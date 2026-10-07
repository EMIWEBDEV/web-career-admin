<?php

namespace App\Support\Sinkron;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Pembuka bagian RAHASIA muatan peristiwa (token verifikasi surel, kode reset
 * sandi) yang dibungkus situs kandidat — pasangan RahasiaSinkron::bungkus()
 * di project pengguna. Kuncinya SINKRON_KUNCI_RAHASIA, sama di kedua zona.
 *
 * Bungkusnya baru dibuka di job surel (WcSyncEmailJob), sesaat sebelum
 * dikirim: kotak masuk, antrean, dan log hanya pernah memegang sandiannya.
 */
final class RahasiaSinkron
{
    /** @return array<string, mixed> */
    public static function buka(string $bungkus): array
    {
        $isi = json_decode(self::enkriptor()->decryptString($bungkus), true);
        if (! is_array($isi)) {
            throw new RuntimeException('Isi rahasia sinkron tidak terbaca.');
        }

        return $isi;
    }

    private static function enkriptor(): Encrypter
    {
        $kunci = (string) config('sinkron.kunci_rahasia');
        if (str_starts_with($kunci, 'base64:')) {
            $kunci = (string) base64_decode(substr($kunci, 7), true);
        }
        if (strlen($kunci) !== 32) {
            throw new RuntimeException('SINKRON_KUNCI_RAHASIA belum diatur (base64:<32 byte>).');
        }

        return new Encrypter($kunci, 'AES-256-CBC');
    }
}
