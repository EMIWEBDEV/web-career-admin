<?php

namespace App\Support\Sinkron;

use Google\Auth\AccessToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pemeriksa token OIDC langganan DORONG (Pub/Sub push dengan autentikasi).
 *
 * Google menandatangani setiap permintaan dorong dengan token identitas
 * akun layanan yang dipilih di setelan langganan. Yang diterima hanya token
 * yang: bertanda tangan Google (sertifikat publiknya disimpan sejam),
 * ditujukan ke audience kita, belum kedaluwarsa, dan dikeluarkan untuk akun
 * layanan yang kita sebut di PUBSUB_DORONG_AKUN — bukan sekadar "token
 * Google apa saja".
 */
final class PenjagaDorong
{
    private const SERTIFIKAT = 'https://www.googleapis.com/oauth2/v3/certs';

    public static function aktif(): bool
    {
        return filled(config('sinkron.dorong.audience')) && filled(config('sinkron.dorong.akun'));
    }

    /** Alasan penolakan, atau null bila token sah. */
    public static function periksa(?string $authorization): ?string
    {
        if (! $authorization || ! preg_match('/^Bearer\s+(\S+)$/i', $authorization, $m)) {
            return 'tanpa token';
        }

        try {
            $isi = (new AccessToken)->verify($m[1], [
                'audience' => (string) config('sinkron.dorong.audience'),
                'certsLocation' => self::berkasSertifikat(),
                'throwException' => true,
            ]);
        } catch (\Throwable $e) {
            return 'token tidak sah: '.$e->getMessage();
        }

        if (! is_array($isi)) {
            return 'token tidak sah';
        }
        if (! in_array($isi['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return 'penerbit token bukan Google';
        }
        if (empty($isi['email_verified']) || strcasecmp((string) ($isi['email'] ?? ''), (string) config('sinkron.dorong.akun')) !== 0) {
            return 'akun layanan token tidak cocok';
        }

        return null;
    }

    /** Sertifikat Google (JWK) disimpan lokal — tanpa unduhan di setiap dorongan. */
    private static function berkasSertifikat(): string
    {
        $berkas = storage_path('framework/cache/google-oidc-certs.json');
        $segar = is_file($berkas) && filemtime($berkas) > time() - 3600;
        if (! $segar) {
            $json = Cache::remember('sinkron:google-oidc-certs', 3600, fn () => Http::timeout(10)->get(self::SERTIFIKAT)->throw()->body());
            @mkdir(dirname($berkas), 0775, true);
            file_put_contents($berkas, $json, LOCK_EX);
        }

        return $berkas;
    }
}
