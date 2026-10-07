<?php

namespace App\Support\Sinkron;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Token akses Google untuk Pub/Sub (REST).
 *
 * Di Cloud Run memakai akun layanan service-nya (metadata server); di lokal
 * memakai berkas kunci GOOGLE_CLOUD_KEY_FILE_PATH bila ada. Token disimpan di
 * cache sampai lima menit sebelum kedaluwarsa.
 */
final class TokenGoogle
{
    private const LINGKUP = ['https://www.googleapis.com/auth/pubsub'];

    private const KUNCI_CACHE = 'sinkron:google:token-pubsub';

    public static function pubsub(): string
    {
        $simpan = Cache::get(self::KUNCI_CACHE);
        if (is_string($simpan) && $simpan !== '') {
            return $simpan;
        }

        $berkas = config('filesystems.disks.gcs.key_file_path');
        $kred = $berkas && is_file($berkas)
            ? new ServiceAccountCredentials(self::LINGKUP, $berkas)
            : ApplicationDefaultCredentials::getCredentials(self::LINGKUP);

        $t = $kred->fetchAuthToken();
        $akses = (string) ($t['access_token'] ?? '');
        if ($akses === '') {
            throw new RuntimeException('Token Google untuk Pub/Sub tidak didapat.');
        }

        Cache::put(self::KUNCI_CACHE, $akses, max(60, (int) ($t['expires_in'] ?? 3600) - 300));

        return $akses;
    }

    /** Buang token tersimpan — dipanggil sesudah Pub/Sub menjawab 401. */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }
}
