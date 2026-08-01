<?php

namespace App\Jobs\Career;

use App\Mail\Career\UndanganJadwalMail;
use App\Jobs\Career\Concerns\AntreanWebCareers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * WEB CAREER — undangan JADWAL wawancara / tes tatap muka.
 *
 * Lewat antrean karena pengiriman SMTP bisa memakan beberapa detik, sementara
 * rekruter menunggu modalnya tertutup. Jadwalnya sendiri sudah tersimpan
 * sebelum job ini dijalankan, jadi kegagalan email tidak pernah membuat
 * kandidat kehilangan jadwalnya — ia tetap terlihat di portal.
 */
class WcJadwalEmailJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Ikut antrean email yang sudah ada — sifat kerjanya sama: kirim SMTP. */
    public const QUEUE = 'wc-applymail';

    public $tries = 3;

    public $backoff = 30;

    protected int $userId;

    protected array $data;

    public function __construct(int $userId, array $data)
    {
        $this->userId = $userId;
        $this->data = $data;

        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $email = $this->data['email'] ?? null;
        if (! $email) {
            Log::channel('web_career')->warning("[JADWAL] user #{$this->userId} tanpa email — undangan dilewati.");

            return;
        }

        try {
            Mail::to($email)->send(new UndanganJadwalMail($this->data));

            Log::channel('web_career')->info(
                "[JADWAL] undangan {$this->data['aktivitas']} terkirim ke {$email}."
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                "[JADWAL] undangan ke {$email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage()
            );

            throw $e;
        }
    }
}
