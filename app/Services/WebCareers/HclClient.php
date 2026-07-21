<?php

namespace App\Services\WebCareers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREERS → HCLEARN/CAT — client HMAC-SHA512.
 *
 * SEMUA panggilan berjalan di sisi SERVER. Domain, Api_Public, dan Api_Secret tidak
 * pernah dikirim ke browser: Vue memanggil endpoint internal Web Careers, controller
 * internal itu yang memakai client ini. Jangan pernah menembak domain CAT dari axios.
 *
 * Tanda tangan mengikuti App\Http\Middleware\ApiCredentialValidator di project CAT:
 *   stringToSign = METHOD \n path \n query \n timestamp \n nonce \n bodyHash \n publicKey
 *   signature    = hash_hmac('sha512', stringToSign, Api_Secret)
 *
 * CATATAN timestamp: middleware memakai strtotime(), jadi WAJIB berupa tanggal yang
 * bisa diurai (ISO 8601) — bukan angka unix. Toleransi selisih 60 detik.
 */
class HclClient
{
    public function get(string $path, array $query = [], array $konteksLog = []): array
    {
        return $this->kirim('GET', $path, [], $query, $konteksLog);
    }

    public function post(string $path, array $body = [], array $konteksLog = []): array
    {
        return $this->kirim('POST', $path, $body, [], $konteksLog);
    }

    public function put(string $path, array $body = [], array $konteksLog = []): array
    {
        return $this->kirim('PUT', $path, $body, [], $konteksLog);
    }

    public function delete(string $path, array $konteksLog = []): array
    {
        return $this->kirim('DELETE', $path, [], [], $konteksLog);
    }

    public function baseUrl(): string
    {
        $env = config('hclearn.env', 'development');
        $domain = config("hclearn.domains.{$env}");

        if (! $domain) {
            throw new \RuntimeException("Domain HCLearn untuk environment '{$env}' belum diatur.");
        }

        return rtrim($domain, '/');
    }

    /**
     * @return array{sukses:bool, status:int, message:string, result:mixed}
     */
    private function kirim(string $metode, string $path, array $body, array $query, array $konteksLog): array
    {
        $publicKey = config('hclearn.api_public');
        $secret = config('hclearn.api_secret');

        if (! $publicKey || ! $secret) {
            throw new \RuntimeException('Kredensial HCLearn (HCLEARN_WC_API_*) belum diatur di .env.');
        }

        $prefix = trim(config('hclearn.prefix', 'api/v1/web-careers'), '/');
        $jalur = '/' . $prefix . '/' . ltrim($path, '/');
        $url = $this->baseUrl() . $jalur;

        // Body harus string mentah yang SAMA persis dengan yang dikirim, karena
        // server menghitung ulang sha256 dari raw body.
        $rawBody = $body === [] ? '' : json_encode($body);
        $queryString = $query === [] ? '' : http_build_query($query);
        $bodyHash = hash('sha256', $rawBody);

        $maksPercobaan = max(1, (int) config('hclearn.retry', 2));
        $nonce = '';
        $terakhir = null;

        // Retry ditangani MANUAL, bukan lewat Http::retry(). Setiap percobaan WAJIB
        // memakai nonce + timestamp + tanda tangan BARU; mengulang dengan nonce yang
        // sama akan ditolak server sebagai "Duplicate Request" (anti-replay).
        for ($percobaan = 1; $percobaan <= $maksPercobaan; $percobaan++) {
            $timestamp = now()->toIso8601String();
            $nonce = (string) Str::uuid();

            $stringToSign = implode("\n", [
                strtoupper($metode),
                $jalur,
                $queryString,
                $timestamp,
                $nonce,
                $bodyHash,
                $publicKey,
            ]);

            $headers = [
                'X-HC-Public' => $publicKey,
                'X-HC-Signature' => hash_hmac('sha512', $stringToSign, $secret),
                'X-HC-Timestamp' => $timestamp,
                'X-HC-Nonce' => $nonce,
                'X-HC-Body-Hash' => $bodyHash,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            $mulai = microtime(true);

            try {
                $permintaan = Http::withHeaders($headers)->timeout((int) config('hclearn.timeout', 30));

                $respons = match (strtoupper($metode)) {
                    'GET' => $permintaan->get($url . ($queryString ? '?' . $queryString : '')),
                    'DELETE' => $permintaan->delete($url),
                    default => $permintaan->withBody($rawBody, 'application/json')->send(strtoupper($metode), $url),
                };

                $durasi = (int) ((microtime(true) - $mulai) * 1000);
                $json = $respons->json();
                $sukses = $respons->successful();

                $this->catat($metode, $jalur, $queryString, $rawBody, $respons->body(), $respons->status(), $nonce, $durasi, $sukses, $konteksLog);

                $terakhir = [
                    'sukses' => $sukses,
                    'status' => $respons->status(),
                    'message' => $json['message'] ?? ($sukses ? 'Berhasil' : 'Permintaan ke HCLearn gagal'),
                    'result' => $json['result'] ?? $json ?? null,
                ];

                // Ulangi HANYA untuk gangguan sementara (5xx). Balasan 4xx adalah
                // keputusan server — mengulanginya percuma dan menyesatkan.
                if ($sukses || $respons->status() < 500) {
                    return $terakhir;
                }
            } catch (\Throwable $e) {
                $durasi = (int) ((microtime(true) - $mulai) * 1000);

                $this->catat($metode, $jalur, $queryString, $rawBody, null, 0, $nonce, $durasi, false, $konteksLog, $e->getMessage());

                Log::channel('web_career')->error("[HCL] {$metode} {$jalur} percobaan {$percobaan} gagal: " . $e->getMessage());

                $terakhir = [
                    'sukses' => false,
                    'status' => 500,
                    'message' => 'Tidak dapat menghubungi HCLearn: ' . $e->getMessage(),
                    'result' => null,
                ];
            }

            if ($percobaan < $maksPercobaan) {
                usleep(500000);
            }
        }

        return $terakhir;
    }

    /**
     * Jejak panggilan ke N_WEB_CAREERS_Integrasi_Log.
     * Payload penuh HANYA disimpan saat gagal (atau bila sengaja dinyalakan) — panggilan
     * sukses cukup ringkasan + hash, supaya tabel tidak membengkak oleh data pribadi.
     */
    private function catat(
        string $metode,
        string $jalur,
        string $queryString,
        string $rawBody,
        ?string $responsBody,
        int $status,
        string $nonce,
        int $durasi,
        bool $sukses,
        array $konteksLog,
        ?string $pesanError = null,
    ): void {
        try {
            $simpanPayload = ! $sukses || config('hclearn.log_payload_sukses', false);

            DB::table('N_WEB_CAREERS_Integrasi_Log')->insert([
                'Arah' => 'KELUAR',
                'Jenis_Event' => $konteksLog['Jenis_Event'] ?? null,
                'Metode' => strtoupper($metode),
                'Endpoint' => $jalur . ($queryString ? '?' . $queryString : ''),
                'Penjadwalan_Id' => $konteksLog['Penjadwalan_Id'] ?? null,
                'Penjadwalan_Tahap_Id' => $konteksLog['Penjadwalan_Tahap_Id'] ?? null,
                'Penjadwalan_Peserta_Id' => $konteksLog['Penjadwalan_Peserta_Id'] ?? null,
                'Id_Ujian_Token' => $konteksLog['Id_Ujian_Token'] ?? null,
                'Http_Status' => $status,
                'Ringkasan' => Str::limit($pesanError ?? ($sukses ? 'OK' : 'Gagal'), 480),
                'Payload_Hash' => hash('sha256', $rawBody),
                'Request_Json' => $simpanPayload ? $rawBody : null,
                'Response_Json' => $simpanPayload ? Str::limit((string) $responsBody, 60000) : null,
                'Nonce' => $nonce,
                'Durasi_Ms' => $durasi,
                'Flag_Sukses' => $sukses ? 'Y' : 'T',
                'Pesan_Error' => $pesanError ? Str::limit($pesanError, 480) : null,
                'Kedaluwarsa_At' => now()->addDays((int) config('hclearn.log_retensi_hari', 30)),
                'Created_At' => now(),
                'Created_By_Id' => session('career_auth.id'),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[HCL] gagal menulis Integrasi_Log: ' . $e->getMessage());
        }
    }
}
