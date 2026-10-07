<?php

namespace App\Support\Sinkron;

use App\Support\Audit\KonteksAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * JEJAK OPERATOR SINKRON (N_WEB_CAREERS_Sinkron_Operasi) — siapa memutar
 * ulang peristiwa MATI, merekonsiliasi salinan, membangun ulang potret, atau
 * menjalankan migrasi awal; dengan argumen apa; baris mana yang terdampak.
 *
 * Tick otomatis tidak dicatat di sini. Gagal mencatat tidak menghentikan
 * perintahnya — tetapi perubahan tabel bisnis yang diaudit tetap tercatat
 * pemicu dengan aktor operator ini.
 */
final class OperasiSinkron
{
    public const TABEL = 'N_WEB_CAREERS_Sinkron_Operasi';

    /** Batas rincian per daftar di Ringkasan — sisanya cukup dihitung. */
    private const MAKS_DAFTAR = 500;

    /**
     * @param  \Closure(\ArrayObject): int  $kerja  isi $catatan['jumlah'] & rincian lain; kembalikan kode keluar
     */
    public static function jalankan(Command $perintah, \Closure $kerja): int
    {
        $operator = self::operator($perintah);
        $catatan = new \ArrayObject;
        $id = self::mulai($perintah, $operator);
        KonteksAudit::tambah([
            'sumber' => 'KONSOL',
            'aktor' => $operator,
            'konteks' => 'artisan '.$perintah->getName().($id ? " #op{$id}" : ''),
        ]);

        try {
            $kode = (int) $kerja($catatan);
        } catch (\Throwable $e) {
            self::selesai($id, 'GAGAL', $catatan, $e->getMessage());
            throw $e;
        }
        self::selesai($id, $kode === Command::SUCCESS ? 'BERHASIL' : 'GAGAL', $catatan);

        return $kode;
    }

    /** --oleh bila disebut, kalau tidak pengguna@mesin. */
    public static function operator(Command $perintah): string
    {
        $oleh = $perintah->getDefinition()->hasOption('oleh') ? trim((string) $perintah->option('oleh')) : '';

        return $oleh !== '' ? mb_substr($oleh, 0, 150) : KonteksAudit::operatorOs();
    }

    /** Daftar id untuk Ringkasan — dipotong, jumlahnya tetap utuh. */
    public static function daftar(iterable $id): array
    {
        $semua = array_values(is_array($id) ? $id : iterator_to_array($id, false));

        return count($semua) > self::MAKS_DAFTAR
            ? ['jumlah' => count($semua), 'awal' => array_slice($semua, 0, self::MAKS_DAFTAR)]
            : $semua;
    }

    private static function mulai(Command $perintah, string $operator): ?int
    {
        $argumen = array_filter(
            array_merge(array_diff_key($perintah->arguments(), ['command' => 1]), array_diff_key($perintah->options(), array_flip(['oleh', 'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env']))),
            fn ($v) => $v !== null && $v !== false && $v !== [],
        );

        try {
            // Waktu dari jam server database (sama dengan jejak audit lainnya).
            return (int) DB::table(self::TABEL)->insertGetId([
                'Perintah' => mb_substr((string) $perintah->getName(), 0, 60),
                'Argumen' => $argumen ? Str::limit(json_encode($argumen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1000, '') : null,
                'Operator' => $operator,
                'Status' => 'BERJALAN',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SINKRON] jejak operator tidak tercatat: '.$e->getMessage());

            return null;
        }
    }

    private static function selesai(?int $id, string $status, \ArrayObject $catatan, ?string $galat = null): void
    {
        if (! $id) {
            return;
        }

        $isi = $catatan->getArrayCopy();
        $jumlah = isset($isi['jumlah']) ? (int) $isi['jumlah'] : null;
        unset($isi['jumlah']);
        $ringkasan = $isi ? json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
        if ($ringkasan !== null && strlen($ringkasan) > 100000) {
            $ringkasan = json_encode(['terpotong' => true, 'bagian' => array_keys($isi)]);
        }

        try {
            DB::table(self::TABEL)->where('Id_Operasi', $id)->update([
                'Selesai_Utc' => DB::raw('SYSUTCDATETIME()'),
                'Status' => $status,
                'Jumlah' => $jumlah,
                'Ringkasan' => $ringkasan,
                'Galat' => $galat !== null ? Str::limit($galat, 990, '') : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[SINKRON] jejak operator #{$id} tidak bisa ditutup: ".$e->getMessage());
        }
    }
}
