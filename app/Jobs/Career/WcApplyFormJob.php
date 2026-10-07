<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Jobs\Career\Concerns\CatatGagalWebCareers;
use App\Support\Career\GcsBerkas;
use App\Support\Career\LamaranService;
use App\Support\Career\TerimaLamaran;
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
 *  - Bila insert DB gagal pada percobaan TERAKHIR → seluruh berkas GCS dihapus
 *    (tidak ada berkas yatim). Percobaan antara TIDAK menghapus apa pun: yang
 *    berikutnya masih membutuhkan berkas itu.
 *  - Begitu transaksinya selesai, tidak ada lagi yang boleh menghapus berkas.
 *  - Jadi: file & DB dua-duanya ada, atau dua-duanya tidak ada.
 */
class WcApplyFormJob implements ShouldQueue, ShouldBeUnique
{
    use AntreanWebCareers, CatatGagalWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'wc-applyform';

    public $timeout = 300;

    public $tries = 3;

    public $backoff = 30;

    public $uniqueFor = 3600;

    protected string $processId;

    public function __construct(string $processId)
    {
        $this->processId = $processId;

        $this->aturAntrean(self::QUEUE);
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

                // Berkas ditautkan ke pengisian formulir PENDAFTARAN (tahap 1) —
                // aturan yang sama dengan peristiwa Lamaran.Dikirim dari situs
                // kandidat. Tanpa pengisian untuk ditempeli → galat (lebih baik
                // gagal & terlihat daripada tuntas tapi kehilangan CV).
                TerimaLamaran::simpanBerkasPendaftaran($lamaranId, (int) $payload['userId'], $berkasSiap, $row->Nama_Kandidat);

                // SELESAI ditulis DI DALAM transaksi yang sama. Kalau ditulis
                // sesudahnya lalu gagal, lamarannya sudah ada tetapi payload-nya
                // tidak berkata begitu — percobaan berikutnya mengulang dari awal,
                // ditolak "sudah melamar", dan pembersihannya menghapus berkas
                // yang sedang dipakai lamaran yang sah.
                DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                    'Status' => 'SELESAI',
                    'Lamaran_Id' => $lamaranId,
                    'Berkas_Json' => null,
                    'Waktu_Proses' => now(),
                    'Updated_At' => now(),
                ]);

                return $lamaranId;
            });
        } catch (\Throwable $e) {
            $terakhir = $this->attempts() >= $this->tries;

            // Kompensasi HANYA pada percobaan terakhir. Dulu berkas dihapus di
            // setiap kegagalan — termasuk yang masih akan diulang — sehingga
            // percobaan berikutnya yang BERHASIL mencatat baris Formulir_Berkas
            // yang menunjuk objek yang sudah tidak ada: CV tercatat, tapi 404.
            if ($terakhir) {
                $gcs->hapus($terunggah);
            }

            DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $this->processId)->update([
                // Selama masih akan diulang, statusnya tetap MENUNGGU: layar
                // kandidat berhenti memantau di GAGAL pertama, dan kandidat yang
                // melihat "gagal" mengirim lamaran kedua sementara yang pertama
                // masih diproses.
                'Status' => $terakhir ? 'GAGAL' : 'MENUNGGU',
                'Percobaan' => ($row->Percobaan ?? 0) + 1,
                'Pesan_Error' => substr($e->getMessage(), 0, 480),
                'Waktu_Proses' => now(),
                'Updated_At' => now(),
            ]);

            Log::channel('web_career')->error("[APPLY] {$this->processId} GAGAL (percobaan {$this->attempts()}/{$this->tries}): " . $e->getMessage());

            // Percobaan terakhir → catat ke N_WEB_CAREERS_Failed_Jobs & SELESAI.
            // Sengaja TIDAK throw agar kegagalan tak masuk N_LMS_Failed_Jobs global.
            if ($terakhir) {
                $this->catatGagalWc('APPLYFORM', json_encode(['processId' => $this->processId]), $e);

                return;
            }

            throw $e; // masih ada sisa percobaan → retry
        }

        // ── LAMARAN SUDAH TERSIMPAN ─────────────────────────────────────────
        // Dari sini ke bawah TIDAK ADA yang boleh menghapus berkas atau
        // menggagalkan job. Dulu langkah-langkah ini berada di dalam try yang
        // sama dengan kompensasi di atas: antrean biodata yang menolak membuat
        // seluruh berkas lamaran yang SUDAH tercatat ikut terhapus.

        Log::channel('web_career')->info("[APPLY] {$this->processId} selesai — lamaran #{$lamaranId}, " . count($terunggah) . ' berkas.');

        // Kirim email HASIL ke kandidat lewat QUEUE TERPISAH (wc-applymail) —
        // tidak menahan job apply. Gagal antre email TIDAK menggagalkan apply.
        $this->kirimEmailHasil($lamaranId, (int) $payload['userId']);

        // ── BIODATA → HRIS REKRUTMEN ────────────────────────────────────
        //
        // Formulir pendaftaran inilah yang memuat tanggal lahir, jenis
        // kelamin, dan alamat kandidat — data yang dulu tidak pernah sampai
        // ke HCLearn, karena satu-satunya pengiriman terjadi saat ia
        // MENDAFTAR AKUN, tepat ketika belum mengisi apa pun.
        //
        // Posisi yang dilamar ikut terbawa, dan ikut berubah bila kandidat
        // yang sama melamar posisi lain dengan akun yang sama.
        //
        // Queue tersendiri, best-effort: lamarannya sudah tersimpan, jadi
        // HCLearn yang sedang tumbang tidak boleh menggagalkan apa pun.
        try {
            WcBiodataHrisJob::dispatch((int) $payload['userId'], 'APPLY');
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[APPLY] {$this->processId} biodata HRIS gagal diantrekan: " . $e->getMessage());
        }
    }

    /** Surel hasil lamaran — lihat TerimaLamaran::kirimEmailHasil(). */
    protected function kirimEmailHasil(int $lamaranId, int $userId): void
    {
        TerimaLamaran::kirimEmailHasil($lamaranId, $userId);
    }
}
