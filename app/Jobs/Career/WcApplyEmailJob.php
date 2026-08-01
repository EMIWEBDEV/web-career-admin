<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Mail\Career\HasilLamaranMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * WEB CAREER — email HASIL LAMARAN (setelah apply) secara ASINKRON.
 *
 * QUEUE TERPISAH 'wc-applymail' — SENGAJA dipisah dari:
 *   - 'wc-syncemailjob' (email verifikasi akun), dan
 *   - 'wc-applyform'    (proses berat apply: tulis DB + berkas GCS).
 * Tujuannya anti-bottleneck: proses apply selesai cepat, dan pengiriman email
 * (SMTP bisa lambat/timeout) tidak menahan antrean apply maupun verifikasi.
 * Ketiganya bisa diskalakan / retry sendiri-sendiri.
 *
 * Buat queue di Cloud Tasks: `gcloud tasks queues create wc-applymail`.
 *
 * Status: LOLOS (lolos syarat otomatis), GUGUR (auto-gugur), MENUNGGU (butuh admin).
 */
class WcApplyEmailJob implements ShouldQueue
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-applymail';

    public $timeout = 120;

    public $tries = 3;

    public $backoff = 30;

    protected int $userId;

    protected string $status;

    /** ['kode' => .., 'posisi' => .., 'program' => ..]. */
    protected array $data;

    public function __construct(int $userId, string $status, array $data = [])
    {
        $this->userId = $userId;
        $this->status = $status;
        $this->data = $data;

        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        Log::info("[APPLYMAIL] job hasil '{$this->status}' MULAI untuk user #{$this->userId}.");

        $user = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $this->userId)->first();
        if (! $user || ! $user->Email) {
            Log::warning("[APPLYMAIL] user #{$this->userId} / email tidak ada — dilewati.");

            return;
        }

        // Ambil foto verifikasi dari GCS (PATH ada di payload; bytes TIDAK pernah
        // lewat queue). Gagal ambil → email tetap terkirim tanpa foto (placeholder).
        $fotoData = null;
        $fotoPath = $this->data['fotoPath'] ?? null;
        if ($fotoPath) {
            try {
                $fotoData = Storage::disk('gcs')->get($fotoPath);
            } catch (\Throwable $e) {
                Log::warning('[APPLYMAIL] foto verifikasi gagal diambil dari GCS: ' . $e->getMessage());
            }
        }

        try {
            Mail::to($user->Email)->send(new HasilLamaranMail(
                $user->Nama,
                $this->status,
                $this->data['kode'] ?? null,
                $this->data['posisi'] ?? null,
                $this->data['program'] ?? null,
                [
                    'lolos' => $this->data['tahapLolos'] ?? null,
                    'berikut' => $this->data['tahapBerikut'] ?? null,
                    'urutan' => $this->data['urutan'] ?? null,
                    'total' => $this->data['total'] ?? null,
                    'diterima' => $this->data['diterima'] ?? false,
                ],
                [
                    'email' => $user->Email,
                    'tglLahir' => $this->data['tglLahir'] ?? null,
                    'jkel' => $this->data['jkel'] ?? null,
                    'kampus' => $this->data['kampus'] ?? null,
                    // Nomor dari formulir yang dipakai; bila kosong, jatuh ke
                    // nomor akun supaya kartu datanya tidak berlubang.
                    'hp' => $this->data['hp'] ?? $user->No_Hp ?? null,
                    'foto' => $fotoData,
                ],
                $this->data['feedbackUrl'] ?? null, // [feat/feedback]
            ));

            Log::info("[APPLYMAIL] hasil '{$this->status}' terkirim ke {$user->Email} (user #{$this->userId}, kode " . ($this->data['kode'] ?? '-') . ').');
            Log::channel('web_career')->info("[APPLYMAIL] hasil '{$this->status}' terkirim ke {$user->Email}.");
        } catch (\Throwable $e) {
            Log::error("[APPLYMAIL] hasil '{$this->status}' ke {$user->Email} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage());

            // Percobaan terakhir → catat ke tabel WC & selesai (tak masuk N_LMS_Failed_Jobs).
            if ($this->attempts() >= $this->tries) {
                $this->catatGagalWc('APPLYMAIL:' . $this->status, json_encode(['userId' => $this->userId, 'kode' => $this->data['kode'] ?? null]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }
    }
}
