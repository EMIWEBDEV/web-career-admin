<?php

namespace App\Support\Sinkron;

use App\Support\Audit\KonteksAudit;
use App\Support\Sinkron\Penangan\PenanganDitolak;
use App\Support\Sinkron\Penangan\PenanganMati;
use App\Support\Sinkron\Potret\PembangunPotret;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PEMROSES KOTAK MASUK — menjalankan kode admin yang sudah ada untuk setiap
 * peristiwa DITERIMA, tepat sekali, URUT per akun.
 *
 * Satu peristiwa = satu transaksi Admin DB:
 *   sp_getapplock per kunci urut (dua pemroses tidak menggarap akun yang sama)
 *   → baris dikunci & dipastikan masih DITERIMA/GAGAL
 *   → tidak ada peristiwa lebih tua akun itu yang belum tuntas
 *   → penangan (efek bisnis)
 *   → DIPROSES + Peristiwa.Hasil ke Sinkron_Keluar
 * Ulangan (queue mengulang, dua tick bersamaan) melihat DIPROSES lalu berhenti.
 *
 * Gagal → GAGAL dengan jeda bertambah (30 dtk … 30 mnt); habis percobaan →
 * MATI (alarm, putar ulang: `php artisan sinkron:ulang`). PeristiwaDitolak →
 * DITOLAK, alasannya sampai ke kandidat.
 */
final class PemrosesMasuk
{
    private const T = KotakMasuk::TABEL;

    private const TUNTAS = ['DIPROSES', 'DITOLAK', 'MATI'];

    /** @var array<string, class-string<\App\Support\Sinkron\Penangan\Penangan>> */
    public const PENANGAN = [
        'Akun.Terdaftar' => \App\Support\Sinkron\Penangan\AkunTerdaftar::class,
        'Akun.Diperbarui' => \App\Support\Sinkron\Penangan\AkunDiperbarui::class,
        'Akun.KodeDiminta' => \App\Support\Sinkron\Penangan\AkunKodeDiminta::class,
        'Lamaran.Dikirim' => \App\Support\Sinkron\Penangan\LamaranDikirim::class,
        'Lamaran.Dibatalkan' => \App\Support\Sinkron\Penangan\LamaranDibatalkan::class,
        'Formulir.Dikirim' => \App\Support\Sinkron\Penangan\FormulirDikirim::class,
        'Berkas.Diunggah' => \App\Support\Sinkron\Penangan\BerkasDiunggah::class,
        'Konfirmasi.Dijawab' => \App\Support\Sinkron\Penangan\KonfirmasiDijawab::class,
        'Konfirmasi.Dicabut' => \App\Support\Sinkron\Penangan\KonfirmasiDicabut::class,
        'Feedback.Dikirim' => \App\Support\Sinkron\Penangan\FeedbackDikirim::class,
    ];

    /**
     * Semua akun yang punya peristiwa jatuh tempo, mulai dari yang tertua.
     *
     * @return array{diproses: int, ditolak: int, gagal: int, mati: int, kunci: int}
     */
    public function prosesJatuhTempo(?int $batas = null): array
    {
        $batas ??= (int) config('sinkron.masuk.batas', 200);
        $kini = Carbon::now('UTC');

        $kunci = DB::table(self::T)
            ->whereIn('Status', ['DITERIMA', 'GAGAL'])
            ->where(fn ($q) => $q->whereNull('Coba_Lagi_At')->orWhere('Coba_Lagi_At', '<=', $kini))
            ->groupBy('Kunci_Urut')
            ->orderByRaw('MIN([Id_Masuk])')
            ->limit($batas)
            ->pluck('Kunci_Urut');

        $total = ['diproses' => 0, 'ditolak' => 0, 'gagal' => 0, 'mati' => 0, 'kunci' => 0];
        foreach ($kunci as $k) {
            $r = $this->prosesKunci((string) $k);
            foreach (['diproses', 'ditolak', 'gagal', 'mati'] as $x) {
                $total[$x] += $r[$x];
            }
            $total['kunci']++;
            if ($total['diproses'] + $total['ditolak'] >= $batas) {
                break;
            }
        }

        return $total;
    }

    /**
     * Peristiwa satu akun, urut Id. Berhenti di peristiwa pertama yang gagal
     * (yang sesudahnya tidak boleh menyalip) atau yang masih menunggu jedanya.
     *
     * @return array{diproses: int, ditolak: int, gagal: int, mati: int}
     */
    public function prosesKunci(string $kunciUrut, int $batas = 50): array
    {
        $hasil = ['diproses' => 0, 'ditolak' => 0, 'gagal' => 0, 'mati' => 0];

        for ($i = 0; $i < $batas; $i++) {
            $depan = DB::table(self::T)
                ->where('Kunci_Urut', $kunciUrut)
                ->whereIn('Status', ['DITERIMA', 'GAGAL'])
                ->orderBy('Id_Masuk')
                ->first(['Id_Masuk', 'Status', 'Coba_Lagi_At']);

            if (! $depan) {
                break;
            }
            if ($depan->Coba_Lagi_At && Carbon::parse($depan->Coba_Lagi_At, 'UTC')->gt(Carbon::now('UTC'))) {
                break;
            }

            $r = $this->prosesSatu((int) $depan->Id_Masuk);
            if ($r === 'SIBUK') {
                break;
            }
            $kunci = strtolower($r);
            if (isset($hasil[$kunci])) {
                $hasil[$kunci]++;
            }
            if ($r === 'GAGAL') {
                break;
            }
        }

        return $hasil;
    }

    /**
     * Jejak audit: setiap perubahan data admin oleh peristiwa ini tercatat
     * bersumber SINKRON, beraktor akun kandidatnya, ber-id Event_Id-nya.
     *
     * @return string DIPROSES | DITOLAK | GAGAL | MATI | SIBUK | LEWAT
     */
    public function prosesSatu(int $idMasuk): string
    {
        return KonteksAudit::dengan(
            ['sumber' => 'SINKRON', 'konteks' => "masuk:{$idMasuk}", 'aktor_id' => null, 'aktor' => null, 'ip' => null],
            fn () => $this->proses($idMasuk),
        );
    }

    private function proses(int $idMasuk): string
    {
        $sesudah = null;

        try {
            $sesudah = DB::transaction(function () use ($idMasuk) {
                $baris = DB::table(self::T)->where('Id_Masuk', $idMasuk)->first(['Kunci_Urut']);
                if (! $baris || ! self::kunciAplikasi((string) $baris->Kunci_Urut)) {
                    return 'SIBUK';
                }

                $baris = DB::table(self::T)
                    ->lock('with(rowlock,updlock)')
                    ->where('Id_Masuk', $idMasuk)
                    ->whereIn('Status', ['DITERIMA', 'GAGAL'])
                    ->first();
                if (! $baris) {
                    return 'LEWAT'; // sudah dituntaskan pemroses lain
                }

                $lebihTua = DB::table(self::T)
                    ->where('Kunci_Urut', $baris->Kunci_Urut)
                    ->where('Id_Masuk', '<', $idMasuk)
                    ->whereNotIn('Status', self::TUNTAS)
                    ->exists();
                if ($lebihTua) {
                    return 'SIBUK';
                }

                $muatan = json_decode((string) $baris->Muatan, true) ?: [];
                KonteksAudit::tambah([
                    'konteks' => "masuk:{$idMasuk} {$baris->Jenis}",
                    'aktor' => (string) $baris->Kunci_Urut,
                    'ip' => is_string($muatan['ip'] ?? null) ? $muatan['ip'] : null,
                    'permintaan' => strtolower((string) $baris->Event_Id),
                ]);
                $kelas = self::PENANGAN[$baris->Jenis] ?? null;
                if (! $kelas) {
                    throw new PeristiwaDitolak("Jenis {$baris->Jenis} belum punya penangan.");
                }

                /** @var \App\Support\Sinkron\Penangan\HasilPenanganan $h */
                $h = app($kelas)->tangani($muatan, $baris);

                DB::table(self::T)->where('Id_Masuk', $idMasuk)->update([
                    'Status' => 'DIPROSES',
                    'Percobaan' => (int) $baris->Percobaan + 1,
                    'Coba_Lagi_At' => null,
                    'Diproses_At' => Carbon::now('UTC'),
                    'Hasil_Ref' => $h->ref !== null ? mb_substr($h->ref, 0, 80) : null,
                    'Hasil_Keterangan' => $h->keterangan !== null ? mb_substr($h->keterangan, 0, 400) : null,
                    'Galat_Terakhir' => null,
                    'Updated_At' => Carbon::now('UTC'),
                ]);

                self::kabari((string) $baris->Event_Id, 'DIPROSES', $h->keterangan);

                return $h;
            });
        } catch (PeristiwaDitolak $e) {
            return $this->tandaiDitolak($idMasuk, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->tandaiGagal($idMasuk, $e);
        }

        if (is_string($sesudah)) {
            return $sesudah;
        }

        // ── SESUDAH COMMIT ─────────────────────────────────────────────────
        // Peristiwanya sudah tersimpan DIPROSES; apa pun yang gagal di sini
        // (antrean surel, HCLearn, potret) tidak boleh membalikkannya.
        foreach ($sesudah->sesudah as $kerja) {
            try {
                $kerja();
            } catch (\Throwable $e) {
                Log::warning("[SINKRON] pekerjaan sesudah peristiwa #{$idMasuk} gagal: ".$e->getMessage());
            }
        }
        foreach (array_unique($sesudah->lamaran) as $lamaranId) {
            try {
                PembangunPotret::segarkan((int) $lamaranId);
            } catch (\Throwable $e) {
                Log::warning("[SINKRON] potret lamaran #{$lamaranId} gagal disegarkan: ".$e->getMessage());
            }
        }

        return 'DIPROSES';
    }

    private function tandaiDitolak(int $idMasuk, string $alasan): string
    {
        try {
            DB::transaction(function () use ($idMasuk, $alasan) {
                $baris = DB::table(self::T)->lock('with(rowlock,updlock)')
                    ->where('Id_Masuk', $idMasuk)->whereIn('Status', ['DITERIMA', 'GAGAL'])->first();
                if (! $baris) {
                    return;
                }

                $kelas = self::PENANGAN[$baris->Jenis] ?? null;
                $penangan = $kelas ? app($kelas) : null;
                if ($penangan instanceof PenanganDitolak) {
                    $penangan->ditolak(json_decode((string) $baris->Muatan, true) ?: [], $baris, $alasan);
                }

                DB::table(self::T)->where('Id_Masuk', $idMasuk)->update([
                    'Status' => 'DITOLAK',
                    'Percobaan' => (int) $baris->Percobaan + 1,
                    'Coba_Lagi_At' => null,
                    'Diproses_At' => Carbon::now('UTC'),
                    'Hasil_Keterangan' => mb_substr($alasan, 0, 400),
                    'Updated_At' => Carbon::now('UTC'),
                ]);

                self::kabari((string) $baris->Event_Id, 'DITOLAK', $alasan);
            });
        } catch (\Throwable $e) {
            return $this->tandaiGagal($idMasuk, $e);
        }

        Log::info("[SINKRON] peristiwa #{$idMasuk} DITOLAK: {$alasan}");

        return 'DITOLAK';
    }

    private function tandaiGagal(int $idMasuk, \Throwable $e): string
    {
        $baris = DB::table(self::T)->where('Id_Masuk', $idMasuk)->first(['Percobaan', 'Jenis', 'Kunci_Urut', 'Status']);
        if (! $baris || ! in_array($baris->Status, ['DITERIMA', 'GAGAL'], true)) {
            return 'LEWAT';
        }

        $ke = (int) $baris->Percobaan + 1;
        $mati = $ke >= max(1, (int) config('sinkron.masuk.maks_percobaan', 8));
        $jeda = min(1800, 30 * (2 ** min(10, $ke - 1)));

        DB::table(self::T)->where('Id_Masuk', $idMasuk)->whereIn('Status', ['DITERIMA', 'GAGAL'])->update([
            'Status' => $mati ? 'MATI' : 'GAGAL',
            'Percobaan' => $ke,
            'Coba_Lagi_At' => $mati ? null : Carbon::now('UTC')->addSeconds($jeda),
            'Galat_Terakhir' => Str::limit(get_class($e).': '.$e->getMessage(), 990, ''),
            'Updated_At' => Carbon::now('UTC'),
        ]);

        $pesan = "[SINKRON] peristiwa #{$idMasuk} {$baris->Jenis} ({$baris->Kunci_Urut}) ".($mati ? 'MATI' : "GAGAL, coba lagi {$jeda} dtk")
            ." (percobaan {$ke}): ".$e->getMessage();
        $mati ? Log::critical($pesan) : Log::warning($pesan);

        if ($mati && ($kelas = self::PENANGAN[$baris->Jenis] ?? null)) {
            try {
                $penangan = app($kelas);
                if ($penangan instanceof PenanganMati) {
                    $penuh = DB::table(self::T)->where('Id_Masuk', $idMasuk)->first();
                    $penangan->mati(json_decode((string) $penuh->Muatan, true) ?: [], $penuh, $e->getMessage());
                }
            } catch (\Throwable $x) {
                Log::warning("[SINKRON] jejak MATI peristiwa #{$idMasuk} gagal ditulis: ".$x->getMessage());
            }
        }

        return $mati ? 'MATI' : 'GAGAL';
    }

    /** Kabar balik ke Outbox publik — di transaksi yang sama dengan statusnya. */
    private static function kabari(string $eventId, string $hasil, ?string $keterangan): void
    {
        Keluar::antrekan(Keluar::HASIL, strtolower($eventId), [
            'event_id' => strtolower($eventId),
            'hasil' => $hasil,
            'keterangan' => $keterangan !== null ? mb_substr($keterangan, 0, 400) : null,
        ]);
    }

    /**
     * Kunci aplikasi per akun, dilepas otomatis saat transaksi selesai.
     * Tanpa menunggu: pemroses yang kalah cepat langsung mundur (SIBUK).
     */
    private static function kunciAplikasi(string $kunciUrut): bool
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return true;
        }

        $r = DB::selectOne(
            "SET NOCOUNT ON; DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 0;
             SELECT @r AS r;",
            ['wc-sinkron:'.$kunciUrut],
        );

        return (int) ($r->r ?? -1) >= 0;
    }
}
