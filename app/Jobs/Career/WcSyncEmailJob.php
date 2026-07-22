<?php

namespace App\Jobs\Career;

use App\Mail\Career\VerifikasiEmailMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * WEB CAREER — pengiriman email kandidat secara ASINKRON.
 *
 * Queue: 'wc-syncemailjob'. Local → driver default (database) saat `php artisan
 * queue:work` polos. Non-local → otomatis connection 'cloudtasks' dengan nama
 * queue yang sama (buat dulu: `gcloud tasks queues create wc-syncemailjob`).
 *
 * Jenis email dibuat extensible lewat $jenis; saat ini baru VERIFIKASI.
 * Token verifikasi ASLI hanya lewat payload job ini — DB cuma menyimpan hash.
 */
class WcSyncEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-syncemailjob';

    public const JENIS_VERIFIKASI = 'VERIFIKASI';

    public $timeout = 120;

    public $tries = 3;

    public $backoff = 30;

    protected string $jenis;

    protected int $userId;

    /** Data tambahan per jenis (VERIFIKASI: ['token' => tokenAsli]). */
    protected array $data;

    public function __construct(string $jenis, int $userId, array $data = [])
    {
        $this->jenis = $jenis;
        $this->userId = $userId;
        $this->data = $data;

        // Local (database) → biarkan di queue 'default' supaya `php artisan
        // queue:work` polos langsung memprosesnya. Non-local → cloudtasks.
        if (env('QUEUE_CONNECTION') === 'cloudtasks') {
            $this->onConnection('cloudtasks')->onQueue(self::QUEUE);
        }
    }

    public function handle(): void
    {
        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        if (! $user) {
            Log::channel('web_career')->warning("[EMAIL] user #{$this->userId} tidak ditemukan — email {$this->jenis} dilewati.");

            return;
        }

        try {
            match ($this->jenis) {
                self::JENIS_VERIFIKASI => $this->kirimVerifikasi($user),
                default => Log::channel('web_career')->warning("[EMAIL] jenis '{$this->jenis}' belum dikenal — dilewati."),
            };
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[EMAIL] {$this->jenis} ke {$user->Email} GAGAL: " . $e->getMessage());

            throw $e; // biar retry (tries) berjalan
        }
    }

    protected function kirimVerifikasi(object $user): void
    {
        // Sudah terverifikasi (mis. klik tautan sebelum retry) → tak perlu kirim.
        if (($user->Flag_Email_Verified ?? 'T') === 'Y') {
            return;
        }

        $token = (string) ($this->data['token'] ?? '');
        if ($token === '') {
            Log::channel('web_career')->warning("[EMAIL] verifikasi #{$this->userId} tanpa token — dilewati.");

            return;
        }

        $verifUrl = rtrim(config('app.url'), '/') . '/verifikasi-email?' . http_build_query([
            'email' => $user->Email,
            'token' => $token,
        ]);

        Mail::to($user->Email)->send(new VerifikasiEmailMail($user->Nama, $verifUrl));

        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Email_Verif_Sent_At' => now(),
            'Updated_At' => now(),
        ]);

        Log::channel('web_career')->info("[EMAIL] verifikasi terkirim ke {$user->Email} (user #{$this->userId}).");
    }
}
