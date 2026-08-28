<?php

namespace App\Support\Career;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREERS — BATAS WAKTU (SLA) SEBUAH MPP, DIHITUNG DALAM HARI KERJA.
 *
 * Satu tempat untuk dua pertanyaan yang harus selalu dijawab sama:
 *
 *   "Level ini berapa hari kerja?"       → aturanLevel()
 *   "Kalau mulai hari ini, tenggatnya kapan?" → batas() / tambahHariKerja()
 *
 * Dipakai layar (untuk mengunci pemilih tanggal), pintu simpan (untuk menolak
 * tanggal di luar ketentuan), dan pembekuan snapshot. Ketiganya WAJIB memakai
 * hitungan yang sama — kalau tidak, kalender di layar menawarkan tanggal yang
 * lalu ditolak server, dan admin menyalahkan sistem untuk aturan yang memang
 * ada.
 *
 * ── KENAPA HARI KERJA ───────────────────────────────────────────────────────
 *
 * "45 hari" yang dijanjikan ke pemohon berarti 45 hari KERJA. Dihitung sebagai
 * hari kalender, tenggat yang jatuh di sekitar Lebaran atau Natal bisa memakan
 * dua pekan libur — dan tim rekrutmen dinilai telat atas hari-hari yang memang
 * kantornya tutup.
 *
 * Hari kerja = Senin–Jumat, dikurangi tanggal di HRIS_Hari_Libur. Tabel itu
 * sudah ada dan diurus modul HRIS; di sini ia hanya dibaca.
 *
 * ── KENAPA SELURUHNYA BERPENJAGA hasTable/hasColumn ─────────────────────────
 *
 * Skrip skemanya (docs/20-8-2026) dijalankan admin, bukan oleh kode. Selama
 * belum dijalankan, seluruh kelas ini menjawab "tidak ada aturan" dan MPP
 * berjalan persis seperti sebelumnya — tanpa satu pun galat di layar yang tidak
 * ada hubungannya dengan pekerjaan orang yang sedang memakainya.
 */
class SlaMpp
{
    public const TABEL = 'N_WEB_CAREERS_Master_Sla_Mpp';

    private const KODE_PERUSAHAAN = '001';

    /** Batas jumlah hari kerja yang ditelusuri — penahan gelung tak berujung. */
    private const MAKS_LANGKAH = 4000;

    /** Masternya sudah dipasang? */
    public static function siap(): bool
    {
        static $ada = null;

        return $ada ??= Skema::adaTabel(self::TABEL);
    }

    /** Kolom snapshot sudah ada di transaksi MPP? */
    public static function siapSnapshot(): bool
    {
        static $ada = null;

        return $ada ??= Skema::adaKolom('N_WEB_CAREERS_Detail_MPP', 'Sla_Hari_Kerja');
    }

    /**
     * Aturan yang berlaku untuk sebuah level — null berarti level itu memang
     * tidak dikunci, dan MPP-nya bebas memilih tanggal seperti sebelumnya.
     */
    public static function aturanLevel(?int $idLevel): ?object
    {
        if (! $idLevel || ! self::siap()) {
            return null;
        }

        return DB::table(self::TABEL)
            ->where('Id_Level', $idLevel)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Sla_Mpp', 'Id_Level', 'Nama_Level', 'Hari_Kerja', 'Keterangan']);
    }

    /**
     * Tenggat sebuah MPP: mulai + N hari kerja.
     *
     * @return array{hari:?int, mulai:string, batas:?string, masterId:?int, nama:?string}
     *         `batas` null = level ini tidak dikunci.
     */
    public static function batas(?int $idLevel, ?string $mulai = null): array
    {
        $awal = $mulai ? Carbon::parse($mulai)->startOfDay() : Carbon::today();
        $aturan = self::aturanLevel($idLevel);

        if (! $aturan) {
            return ['hari' => null, 'mulai' => $awal->toDateString(), 'batas' => null, 'masterId' => null, 'nama' => null];
        }

        return [
            'hari' => (int) $aturan->Hari_Kerja,
            'mulai' => $awal->toDateString(),
            'batas' => self::tambahHariKerja($awal, (int) $aturan->Hari_Kerja)->toDateString(),
            'masterId' => (int) $aturan->Id_Sla_Mpp,
            'nama' => $aturan->Nama_Level,
        ];
    }

    /**
     * Tenggat dari ANGKA yang belum tersimpan di master.
     *
     * Dipakai borang master saat admin masih mengetik jumlah harinya: contoh
     * tanggalnya harus ikut bergerak sebelum ada satu baris pun tersimpan.
     * Lewat sini, bukan dihitung ulang di layar — supaya contoh yang dibaca
     * admin memakai penanggalan yang sama persis dengan yang kelak menegakkan
     * tenggatnya.
     *
     * @return array{hari:int, mulai:string, batas:string, masterId:null, nama:null}
     */
    public static function batasDariAngka(int $hari, ?string $mulai = null): array
    {
        $awal = $mulai ? Carbon::parse($mulai)->startOfDay() : Carbon::today();

        return [
            'hari' => $hari,
            'mulai' => $awal->toDateString(),
            'batas' => self::tambahHariKerja($awal, $hari)->toDateString(),
            'masterId' => null,
            'nama' => null,
        ];
    }

    /**
     * Maju N HARI KERJA dari sebuah tanggal.
     *
     * Hari mulainya sendiri TIDAK ikut dihitung — "45 hari kerja sejak
     * hari ini" berarti hari ini masih hari ke-0, dan hitungan mulai dari hari
     * kerja berikutnya. Menghitung hari pengajuan sebagai hari kerja penuh
     * berarti MPP yang diajukan pukul lima sore kehilangan satu hari yang
     * tak pernah bisa ia pakai.
     */
    public static function tambahHariKerja(Carbon $mulai, int $hari): Carbon
    {
        $tanggal = $mulai->copy()->startOfDay();

        if ($hari < 1) {
            return $tanggal;
        }

        $libur = self::hariLibur($tanggal, $tanggal->copy()->addDays(self::batasJangkauan($hari)));
        $sisa = $hari;
        $langkah = 0;

        while ($sisa > 0 && $langkah < self::MAKS_LANGKAH) {
            $tanggal->addDay();
            $langkah++;

            if (self::hariKerja($tanggal, $libur)) {
                $sisa--;
            }
        }

        return $tanggal;
    }

    /**
     * Berapa hari kerja antara dua tanggal (tidak termasuk hari mulai).
     *
     * Dipakai laporan: "tenggatnya 45, terpakai 51" — dan angka itu harus
     * dihitung dengan penanggalan yang sama dengan yang menetapkan tenggatnya.
     */
    public static function selisihHariKerja(string $dari, string $sampai): int
    {
        $a = Carbon::parse($dari)->startOfDay();
        $b = Carbon::parse($sampai)->startOfDay();

        if ($b->lte($a)) {
            return 0;
        }

        $libur = self::hariLibur($a, $b);
        $n = 0;
        $jalan = $a->copy();

        while ($jalan->lt($b)) {
            $jalan->addDay();
            if (self::hariKerja($jalan, $libur)) {
                $n++;
            }
        }

        return $n;
    }

    /** Senin–Jumat dan bukan hari libur terdaftar. */
    private static function hariKerja(Carbon $t, array $libur): bool
    {
        return ! $t->isWeekend() && ! isset($libur[$t->toDateString()]);
    }

    /**
     * Hari libur dalam satu rentang — SATU kueri untuk seluruh perhitungan.
     *
     * Menanyakannya per hari berarti 45 kueri untuk satu tanggal target, dan
     * layar yang memanggil ini tiap kali level diganti akan terasa tersendat
     * tanpa sebab yang terlihat.
     */
    private static function hariLibur(Carbon $dari, Carbon $sampai): array
    {
        if (! Skema::adaTabel('HRIS_Hari_Libur')) {
            return [];
        }

        return DB::table('HRIS_Hari_Libur')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->whereBetween('Tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->pluck('Tanggal')
            ->mapWithKeys(fn ($t) => [Carbon::parse($t)->toDateString() => true])
            ->all();
    }

    /**
     * Rentang kalender yang perlu diambil libur-nya untuk N hari kerja.
     *
     * Sepekan berisi lima hari kerja, jadi N hari kerja paling banyak memakan
     * sekitar N/5 pekan; ditambah bantalan 30 hari untuk rentetan libur panjang.
     * Kelebihan mengambil beberapa baris libur jauh lebih murah daripada
     * kekurangan — yang akibatnya tenggat meleset diam-diam.
     */
    private static function batasJangkauan(int $hari): int
    {
        return (int) ceil($hari * 7 / 5) + 30;
    }
}
