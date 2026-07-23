<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Mail\Career\ResetOtpMail;
use App\Mail\Career\ResetSelesaiMail;
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
 * Local → driver default (database) saat `php artisan queue:work` polos.
 * Non-local (Cloud Run) → connection 'cloudtasks', queue 'wc-syncemailjob'.
 * Queue itu HARUS sudah dibuat di Cloud Tasks: `gcloud tasks queues create wc-syncemailjob`.
 *
 * Jenis email dibuat extensible lewat $jenis; saat ini baru VERIFIKASI.
 * Token verifikasi ASLI hanya lewat payload job ini — DB cuma menyimpan hash.
 */
class WcSyncEmailJob implements ShouldQueue
{
    use CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-syncemailjob';

    public const JENIS_VERIFIKASI = 'VERIFIKASI';

    public const JENIS_RESET_OTP = 'RESET_OTP';

    public const JENIS_RESET_SELESAI = 'RESET_SELESAI';

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

        // Pemilihan koneksi antrean berdasarkan QUEUE_CONNECTION aktif:
        //  - cloudtasks → Cloud Tasks, queue 'wc-syncemailjob' (harus sudah dibuat).
        //  - sync       → kirim LANGSUNG saat request (dev lokal; tanpa worker/tunnel).
        //  - lainnya (mis. database) → koneksi 'webcareers' → antrean
        //    N_WEB_CAREERS_Jobs (TERPISAH dari N_LMS_Jobs). Worker: `php artisan queue:work webcareers`.
        $conn = config('queue.default');
        if ($conn === 'cloudtasks') {
            $this->onConnection('cloudtasks')->onQueue(self::QUEUE);
        } elseif ($conn === 'sync') {
            $this->onConnection('sync');
        } else {
            $this->onConnection('webcareers');
        }
    }

    public function handle(): void
    {
        // Log ke channel default (stack → stderr) supaya TERLIHAT di Cloud Run /
        // log-viewer. File 'web_career' bersifat ephemeral di Cloud Run.
        Log::info("[EMAIL] job {$this->jenis} MULAI diproses untuk user #{$this->userId}.");

        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        if (! $user) {
            Log::warning("[EMAIL] user #{$this->userId} tidak ditemukan — email {$this->jenis} dilewati.");

            return;
        }

        try {
            match ($this->jenis) {
                self::JENIS_VERIFIKASI => $this->kirimVerifikasi($user),
                self::JENIS_RESET_OTP => $this->kirimResetOtp($user),
                self::JENIS_RESET_SELESAI => $this->kirimResetSelesai($user),
                default => Log::warning("[EMAIL] jenis '{$this->jenis}' belum dikenal — dilewati."),
            };
        } catch (\Throwable $e) {
            Log::error("[EMAIL] {$this->jenis} ke {$user->Email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage());
            Log::channel('web_career')->error("[EMAIL] {$this->jenis} ke {$user->Email} GAGAL: " . $e->getMessage());

            // Percobaan terakhir → catat ke N_WEB_CAREERS_Failed_Jobs & SELESAI.
            // Sengaja TIDAK throw agar kegagalan tak masuk N_LMS_Failed_Jobs global.
            if ($this->attempts() >= $this->tries) {
                $this->catatGagalWc('EMAIL:' . $this->jenis, json_encode(['userId' => $this->userId, 'jenis' => $this->jenis]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
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

        Log::info("[EMAIL] verifikasi terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] verifikasi terkirim ke {$user->Email} (user #{$this->userId}).");
    }

    /**
     * Kirim email OTP reset kata sandi. OTP ASLI hanya lewat payload job ini —
     * DB cuma menyimpan hash. Guard: bila OTP sudah terpakai (hash di-null-kan
     * saat sukses reset), jangan kirim OTP basi. `Reset_Otp_Sent_At` diset di
     * SINI (setelah kirim) agar cooldown baru berjalan begitu email keluar.
     */
    protected function kirimResetOtp(object $user): void
    {
        // OTP sudah dipakai/dihanguskan sebelum job jalan → tak perlu kirim.
        if (empty($user->Reset_Otp_Hash)) {
            return;
        }

        $otp = (string) ($this->data['otp'] ?? '');
        if ($otp === '') {
            Log::channel('web_career')->warning("[EMAIL] reset OTP #{$this->userId} tanpa kode — dilewati.");

            return;
        }

        $menit = (int) ($this->data['menit'] ?? 10);

        Mail::to($user->Email)->send(new ResetOtpMail($user->Nama, $otp, $menit));

        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->update([
            'Reset_Otp_Sent_At' => now(),
            'Updated_At' => now(),
        ]);

        // OTP TIDAK PERNAH di-log.
        Log::info("[EMAIL] OTP reset terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] OTP reset terkirim ke {$user->Email} (user #{$this->userId}).");
    }

    /** Kirim email pemberitahuan bahwa kata sandi berhasil diubah (tanpa rahasia). */
    protected function kirimResetSelesai(object $user): void
    {
        Mail::to($user->Email)->send(new ResetSelesaiMail($user->Nama));

        Log::info("[EMAIL] notifikasi ganti sandi terkirim ke {$user->Email} (user #{$this->userId}).");
        Log::channel('web_career')->info("[EMAIL] notifikasi ganti sandi terkirim ke {$user->Email} (user #{$this->userId}).");
    }
}
