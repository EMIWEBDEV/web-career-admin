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

/**
 * JALUR CEPAT — proses peristiwa SATU akun begitu pesannya masuk kotak masuk
 * (dari langganan dorong atau penarikan), lalu dorong kabar baliknya.
 *
 * Tanpa job ini peristiwanya tetap diproses tick berikutnya; job ini hanya
 * memangkas jedanya dari ±1 menit menjadi beberapa detik. Antrean 'wc-sinkron'.
 */
class WcSinkronMasukJob implements ShouldQueue, ShouldBeUnique
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-sinkron';

    public $timeout = 120;

    public $tries = 1;

    public $uniqueFor = 30;

    public function __construct(public string $kunciUrut)
    {
        $this->aturAntrean(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return self::QUEUE.'-'.$this->kunciUrut;
    }

    public function handle(): void
    {
        Siklus::kunci($this->kunciUrut);
    }
}
