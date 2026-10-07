<?php

namespace App\Support\Sinkron;

use App\Support\Sinkron\Potret\PenyapuPotret;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SATU PUTARAN SYNC WORKER — dipanggil tick Cloud Scheduler (job
 * WcSinkronJob), `php artisan sinkron:jalan`, atau langsung.
 *
 *   1. tarik     Pub/Sub → kotak masuk (bila langganan TARIK diatur)
 *   2. proses    kotak masuk jatuh tempo → kode admin
 *   3. potret    perubahan lamaran → Sinkron_Keluar (Portal.Lamaran, Dokumen)
 *   4. salinan   master admin → salinan publik
 *   5. dorong    Sinkron_Keluar → database & bucket publik
 *   6. retensi   masa simpan jejak & tabel sinkron (±sekali sehari per kebijakan)
 *
 * Langkah yang gagal tidak menahan langkah lain; satu putaran pada satu
 * waktu (kunci cache), jadi tick yang tumpang-tindih tidak saling injak.
 */
final class Siklus
{
    /** @return array<string, mixed> ringkasan per langkah */
    public static function jalan(?int $detikTarik = null, bool $tarik = true): array
    {
        $kunci = Cache::lock('wc-sinkron:siklus', 300);
        if (! $kunci->get()) {
            return ['dilewati' => 'Putaran lain sedang berjalan.'];
        }

        $mulai = microtime(true);
        $hasil = [];

        try {
            if ($tarik && self::tarikDiatur()) {
                $hasil['tarik'] = self::langkah(fn () => app(PenarikPesan::class)->tarik($detikTarik ?? (int) config('sinkron.tick_detik_tarik', 20)));
            }
            $hasil['proses'] = self::langkah(fn () => app(PemrosesMasuk::class)->prosesJatuhTempo());
            $hasil['potret'] = self::langkah(fn () => app(PenyapuPotret::class)->sapu(25));
            $hasil['salinan'] = self::langkah(fn () => app(PenyapuSalinan::class)->sapu(false, 40));
            $hasil['dorong'] = self::langkah(fn () => app(PendorongKeluar::class)->dorongHabis(60));
            if ($r = self::langkah(fn () => Retensi::jalankan())) {
                $hasil['retensi'] = $r;
            }
        } finally {
            $kunci->release();
        }

        $hasil['detik'] = round(microtime(true) - $mulai, 1);

        return $hasil;
    }

    /** Proses satu akun lalu dorong kabarnya — jalur cepat sesudah pesan diterima. */
    public static function kunci(string $kunciUrut): array
    {
        $r = ['proses' => self::langkah(fn () => app(PemrosesMasuk::class)->prosesKunci($kunciUrut))];
        $r['dorong'] = self::langkah(fn () => app(PendorongKeluar::class)->dorongHabis(30));

        return $r;
    }

    /** Langganan TARIK diatur — bila dorong aktif, Google yang mengantar (tarik akan ditolak). */
    private static function tarikDiatur(): bool
    {
        return filled(config('sinkron.pubsub.project')) && filled(config('sinkron.pubsub.langganan')) && ! PenjagaDorong::aktif();
    }

    private static function langkah(\Closure $kerja): array
    {
        try {
            return (array) $kerja();
        } catch (\Throwable $e) {
            Log::error('[SINKRON] langkah putaran gagal: '.$e->getMessage());

            return ['galat' => $e->getMessage()];
        }
    }
}
