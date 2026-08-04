<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — METRIK REKRUTMEN (sumber kebenaran tunggal untuk hitungan pipeline).
 *
 * Ekspresi di sini dulunya `private static` di dalam MonitoringController, jadi
 * halaman lain yang butuh angka yang SAMA tidak punya pilihan selain menyalinnya.
 * Begitu disalin, dua halaman akan menjawab "berapa pelamar di tahap 3?" dengan
 * angka berbeda begitu salah satu salinan diperbaiki — dan tidak ada yang tahu
 * mana yang benar. Karena itu ekspresinya DIPINDAH ke sini (bukan digandakan):
 * MonitoringController dan DashboardController sama-sama memanggil kelas ini.
 *
 * Isinya sengaja hanya potongan SQL & aritmetika murni — tanpa kueri jadi —
 * supaya tiap pemanggil tetap bebas menyusun scope-nya sendiri (per program,
 * per kategori, per posisi) tanpa kelas ini ikut menebak kebutuhan mereka.
 */
class MetrikRekrutmen
{
    /**
     * Derived table: satu baris per lamaran + UrutanDisplay (tahap yang
     * mewakili) — mirror SQL dari PipelineProgress::tahapKini():
     *  GUGUR/TALENT_POOL → tahap Hasil ybs (fallback tahap terakhir);
     *  LULUS → tahap terakhir; lainnya → tahap BERJALAN (fallback pertama).
     * LEFT JOIN + COALESCE(...,1): lamaran tanpa tahap masuk kolom 1.
     *
     * $kolomTambahan menambah kolom `l.*` ke SELECT sekaligus ke GROUP BY —
     * aman karena l.Id_Lamaran sudah jadi kunci grup (unik per baris), jadi
     * kolom lain milik lamaran yang sama tidak bisa memecah grupnya. Dipakai
     * dashboard MT untuk memecah funnel per Program_Posisi_Id.
     */
    /**
     * Sama seperti sqlUrutanDisplay(), TAPI juga membawa KODE tahap yang
     * mewakili — identitasnya, bukan cuma nomor urutnya.
     *
     * KENAPA PERLU
     * Funnel & papan menyusun kolomnya dari master alur, lalu memasangkan
     * angka lewat nomor urut. Itu benar hanya selama alur tak pernah berubah.
     * Begitu program diarahkan ke alur lain — atau alurnya disunting di tempat,
     * yang memakai ULANG baris per urutan — "tahap ke-3" milik kandidat dan
     * "kolom ke-3" di layar bisa dua hal yang sama sekali berbeda. Angkanya
     * tetap muncul, hanya menempel di tahap yang salah: kegagalan yang tidak
     * menimbulkan satu galat pun, dan justru karena itu tidak pernah ketahuan.
     *
     * Dibungkus, bukan diubah di tempat: agregat lain masih memakai bentuk
     * aslinya, dan menyisipkan JOIN ke dalamnya akan memaksa mereka ikut
     * menanggung biayanya tanpa memerlukan hasilnya.
     */
    public static function sqlUrutanDisplayBerkode(string $whereLamaran, array $kolomTambahan = []): string
    {
        $dalam = self::sqlUrutanDisplay($whereLamaran, $kolomTambahan);

        return "SELECT d.*, lt2.Kode AS KodeDisplay
                FROM ({$dalam}) d
                LEFT JOIN N_WEB_CAREERS_Lamaran_Tahap lt2
                       ON lt2.Lamaran_Id = d.Id_Lamaran
                      AND lt2.Urutan     = d.UrutanDisplay";
    }

    public static function sqlUrutanDisplay(string $whereLamaran, array $kolomTambahan = []): string
    {
        $extra = $kolomTambahan ? ', ' . implode(', ', $kolomTambahan) : '';

        return "SELECT l.Id_Lamaran, l.Program_Id, l.Status{$extra},
                       COALESCE(CASE l.Status
                           WHEN 'GUGUR'       THEN COALESCE(MIN(CASE WHEN lt.Hasil = 'GUGUR' THEN lt.Urutan END), MAX(lt.Urutan))
                           WHEN 'TALENT_POOL' THEN COALESCE(MIN(CASE WHEN lt.Hasil = 'TALENT_POOL' THEN lt.Urutan END), MAX(lt.Urutan))
                           WHEN 'LULUS'       THEN MAX(lt.Urutan)
                           ELSE COALESCE(MIN(CASE WHEN lt.Status = 'BERJALAN' THEN lt.Urutan END), MIN(lt.Urutan))
                       END, 1) AS UrutanDisplay
                FROM N_WEB_CAREERS_Lamaran l
                LEFT JOIN N_WEB_CAREERS_Lamaran_Tahap lt ON lt.Lamaran_Id = l.Id_Lamaran
                WHERE {$whereLamaran}
                GROUP BY l.Id_Lamaran, l.Program_Id, l.Status{$extra}";
    }

    /**
     * UMUR TAHAP — hari sejak tahap mulai dijalani.
     *
     * PENTING: Lamaran_Tahap.Waktu_Mulai TIDAK ditulis saat tahap maju
     * (LamaranService::tetapkanTahap hanya menyetel Status + Updated_At; yang
     * mengisi Waktu_Mulai cuma simpanPengisian()). Jadi untuk sebagian besar
     * tahap kolom itu NULL dan COALESCE ke Created_At-lah yang bekerja —
     * Created_At = saat lamaran dibuat, karena seluruh baris tahap dicetak
     * sekaligus di awal. Artinya angka ini adalah "umur sejak melamar" untuk
     * tahap yang belum pernah diisi formulir, bukan "umur di tahap ini".
     * Jangan diperbaiki di sini: perbaikannya ada di penulisan Waktu_Mulai.
     */
    public static function sqlUmurTahap(string $alias = 'lt'): string
    {
        return "DATEDIFF(day, COALESCE({$alias}.Waktu_Mulai, {$alias}.Created_At), GETDATE())";
    }

    /**
     * AGING — dua jam berbeda, dipilih menurut siapa yang sedang ditunggu:
     *  - Siap_Diputus = 'Y' → mesin sudah selesai, yang menggantung adalah
     *    KEPUTUSAN MANUSIA. Jamnya mulai dari Rekomendasi_At.
     *  - selain itu       → yang ditunggu prosesnya sendiri (kandidat mengisi,
     *    penyedia tes menilai). Jamnya mulai dari tahap mulai dijalani.
     *
     * Menyatukan keduanya jadi satu DATEDIFF akan menyalahkan admin atas
     * tunggu yang bukan urusannya, atau sebaliknya menyembunyikan keputusan
     * yang sudah seminggu didiamkan di balik tahap yang baru dimulai.
     */
    public static function sqlAging(string $alias = 'lt'): string
    {
        return "CASE WHEN {$alias}.Siap_Diputus = 'Y'
                     THEN DATEDIFF(day, COALESCE({$alias}.Rekomendasi_At, {$alias}.Updated_At, {$alias}.Created_At), GETDATE())
                     ELSE " . self::sqlUmurTahap($alias) . ' END';
    }

    /**
     * GILIRAN ADMIN — apakah tahap ini tidak akan bergerak sampai admin bertindak.
     *
     * Ini bukan tebakan per kode tahap melainkan turunan dari master, supaya
     * tipe tahap baru ikut terbaca sendiri tanpa menyentuh kode:
     *
     *   Perilaku_Kode = 'CAT'    → ujian online. Yang menilai sistem, TAPI
     *       jadwalnya dibuat admin. Jadi giliran admin hanya selama
     *       Penjadwalan_Tahap_Id masih NULL; begitu terjadwal, yang ditunggu
     *       kandidat mengerjakan & penyedia menilai.
     *   Perilaku_Kode = 'MANUAL' + Flag_Formulir <> 'Y' → wawancara, MCU,
     *       screening, penawaran. Tidak ada mesin yang akan menyelesaikannya;
     *       giliran admin sejak detik tahap itu berjalan.
     *   Perilaku_Kode = 'MANUAL' + Flag_Formulir = 'Y' → tahap berformulir.
     *       Selama isian kandidat belum masuk (Formulir_Pengisian_Id NULL) yang
     *       ditunggu KANDIDAT, bukan admin. Setelah masuk, giliran admin
     *       memverifikasi.
     *
     * Pengecualian terakhir itulah yang membuat angkanya layak dipercaya:
     * tanpa itu setiap tahap formulir yang baru dibuka akan tampil sebagai
     * "kamu belum mengerjakan ini", dan admin berhenti mempercayai lencananya.
     *
     * Tahap yang Siap_Diputus = 'Y' TIDAK dikecualikan di sini — pemanggil yang
     * memutuskan, karena "siap diputus" punya keranjangnya sendiri.
     *
     * @param  string  $lt   alias N_WEB_CAREERS_Lamaran_Tahap
     * @param  string  $mtt  alias N_WEB_CAREERS_Master_Tipe_Tahap (LEFT JOIN via Kode)
     */
    public static function sqlGiliranAdmin(string $lt = 'lt', string $mtt = 'mtt'): string
    {
        // COALESCE: tahap lama bisa punya Tipe_Tahap_Kode NULL sehingga tidak
        // ketemu barisnya di master. Diperlakukan MANUAL non-formulir — lebih
        // baik muncul dan diabaikan daripada hilang tanpa ada yang tahu.
        $perilaku = "COALESCE({$mtt}.Perilaku_Kode, 'MANUAL')";
        $formulir = "COALESCE({$mtt}.Flag_Formulir, 'T')";

        return "(
            ({$perilaku} = 'CAT' AND {$lt}.Penjadwalan_Tahap_Id IS NULL)
         OR ({$perilaku} = 'MANUAL' AND {$formulir} <> 'Y')
         OR ({$perilaku} = 'MANUAL' AND {$formulir} = 'Y' AND {$lt}.Formulir_Pengisian_Id IS NOT NULL)
        )";
    }

    /**
     * Predikat SQL "kandidat TIDAK sedang ditahan" — NULL-safe.
     *
     * HOLD_Flag NULL diperlakukan sebagai 'T' (tidak ditahan): tanpa COALESCE,
     * `Hold_Flag <> 'Y'` bernilai NULL (bukan TRUE) untuk baris NULL dalam logika
     * tiga-nilai SQL, sehingga baris itu diam-diam berhenti terhitung sebagai
     * "aktif" — kebalikan dari yang diinginkan. Bug ini sudah ditemukan berulang
     * kali di berbagai tempat; helper ini mencegahnya terjadi lagi.
     *
     * @param  string  $lt  alias tabel N_WEB_CAREERS_Lamaran_Tahap
     */
    public static function sqlBukanDitahan(string $lt = 'lt'): string
    {
        return "COALESCE({$lt}.Hold_Flag, 'T') <> 'Y'";
    }

    /** Ambang "macet" (hari) — tahap BERJALAN lebih lama dari ini dianggap tersendat. */
    public static function macetHari(): int
    {
        return (int) config('career_monitoring.macet_hari', 7);
    }

    /** Ambang sorot merah untuk keputusan yang menggantung (hari). */
    public static function sorotHari(): int
    {
        return (int) config('career_monitoring.siap_diputus_sorot_hari', 2);
    }

    /**
     * SKOR KESEHATAN 0-100 dari agregat tahap BERJALAN satu program.
     *
     * Dihukum oleh dua hal saja, dan keduanya memang salah admin:
     *  - macet   : tahap berjalan yang lewat ambang tanpa siap diputus;
     *  - siapTua : keputusan yang sudah siap tapi didiamkan.
     * Sengaja TIDAK menghukum "menunggu tes pihak ke-3": sebabnya di luar
     * kendali (kandidat/penyedia tes belum menuntaskan).
     *
     * Tanpa proses berjalan skor null (netral, bukan 0), dengan DUA label
     * berbeda — keduanya sering tertukar dan menyesatkan kalau disamakan:
     *  - KOSONG  : belum ada pelamar sama sekali;
     *  - SELESAI : pernah ada pelamar, tapi semuanya sudah tuntas.
     */
    public static function skorSehat(?object $agg, int $totalPelamar = 0): array
    {
        $aktif = (int) ($agg->aktif ?? 0);
        $macet = (int) ($agg->macet ?? 0);
        $siapTua = (int) ($agg->siapTua ?? 0);

        if ($aktif < 1) {
            return ['skor' => null, 'label' => $totalPelamar > 0 ? 'SELESAI' : 'KOSONG',
                'aktif' => 0, 'macet' => 0, 'siapMenggantung' => 0,
                'siapDiputus' => (int) ($agg->siap ?? 0),
                'menungguTes' => (int) ($agg->nungguTes ?? 0), 'maxAging' => null];
        }

        $skor = (int) max(5, round(100 - 60 * ($macet / $aktif) - 80 * ($siapTua / $aktif)));

        return [
            'skor' => $skor,
            'label' => $skor >= 80 ? 'SEHAT' : ($skor >= 50 ? 'PERLU_AKSI' : 'KRITIS'),
            'aktif' => $aktif,
            'macet' => $macet,
            'siapMenggantung' => $siapTua,
            'siapDiputus' => (int) ($agg->siap ?? 0),
            'menungguTes' => (int) ($agg->nungguTes ?? 0),
            'maxAging' => $agg->maxAging !== null ? max(0, (int) $agg->maxAging) : null,
        ];
    }

    /**
     * AGREGAT KESEHATAN per program — enam ukuran dalam satu GROUP BY.
     * Keluarannya adalah objek yang diharapkan skorSehat() di atas.
     */
    public static function agregatSehat(array $programIds)
    {
        if (! $programIds) {
            return collect();
        }

        $macet = self::macetHari();
        $sorot = self::sorotHari();
        $umur = self::sqlUmurTahap('lt');
        $aging = self::sqlAging('lt');
        $bukanDitahan = self::sqlBukanDitahan('lt');

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $programIds)
            ->groupBy('l.Program_Id')
            ->selectRaw("l.Program_Id,
                         COUNT(*) as aktif,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus <> 'Y' AND {$umur} > {$macet} THEN 1 ELSE 0 END) as macet,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus = 'Y'
                                   AND DATEDIFF(day, COALESCE(lt.Rekomendasi_At, lt.Updated_At, lt.Created_At), GETDATE()) > {$sorot}
                                  THEN 1 ELSE 0 END) as siapTua,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siap,
                         SUM(CASE WHEN {$bukanDitahan} AND lt.Provider = 'THIRD_PARTY' AND lt.Siap_Diputus = 'N' THEN 1 ELSE 0 END) as nungguTes,
                         MAX({$aging}) as maxAging")
            ->get()
            ->keyBy('Program_Id');
    }

    /** Jam server, dikirim di setiap respons supaya user tahu data per kapan. */
    public static function checkpoint(): array
    {
        $now = now();

        return ['waktu' => $now->format('Y-m-d H:i:s'), 'label' => $now->format('d M Y H:i')];
    }
}
