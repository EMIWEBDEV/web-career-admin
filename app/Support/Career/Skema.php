<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WEB CAREERS — PENJAGA SKEMA YANG DIBACA SEKALI, BUKAN BERULANG.
 *
 * ══ KENAPA INI ADA ═════════════════════════════════════════════════════════
 *
 * Modul ini penuh penjaga berbentuk `Schema::hasTable()` / `hasColumn()`, dan
 * penjaga itu memang benar: skrip SQL-nya dijalankan manual per lingkungan,
 * jadi kode harus tetap hidup di basis data yang belum menjalankannya.
 *
 * Yang tidak disadari adalah HARGANYA. Pada driver sqlsrv, `hasTable()` bukan
 * pencarian satu baris — ia menjumlahkan `total_pages` seluruh tabel di
 * database untuk melaporkan ukurannya, dan satu panggilan memakan ±220 ms.
 * `hasColumn()` menembak sys.columns, ±57 ms. Angka itu tidak terasa pada satu
 * panggilan, dan menjadi bencana begitu penjaganya duduk di dalam perulangan.
 *
 * Papan worklist satu lowongan berisi 352 kandidat pernah menghabiskan 326
 * DETIK, dan 158 detik di antaranya cuma pemeriksaan skema: dua penjaga yang
 * dipanggil sekali per aktivitas kandidat, 1.100 kali masing-masing. Endpoint-
 * nya tidak pernah selesai dimuat; rekruter melihat "pending" sampai menyerah.
 *
 * ══ CARANYA ════════════════════════════════════════════════════════════════
 *
 * Dibaca dari INFORMATION_SCHEMA — pandangan standar yang murah dan tidak
 * menghitung ukuran apa pun — lalu dipegang statis. Statis per proses PHP:
 * pada permintaan web ia hidup selama satu permintaan, dan skema tidak berubah
 * di tengah permintaan. Pada worker antrean yang berumur panjang, `lupakan()`
 * disediakan untuk dipanggil sesudah migrasi.
 *
 * Bila INFORMATION_SCHEMA tak bisa dibaca (hak akses, driver lain), ia jatuh
 * ke Schema bawaan Laravel — lebih lambat, tapi jawabannya tetap benar, dan
 * jawabannya tetap disimpan supaya hanya mahal sekali.
 */
final class Skema
{
    /** @var array<string,true>|null nama tabel (huruf kecil) */
    private static ?array $tabel = null;

    /** @var array<string, array<string,true>> nama kolom per tabel (huruf kecil) */
    private static array $kolom = [];

    /** @var array<string,bool> jawaban satuan, dipakai bila daftarnya tak terbaca */
    private static array $tabelSatuan = [];

    public static function adaTabel(string $tabel): bool
    {
        $daftar = self::daftarTabel();

        if ($daftar !== []) {
            return isset($daftar[strtolower($tabel)]);
        }

        // INFORMATION_SCHEMA tidak terbaca. JANGAN menyimpulkan "tidak ada" —
        // itu akan mematikan seluruh fitur yang dijaga penanda ini secara diam-
        // diam. Ditanyakan satu per satu lewat Schema, lalu jawabannya disimpan
        // supaya tetap hanya mahal sekali.
        return self::$tabelSatuan[strtolower($tabel)] ??= Schema::hasTable($tabel);
    }

    public static function adaKolom(string $tabel, string $kolom): bool
    {
        return isset(self::daftarKolom($tabel)[strtolower($kolom)]);
    }

    /**
     * Lupakan yang sudah dibaca.
     *
     * Dipakai sesudah skrip skema dijalankan di dalam proses yang sama —
     * pengujian, dan worker antrean yang berumur panjang. Tanpa ini, proses
     * yang sudah terlanjur menyimpulkan "kolomnya belum ada" akan bertahan
     * pada kesimpulan itu sampai ia mati.
     */
    public static function lupakan(): void
    {
        self::$tabel = null;
        self::$kolom = [];
        self::$tabelSatuan = [];
    }

    /** @return array<string,true> */
    private static function daftarTabel(): array
    {
        if (self::$tabel !== null) {
            return self::$tabel;
        }

        try {
            $nama = collect(DB::select('SELECT TABLE_NAME AS n FROM INFORMATION_SCHEMA.TABLES'))
                ->pluck('n')->map(fn ($n) => strtolower((string) $n))->all();

            return self::$tabel = array_fill_keys($nama, true);
        } catch (\Throwable $e) {
            // Basis data yang tidak punya INFORMATION_SCHEMA (SQLite pada uji).
            // Dijawab satu per satu lewat Schema, dan tetap disimpan.
            return self::$tabel = [];
        }
    }

    /** @return array<string,true> */
    private static function daftarKolom(string $tabel): array
    {
        $kunci = strtolower($tabel);

        if (isset(self::$kolom[$kunci])) {
            return self::$kolom[$kunci];
        }

        try {
            $nama = collect(DB::select(
                'SELECT COLUMN_NAME AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
                [$tabel]
            ))->pluck('n')->map(fn ($n) => strtolower((string) $n))->all();

            if ($nama) {
                return self::$kolom[$kunci] = array_fill_keys($nama, true);
            }
        } catch (\Throwable $e) {
            // jatuh ke bawah
        }

        try {
            $nama = array_map('strtolower', Schema::getColumnListing($tabel));

            return self::$kolom[$kunci] = array_fill_keys($nama, true);
        } catch (\Throwable $e) {
            return self::$kolom[$kunci] = [];
        }
    }
}
