<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Support\Sinkron\Siklus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * TICK SYNC WORKER — satu putaran sinkron dua zona (lihat Siklus).
 *
 * Dipicu Cloud Scheduler tiap menit lewat POST /api/tugas/sinkron, lalu
 * dijalankan antrean 'wc-sinkron'. Tidak diulang bila gagal: tick berikutnya
 * melanjutkan dari keadaan di database (kemajuan tidak disimpan di antrean).
 */
class WcSinkronJob implements ShouldQueue, ShouldBeUnique
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-sinkron';

    public $timeout = 280;

    public $tries = 1;

    /** Tick yang datang selagi putaran sebelumnya masih antre/jalan dilewati. */
    public $uniqueFor = 240;

    public function __construct()
    {
        $this->aturAntrean(self::QUEUE);
    }

    /** Dipanggil PemicuTugasController: setiap tick memang jamnya. */
    public static function dariPemicu(): ?static
    {
        return new static;
    }

    public function uniqueId(): string
    {
        return self::QUEUE.'-tick';
    }

    public function handle(): void
    {
        $r = Siklus::jalan();

        Log::info('[SINKRON] tick', $r);
    }
}
