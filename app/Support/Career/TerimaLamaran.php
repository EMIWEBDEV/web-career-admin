<?php

namespace App\Support\Career;

use App\Jobs\Career\WcApplyEmailJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Langkah bersama sesudah sebuah lamaran dibuat — dipakai WcApplyFormJob
 * (Apply_Payload) dan peristiwa Lamaran.Dikirim dari situs kandidat
 * (App\Support\Sinkron\Penangan\LamaranDikirim), supaya kedua jalur
 * menulis berkas dan mengirim surel hasil dengan aturan yang sama persis.
 */
final class TerimaLamaran
{
    /**
     * Tautkan berkas pendaftaran ke pengisian formulir PENDAFTARAN (tahap 1).
     *
     * Ada berkas tapi tak ada pengisian untuk ditempeli → GALAT, bukan
     * dilewati: lebih baik gagal & terlihat daripada tuntas tapi kehilangan CV.
     *
     * @param  list<array{field: string, bagian?: ?string, baris?: ?int, nama: string, path: string, ukuran?: ?int, mime?: ?string, ext?: ?string, hash?: ?string}>  $berkas
     */
    public static function simpanBerkasPendaftaran(int $lamaranId, int $userId, array $berkas, ?string $nama): void
    {
        if (! $berkas) {
            return;
        }

        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->where('Lamaran_Id', $lamaranId)
            ->where('Sumber', 'PENDAFTARAN')
            ->orderBy('Id_Formulir_Pengisian')
            ->first(['Id_Formulir_Pengisian']);

        if (! $pengisian) {
            throw new RuntimeException('Pengisian pendaftaran tidak terbentuk — berkas lamaran tidak bisa ditautkan.');
        }

        $now = now();
        foreach (array_values($berkas) as $i => $b) {
            DB::table('N_WEB_CAREERS_Formulir_Berkas')->insert([
                'Formulir_Pengisian_Id' => $pengisian->Id_Formulir_Pengisian,
                'Id_Users' => $userId,
                'Field_Key' => $b['field'],
                // Posisi baris bagian berulang — tanpa ini worklist tak bisa
                // memasangkan sertifikat ke barisnya sendiri.
                'Bagian_Key' => $b['bagian'] ?? null,
                'Baris_Index' => $b['baris'] ?? null,
                'Urutan' => $i + 1,
                'Nama_Asli' => mb_substr((string) $b['nama'], 0, 250),
                'Path_File' => $b['path'],
                'Ukuran_Byte' => $b['ukuran'] ?? null,
                'Mime' => $b['mime'] ?? null,
                'Ekstensi' => $b['ext'] ?? null,
                'Hash_File' => $b['hash'] ?? null,
                'Status_Verifikasi' => 'BELUM',
                'Waktu_Unggah' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ]);
        }
    }

    /**
     * Tentukan hasil lamaran & antrekan surel ke kandidat (queue wc-applymail).
     *   GUGUR    = lamaran auto-gugur (Status lamaran GUGUR).
     *   LOLOS    = lolos syarat otomatis (tahap 1 = LULUS).
     *   MENUNGGU = tanpa syarat / menunggu keputusan admin (tahap 1 masih BERJALAN).
     * Kegagalan surel tidak pernah menggagalkan lamaran.
     */
    public static function kirimEmailHasil(int $lamaranId, int $userId): void
    {
        try {
            $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->leftJoin('N_WEB_CAREERS_Program as pr', 'pr.Id_Program', '=', 'l.Program_Id')
                ->where('l.Id_Lamaran', $lamaranId)
                ->select('l.Kode', 'l.Status', 'l.Total_Tahap', 'pos.Posisi as posisi', 'pr.Nama as program')
                ->first();

            if (! $lam) {
                return;
            }

            $extra = ['kode' => $lam->Kode, 'posisi' => $lam->posisi, 'program' => $lam->program];

            // Data kartu kandidat untuk surel (tanggal lahir, kampus, PATH foto)
            // — dipakai bersama dengan surel keputusan tahap.
            $extra += LamaranService::dataKandidatEmail($lamaranId);

            if ($lam->Status === 'GUGUR') {
                $status = 'GUGUR';
            } else {
                // Tahap TERAKHIR yang sudah diputus LULUS. Saat lolos, tahap
                // di-set Status='SELESAI' + Hasil='LULUS' (BUKAN Status='LULUS'),
                // jadi deteksi lolos memakai kolom Hasil.
                $lolos = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)->where('Hasil', 'LULUS')
                    ->orderByDesc('Urutan')->first();

                if ($lolos) {
                    $status = 'LOLOS';
                    $extra['tahapLolos'] = $lolos->Label;
                    $extra['urutan'] = (int) $lolos->Urutan;
                    $extra['total'] = (int) ($lam->Total_Tahap ?? 0);
                    // Tahap berikutnya (null bila ini tahap terakhir → DITERIMA).
                    $extra['tahapBerikut'] = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Lamaran_Id', $lamaranId)->where('Urutan', '>', $lolos->Urutan)
                        ->orderBy('Urutan')->value('Label');
                    $extra['diterima'] = $lam->Status === 'LULUS';
                } else {
                    // Belum ada keputusan otomatis (tahap 1 masih BERJALAN).
                    $status = 'MENUNGGU';
                }
            }

            WcApplyEmailJob::dispatch($userId, $status, $extra);

            Log::info("[APPLY] email hasil '{$status}' di-antre user #{$userId} lamaran #{$lamaranId} (queue ".WcApplyEmailJob::QUEUE.').');
        } catch (\Throwable $e) {
            Log::error("[APPLY] gagal antre email hasil lamaran #{$lamaranId}: ".$e->getMessage());
        }
    }
}
