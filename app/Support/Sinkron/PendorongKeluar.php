<?php

namespace App\Support\Sinkron;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PENDORONG — Sinkron_Keluar ke database publik.
 *
 * Klaim bersewa lewat usp_WC_Sinkron_Ambil_Keluar (dua pendorong tidak
 * pernah memegang baris yang sama; baris milik pendorong yang mati diambil
 * lagi sesudah sewanya habis), terapkan satu per satu, lalu laporkan lewat
 * usp_WC_Sinkron_Tandai_Keluar (gagal → dicoba lagi 30 dtk … 30 mnt; 20× MATI).
 *
 * Di sisi publik setiap penerapan dicatat di Sinkron_Diterapkan (Event_Id
 * unik): dorongan ulang tidak berefek. Keadaan yang lebih tua dari yang
 * tersimpan dicatat USANG dan diabaikan.
 *
 * Hak peran publik yang dipakai (wc_publik_worker): tulis Pub_Portal_Lamaran,
 * Pub_Dokumen, Sinkron_Diterapkan; kolom status Lamaran & Users; kolom hasil
 * Outbox. Tidak ada yang lain.
 */
final class PendorongKeluar
{
    private const PORTAL = 'N_WEB_CAREERS_Pub_Portal_Lamaran';

    private const DOKUMEN = 'N_WEB_CAREERS_Pub_Dokumen';

    private const DITERAPKAN = 'N_WEB_CAREERS_Sinkron_Diterapkan';

    private const OUTBOX = 'N_WEB_CAREERS_Sinkron_Outbox';

    /** @return array{diklaim: int, terkirim: int, gagal: int} */
    public function dorong(?int $batas = null): array
    {
        $batas ??= (int) config('sinkron.keluar.batas', 200);
        $sewa = (int) config('sinkron.keluar.sewa_detik', 120);

        $baris = DB::select('SET NOCOUNT ON; EXEC dbo.usp_WC_Sinkron_Ambil_Keluar @Batas = ?, @Lease_Detik = ?', [$batas, $sewa]);
        if (! $baris) {
            return ['diklaim' => 0, 'terkirim' => 0, 'gagal' => 0];
        }

        usort($baris, fn ($a, $b) => (int) $a->Id_Keluar <=> (int) $b->Id_Keluar);

        $daftar = [];
        $gagal = 0;
        foreach ($baris as $b) {
            try {
                $this->terapkan($b);
                $daftar[] = ['id' => (int) $b->Id_Keluar, 'hasil' => 'TERKIRIM'];
            } catch (\Throwable $e) {
                $gagal++;
                $daftar[] = ['id' => (int) $b->Id_Keluar, 'hasil' => 'GAGAL', 'galat' => Str::limit($e->getMessage(), 950, '')];
                Log::warning("[SINKRON] dorong {$b->Jenis} {$b->Kunci} v{$b->Versi} gagal (percobaan {$b->Percobaan}): ".$e->getMessage());
            }
        }

        DB::select('SET NOCOUNT ON; EXEC dbo.usp_WC_Sinkron_Tandai_Keluar @Daftar = ?', [json_encode($daftar)]);

        return ['diklaim' => count($baris), 'terkirim' => count($baris) - $gagal, 'gagal' => $gagal];
    }

    /** Dorong sampai antrean kosong atau waktunya habis. */
    public function dorongHabis(int $detik = 30): array
    {
        $total = ['diklaim' => 0, 'terkirim' => 0, 'gagal' => 0];
        $akhir = microtime(true) + $detik;
        do {
            $r = $this->dorong();
            foreach ($total as $k => $v) {
                $total[$k] = $v + $r[$k];
            }
        } while ($r['diklaim'] > 0 && $r['gagal'] < $r['diklaim'] && microtime(true) < $akhir);

        return $total;
    }

    private function terapkan(object $b): void
    {
        $muatan = json_decode((string) $b->Muatan, true);
        if (! is_array($muatan)) {
            throw new RuntimeException('Muatan keluar bukan JSON.');
        }

        // Berkas disalin SEBELUM transaksi publik dibuka — salinan sisi server
        // bersifat idempoten, dan transaksi tidak perlu menunggu jaringan.
        if ($b->Jenis === Keluar::DOKUMEN && ! empty($muatan['aktif'])) {
            $this->siapkanObjek($muatan);
        }

        $pub = self::publik();
        $eventId = strtoupper((string) $b->Event_Id);

        $pub->transaction(function () use ($pub, $b, $muatan, $eventId) {
            if ($pub->table(self::DITERAPKAN)->where('Event_Id', $eventId)->exists()) {
                return; // dorongan ulang — sudah diterapkan
            }

            $hasil = match ($b->Jenis) {
                Keluar::PORTAL => $this->portal($pub, $muatan, (int) $b->Versi),
                Keluar::HASIL => $this->hasil($pub, $muatan),
                Keluar::DOKUMEN => $this->dokumen($pub, $muatan, (int) $b->Versi),
                Keluar::AKUN => $this->akun($pub, $muatan),
                default => throw new RuntimeException("Jenis keluar {$b->Jenis} belum punya penerap."),
            };

            $pub->table(self::DITERAPKAN)->insert([
                'Event_Id' => $eventId,
                'Jenis' => $b->Jenis,
                'Kunci' => mb_substr((string) $b->Kunci, 0, 160),
                'Versi' => (int) $b->Versi,
                'Hasil' => $hasil,
            ]);
        });
    }

    /**
     * Portal.Lamaran — {kode, id_users, lamaran: {kolom status}|null, potret: {kontrak 1}|null}
     */
    private function portal(Connection $pub, array $m, int $versi): string
    {
        $kode = (string) ($m['kode'] ?? '');
        $idUsers = (int) ($m['id_users'] ?? 0);
        if ($kode === '' || $idUsers < 1) {
            throw new RuntimeException('Portal.Lamaran tanpa kode / id_users.');
        }

        $potret = $pub->table(self::PORTAL)->lock('with(rowlock,updlock)')->where('Kode_Lamaran', $kode)->first(['Versi']);
        $lamaran = $pub->table('N_WEB_CAREERS_Lamaran')->lock('with(rowlock,updlock)')->where('Kode', $kode)->first(['Id_Lamaran', 'Id_Users', 'Sinkron_Versi']);

        // Lamaran yang lahir di zona dalam (Talent Pool) dibuatkan barisnya —
        // SEKALI: kunci yang pernah diterapkan lalu barisnya hilang berarti
        // kandidat membatalkannya, dan itu tidak dihidupkan lagi.
        if (! $lamaran && is_array($m['buat'] ?? null)
            && ! $pub->table(self::DITERAPKAN)->where('Jenis', Keluar::PORTAL)->where('Kunci', $kode)->exists()) {
            $pub->table('N_WEB_CAREERS_Lamaran')->insert(array_intersect_key($m['buat'], array_flip([
                'Kategori', 'Program_Id', 'Program_Batch_Id', 'Program_Posisi_Id', 'Mpp_Ref', 'Pembukaan_Id',
                'Master_Alur_Id', 'Asal_Talent_Pool_Id', 'Mulai_Dari_Urutan', 'Waktu_Lamar',
            ])) + [
                'Kode' => $kode,
                'Id_Users' => $idUsers,
                'Urutan_Tahap' => (int) ($m['lamaran']['Urutan_Tahap'] ?? 1),
                'Status' => (string) ($m['lamaran']['Status'] ?? 'BERJALAN'),
                'Created_At' => now(),
                'Created_By' => 'TIM REKRUTMEN',
                'Updated_At' => now(),
            ]);
            $lamaran = $pub->table('N_WEB_CAREERS_Lamaran')->where('Kode', $kode)->first(['Id_Lamaran', 'Id_Users', 'Sinkron_Versi']);
        }

        // Potret hanya berarti bila lamaran publiknya ada. Tanpa baris lamaran
        // (dibatalkan kandidat, atau belum dipindahkan) potret lama dibuang.
        if (! $lamaran) {
            if ($potret) {
                $pub->table(self::PORTAL)->where('Kode_Lamaran', $kode)->delete();
            }

            return 'USANG';
        }

        $kini = max((int) ($potret->Versi ?? 0), (int) $lamaran->Sinkron_Versi);
        if ($versi <= $kini) {
            return 'USANG';
        }

        if ((int) $lamaran->Id_Users !== $idUsers) {
            throw new RuntimeException("Lamaran {$kode} di publik milik akun #{$lamaran->Id_Users}, bukan #{$idUsers}.");
        }

        if (is_array($m['potret'] ?? null)) {
            $json = json_encode($m['potret'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $nilai = [
                'Id_Users' => $idUsers,
                'Versi' => $versi,
                'Muatan' => $json,
                'Hash_Muatan' => DB::raw('0x'.hash('sha256', $json)),
                'Diperbarui_At' => DB::raw('SYSUTCDATETIME()'),
            ];
            $potret
                ? $pub->table(self::PORTAL)->where('Kode_Lamaran', $kode)->update($nilai)
                : $pub->table(self::PORTAL)->insert(['Kode_Lamaran' => $kode] + $nilai);
        } elseif ($potret) {
            $pub->table(self::PORTAL)->where('Kode_Lamaran', $kode)->delete();
        }

        if (is_array($m['lamaran'] ?? null)) {
            $l = $m['lamaran'];
            $pub->table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaran->Id_Lamaran)->update([
                'Urutan_Tahap' => (int) ($l['Urutan_Tahap'] ?? 1),
                'Total_Tahap' => isset($l['Total_Tahap']) ? (int) $l['Total_Tahap'] : null,
                'Status' => (string) ($l['Status'] ?? 'BERJALAN'),
                'Hasil_Akhir' => $l['Hasil_Akhir'] ?? null,
                'Waktu_Selesai' => $l['Waktu_Selesai'] ?? null,
                'Updated_At' => now(),
                'Sinkron_Versi' => $versi,
                'Sinkron_At' => DB::raw('SYSUTCDATETIME()'),
            ]);
        }

        return 'DITERAPKAN';
    }

    /** Peristiwa.Hasil — {event_id, hasil: DIPROSES|DITOLAK, keterangan} */
    private function hasil(Connection $pub, array $m): string
    {
        $hasil = (string) ($m['hasil'] ?? '');
        if (! in_array($hasil, ['DIPROSES', 'DITOLAK'], true)) {
            throw new RuntimeException("Peristiwa.Hasil tidak sah ({$hasil}).");
        }

        $n = $pub->table(self::OUTBOX)
            ->where('Event_Id', strtoupper((string) ($m['event_id'] ?? '')))
            ->whereIn('Status', ['MENUNGGU', 'TERBIT'])
            ->update([
                'Status' => $hasil,
                'Hasil_At' => DB::raw('SYSUTCDATETIME()'),
                'Hasil_Keterangan' => isset($m['keterangan']) ? mb_substr((string) $m['keterangan'], 0, 400) : null,
            ]);

        return $n > 0 ? 'DITERAPKAN' : 'USANG';
    }

    /** Akun.Status — {id_publik, status} */
    private function akun(Connection $pub, array $m): string
    {
        $status = (string) ($m['status'] ?? '');
        if ($status === '' || strlen($status) > 15) {
            throw new RuntimeException('Akun.Status tanpa status yang sah.');
        }

        $n = $pub->table('N_WEB_CAREERS_Users')
            ->where('Id_Users', (int) ($m['id_publik'] ?? 0))
            ->update(['Status' => $status, 'Updated_At' => now()]);

        return $n > 0 ? 'DITERAPKAN' : 'USANG';
    }

    /**
     * Dokumen.Tersedia — {kunci, kode_lamaran, jenis, nama, path, mime, ukuran, sha256, aktif}
     */
    private function dokumen(Connection $pub, array $m, int $versi): string
    {
        $kunci = (string) ($m['kunci'] ?? '');
        if ($kunci === '' || empty($m['kode_lamaran'])) {
            throw new RuntimeException('Dokumen.Tersedia tanpa kunci / kode lamaran.');
        }

        $ada = $pub->table(self::DOKUMEN)->lock('with(rowlock,updlock)')->where('Kunci', $kunci)->first(['Id_Dokumen', 'Versi', 'Objek_Path']);
        if ($ada && (int) $ada->Versi >= $versi) {
            return 'USANG';
        }

        if (empty($m['aktif'])) {
            if ($ada) {
                $pub->table(self::DOKUMEN)->where('Id_Dokumen', $ada->Id_Dokumen)->update([
                    'Flag_Aktif' => 'T',
                    'Versi' => $versi,
                    'Diperbarui_At' => DB::raw('SYSUTCDATETIME()'),
                ]);
            }

            return 'DITERAPKAN';
        }

        $nilai = [
            'Kode_Lamaran' => mb_substr((string) $m['kode_lamaran'], 0, 30),
            'Jenis' => mb_substr((string) ($m['jenis'] ?? 'DOKUMEN'), 0, 40),
            'Nama_Berkas' => mb_substr((string) ($m['nama'] ?? 'dokumen'), 0, 200),
            'Objek_Path' => self::objekPublik($m),
            'Mime' => isset($m['mime']) ? mb_substr((string) $m['mime'], 0, 100) : null,
            'Ukuran' => isset($m['ukuran']) ? (int) $m['ukuran'] : null,
            'Sha256' => ! empty($m['sha256']) ? strtolower((string) $m['sha256']) : null,
            'Versi' => $versi,
            'Flag_Aktif' => 'Y',
            'Diperbarui_At' => DB::raw('SYSUTCDATETIME()'),
        ];
        $ada
            ? $pub->table(self::DOKUMEN)->where('Id_Dokumen', $ada->Id_Dokumen)->update($nilai)
            : $pub->table(self::DOKUMEN)->insert(['Kunci' => mb_substr($kunci, 0, 160)] + $nilai);

        return 'DITERAPKAN';
    }

    private function siapkanObjek(array $m): void
    {
        $tujuan = self::objekPublik($m);
        if (! SalinBerkas::adaDiPublik($tujuan)) {
            SalinBerkas::kePublik((string) ($m['path'] ?? ''), $tujuan);
        }
    }

    /**
     * Path objek di bucket publik — ditentukan ISI dokumennya: dokumen yang
     * sama tidak disalin dua kali, dokumen yang diganti mendapat objek baru.
     */
    public static function objekPublik(array $m): string
    {
        $ext = strtolower(pathinfo((string) ($m['nama'] ?? $m['path'] ?? ''), PATHINFO_EXTENSION)) ?: 'bin';
        $sidik = ! empty($m['sha256']) ? substr((string) $m['sha256'], 0, 32) : md5(($m['kunci'] ?? '').'|'.($m['path'] ?? '').'|'.($m['ukuran'] ?? ''));
        $kode = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($m['kode_lamaran'] ?? 'umum'));

        return "dokumen/{$kode}/{$sidik}.".preg_replace('/[^a-z0-9]/', '', $ext);
    }

    public static function publik(): Connection
    {
        return DB::connection((string) config('sinkron.koneksi_publik', 'pengguna'));
    }
}
