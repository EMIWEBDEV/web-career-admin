<?php

namespace App\Support\Sinkron;

use Google\Cloud\Storage\StorageClient;
use RuntimeException;

/**
 * Penyalin objek ANTAR-BUCKET di sisi server Google (rewrite) — berkasnya
 * tidak pernah lewat memori aplikasi.
 *
 *   karantina → admin    berkas kandidat yang menyeberang (CV, berkas formulir,
 *                        berkas aktivitas). Path-nya dipertahankan: struktur
 *                        folder kedua zona sama (apply-form/…, formulir-tahap/…).
 *   admin → publik       dokumen untuk kandidat (surat pengantar, berkas hasil
 *                        tahap) dan salinan bersih berkas kandidat sendiri.
 *
 * Akun layanan zona dalam butuh: baca karantina, tulis bucket admin, tulis
 * bucket publik. Zona luar tidak pernah bisa membaca bucket admin.
 */
final class SalinBerkas
{
    private static ?StorageClient $klien = null;

    public static function bucketAdmin(): string
    {
        return (string) config('filesystems.disks.gcs.bucket');
    }

    public static function bucketKarantina(): string
    {
        return (string) config('sinkron.bucket.karantina');
    }

    public static function bucketPublik(): string
    {
        return (string) config('sinkron.bucket.publik');
    }

    /** Karantina → bucket admin, path sama. Mengembalikan path di bucket admin. */
    public static function dariKarantina(string $path): string
    {
        $path = self::rapikan($path);
        self::salin(self::bucketKarantina(), $path, self::bucketAdmin(), $path);

        return $path;
    }

    /** Bucket admin → bucket publik. */
    public static function kePublik(string $pathAdmin, string $pathPublik): void
    {
        self::salin(self::bucketAdmin(), self::rapikan($pathAdmin), self::bucketPublik(), self::rapikan($pathPublik));
    }

    public static function adaDiPublik(string $path): bool
    {
        return self::klien()->bucket(self::bucketPublik())->object(self::rapikan($path))->exists();
    }

    public static function hapusDiPublik(string $path): void
    {
        $o = self::klien()->bucket(self::bucketPublik())->object(self::rapikan($path));
        if ($o->exists()) {
            $o->delete();
        }
    }

    private static function salin(string $bucketAsal, string $asal, string $bucketTujuan, string $tujuan): void
    {
        if ($asal === '' || $tujuan === '') {
            throw new RuntimeException('Path berkas kosong.');
        }

        $obj = self::klien()->bucket($bucketAsal)->object($asal);
        if (! $obj->exists()) {
            throw new RuntimeException("Berkas {$bucketAsal}/{$asal} tidak ditemukan.");
        }

        $obj->copy($bucketTujuan, ['name' => $tujuan]);
    }

    /** Path objek tanpa garis miring di depan; ".." ditolak. */
    private static function rapikan(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_contains($path, '..')) {
            throw new RuntimeException('Path berkas tidak sah.');
        }

        return $path;
    }

    private static function klien(): StorageClient
    {
        if (self::$klien) {
            return self::$klien;
        }

        $cfg = ['projectId' => config('filesystems.disks.gcs.project_id')];
        $berkas = config('filesystems.disks.gcs.key_file_path');
        if ($berkas && is_file($berkas)) {
            $cfg['keyFilePath'] = $berkas;
        }

        return self::$klien = new StorageClient($cfg);
    }
}
