<?php

namespace App\Console\Commands;

use App\Support\Sinkron\Siklus;
use Illuminate\Console\Command;

/**
 * Sync Worker dua zona sebagai proses — untuk Cloud Run worker pool / VM
 * tanpa IP publik (selalu menyala), atau sekali jalan untuk uji.
 *
 *   php artisan sinkron:jalan            putaran terus-menerus
 *   php artisan sinkron:jalan --sekali   satu putaran lalu keluar
 *
 * Di Cloud Run biasa tidak perlu: Cloud Scheduler memicu putaran yang sama
 * tiap menit lewat POST /api/tugas/sinkron.
 */
class SinkronJalan extends Command
{
    protected $signature = 'sinkron:jalan
        {--sekali : Satu putaran lalu keluar}
        {--detik-tarik=20 : Lama menarik Pub/Sub per putaran}
        {--jeda=5 : Jeda (detik) antar putaran bila tidak ada yang dikerjakan}';

    protected $description = 'Sync Worker dua zona: tarik Pub/Sub → kotak masuk → proses → potret & salinan → dorong ke database publik';

    private bool $berhenti = false;

    public function handle(): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->berhenti = true);
            pcntl_signal(SIGINT, fn () => $this->berhenti = true);
        }

        do {
            $r = Siklus::jalan((int) $this->option('detik-tarik'));
            $this->line(now()->format('H:i:s').'  '.json_encode($r, JSON_UNESCAPED_UNICODE));

            if ($this->option('sekali')) {
                break;
            }

            $sibuk = ($r['tarik']['ditarik'] ?? 0) + ($r['proses']['diproses'] ?? 0) + ($r['dorong']['diklaim'] ?? 0) > 0;
            if (! $sibuk && ! $this->berhenti) {
                sleep(max(1, (int) $this->option('jeda')));
            }
        } while (! $this->berhenti);

        return self::SUCCESS;
    }
}
