<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Support\Career\GcsBerkas;
use App\Support\Career\LamaranService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — proses pendaftaran (apply-form) secara ASINKRON.
 *
 * Queue: 'wc-applyform'. Local → driver default (database) saat `queue:work`.
 * Non-local → otomatis connection 'cloudtasks' dengan nama queue yang sama.
 *
 * CATATAN GAMBAR/BERKAS: file TIDAK dititip sebagai base64 (kena batas ukuran/chunk).
 * Berkas sudah di-stream ke GCS saat REQUEST; payload hanya menyimpan PATH-nya.
 * Job ini murni menulis DB, lalu:
 *  - Bila insert DB gagal → seluruh berkas GCS dihapus (tidak ada berkas yatim).
 *  - Jadi: file & DB dua-duanya ada, atau dua-duanya tidak ada.
 */
class WcApplyFormJob implements ShouldQueue, ShouldBeUnique
{
    use CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-applyform';

    public $timeout = 300;

    public $tries = 3;

    public $backoff = 30;

    public $uniqueFor = 3600;

    protected string $processId;

    public function __construct(string $processId)
    {
        $this->processId = $processId;

        // Non-local → cloudtasks queue 'wc-applyform'. Local → koneksi 'webcareers'
        // → antrean N_WEB_CAREERS_Jobs (TERPISAH dari N_LMS_Jobs).
        // Worker lokal: `php artisan queue:work webcareers`.
        if (env('QUEUE_CONNECTION') === 'cloudtasks') {
            $this->onConnection('cloudtasks')->onQueue(self::QUEUE);
        } else {
            $this->onConnection('webcareers');
        }
    }

    public function uniqueId(): string
    {
        return self::QUEUE . '-' . $this->processId;
    }

    public function handle(LamaranService $svc, GcsBerkas $gcs): void
    {
        $row = DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->first();
        if (! $row) {
            Log::channel('web_career')->warning("[APPLY] payload {$this->processId} tidak ditemukan.");

            return;
        }
        if ($row->Status === 'SELESAI') {
            return; // idempoten
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        // Berkas hanya berisi METADATA + PATH GCS (sudah terunggah saat request).
        $berkasSiap = json_decode($row->Berkas_Json ?: '[]', true) ?: [];
        $terunggah = array_column($berkasSiap, 'path'); // untuk kompensasi bila DB gagal

        try {
            // ── INSERT DB (lamaran + tahap + pengisian + berkas) ATOMIK ──
            $lamaranId = DB::transaction(function () use ($svc, $payload, $berkasSiap, $row) {
                $hasil = $svc->buatLamaran(
                    (int) $payload['userId'],
                    (int) $payload['pembukaanId'],
                    (int) $payload['posisiId'],
                    null,
                    $payload['gugurAlasan'] ?? null,
                    $payload['jawaban'] ?? null,
                );

                if (! ($hasil['ok'] ?? false)) {
                    throw new \RuntimeException($hasil['pesan'] ?? 'Gagal membuat lamaran.');
                }
                $lamaranId = $hasil['lamaranId'];

                // Berkas ditautkan ke pengisian formulir PENDAFTARAN (tahap 1).
                $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Lamaran_Id', $lamaranId)
                    ->where('Sumber', 'PENDAFTARAN')
                    ->orderBy('Id_Formulir_Pengisian')
                    ->first();

                if ($pengisian && $berkasSiap) {
                    $now = now();
                    foreach ($berkasSiap as $i => $b) {
                        DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                            'Formulir_Pengisian_Id' => $pengisian->Id_Formulir_Pengisian,
                            'Id_Users' => (int) $payload['userId'],
                            'Field_Key' => $b['field'],
                            'Urutan' => $i + 1,
                            'Nama_Asli' => $b['nama'],
                            'Path_File' => $b['path'],
                            'Ukuran_Byte' => $b['ukuran'],
                            'Mime' => $b['mime'],
                            'Ekstensi' => $b['ext'],
                            'Hash_File' => $b['hash'],
                            'Status_Verifikasi' => 'BELUM',
                            'Waktu_Unggah' => $now,
                            'Created_At' => $now, 'Created_By' => $row->Nama_Kandidat, 'Created_By_Id' => (int) $payload['userId'],
                            'Updated_At' => $now, 'Updated_By' => $row->Nama_Kandidat, 'Updated_By_Id' => (int) $payload['userId'],
                        ]);
                    }
                }

                return $lamaranId;
            });

            // ── 3. SUKSES → tandai payload selesai, kosongkan base64 (hemat ruang) ──
            DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                'Status' => 'SELESAI',
                'Lamaran_Id' => $lamaranId,
                'Berkas_Json' => null,
                'Waktu_Proses' => now(),
                'Updated_At' => now(),
            ]);

            Log::channel('web_career')->info("[APPLY] {$this->processId} selesai — lamaran #{$lamaranId}, " . count($terunggah) . ' berkas.');
        } catch (\Throwable $e) {
            // Kompensasi: hapus berkas yang sudah terunggah (tidak boleh ada yatim).
            $gcs->hapus($terunggah);

            DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                'Status' => 'GAGAL',
                'Percobaan' => ($row->Percobaan ?? 0) + 1,
                'Pesan_Error' => substr($e->getMessage(), 0, 480),
                'Waktu_Proses' => now(),
                'Updated_At' => now(),
            ]);

            Log::channel('web_career')->error("[APPLY] {$this->processId} GAGAL: " . $e->getMessage());

            // Percobaan terakhir → catat ke N_WEB_CAREERS_Failed_Jobs & SELESAI.
            // Sengaja TIDAK throw agar kegagalan tak masuk N_LMS_Failed_Jobs global.
            if ($this->attempts() >= $this->tries) {
                $this->catatGagalWc('APPLYFORM', json_encode(['processId' => $this->processId]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }
    }
}
