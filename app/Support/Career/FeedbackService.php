<?php

namespace App\Support\Career;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class FeedbackService
{
    /**
     * Resolve form feedback untuk program tertentu.
     * Prioritas: spesifik > general.
     */
    public function resolveForm(?int $programId): ?object
    {
        if ($programId) {
            $specific = DB::table('N_WEB_CAREERS_Feedback_Assignment')
                ->where('Program_Id', $programId)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cancellation', 'T')
                ->join('N_WEB_CAREERS_Master_Feedback_Form',
                    'N_WEB_CAREERS_Feedback_Assignment.Master_Feedback_Form_Id', '=',
                    'N_WEB_CAREERS_Master_Feedback_Form.Id_Master_Feedback_Form')
                ->select('N_WEB_CAREERS_Master_Feedback_Form.*')
                ->first();

            if ($specific) return $specific;
        }

        $general = DB::table('N_WEB_CAREERS_Feedback_Assignment')
            ->where('Flag_General', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Cancellation', 'T')
            ->join('N_WEB_CAREERS_Master_Feedback_Form',
                'N_WEB_CAREERS_Feedback_Assignment.Master_Feedback_Form_Id', '=',
                'N_WEB_CAREERS_Master_Feedback_Form.Id_Master_Feedback_Form')
            ->select('N_WEB_CAREERS_Master_Feedback_Form.*')
            ->first();

        return $general;
    }

    /**
     * Buat feedback record untuk lamaran.
     * Dipanggil dari LamaranService::evaluasiTahap() saat keputusan final.
     */
    public function buatFeedback(int $lamaranId, string $email, ?int $programId): ?int
    {
        $form = $this->resolveForm($programId);
        if (! $form) return null;

        $now = FormatTanggalHelper::getCurrentTime();

        try {
            $id = DB::table('N_WEB_CAREERS_Feedback_Jawaban')->insertGetId([
                'Lamaran_Id' => $lamaranId,
                'Master_Feedback_Form_Id' => $form->Id_Master_Feedback_Form,
                'Email_Token' => $email,
                'Status_Pengisian' => 'MENUNGGU',
                'Created_At' => $now,
            ], 'Id_Feedback_Jawaban');

            // Generate token
            $tokens = $this->generateTokenPair($id, $email);
            DB::table('N_WEB_CAREERS_Feedback_Jawaban')
                ->where('Id_Feedback_Jawaban', $id)
                ->update(['Token_Hash' => $tokens['hashids'] . '.' . $tokens['signature']]);

            Log::channel('feedback')->info('Feedback record dibuat', [
                'feedback_id' => $id,
                'lamaran_id' => $lamaranId,
            ]);

            return $id;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            Log::channel('feedback')->warning('Duplicate feedback dicegah', [
                'lamaran_id' => $lamaranId,
            ]);
            return null;
        }
    }

    /**
     * Generate token pair: hashids + HMAC signature.
     */
    public function generateTokenPair(int $feedbackId, string $email): array
    {
        $hashids = Hashids::encode($feedbackId);
        $signature = substr(hash_hmac('sha256', $email . '|' . $feedbackId, config('app.key')), 0, 32);
        return ['hashids' => $hashids, 'signature' => $signature];
    }

    /**
     * Verifikasi token dari URL.
     * Return object feedback record atau null jika invalid.
     */
    public function verifyToken(string $hashids, string $signature): ?object
    {
        $decoded = Hashids::decode($hashids);
        if (empty($decoded)) return null;
        $feedbackId = $decoded[0];

        $feedback = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Master_Feedback_Form as ff',
                'fj.Master_Feedback_Form_Id', '=', 'ff.Id_Master_Feedback_Form')
            ->where('fj.Id_Feedback_Jawaban', $feedbackId)
            ->where('fj.Flag_Cancellation', 'T')
            ->select('fj.*', 'ff.Nama as Form_Nama', 'ff.Durasi_Hari', 'ff.Mode_Tampilan')
            ->first();

        if (! $feedback) return null;

        // Verify HMAC
        $expected = substr(hash_hmac('sha256', $feedback->Email_Token . '|' . $feedbackId, config('app.key')), 0, 32);
        if (! hash_equals($expected, $signature)) return null;

        return $feedback;
    }

    /**
     * Cek apakah feedback sudah expired.
     * Absolute max 30 hari, atau Durasi_Hari dari form.
     */
    public function isExpired(object $feedback): bool
    {
        $createdAt = \Carbon\Carbon::parse($feedback->Created_At);
        $maxDays = $feedback->Durasi_Hari ?? 30;
        $expiryDays = min($maxDays, 30);
        return $createdAt->addDays($expiryDays)->isPast();
    }

    /**
     * Simpan jawaban feedback (atomic transaction).
     */
    public function simpanJawaban(int $feedbackId, array $jawaban): bool
    {
        $now = FormatTanggalHelper::getCurrentTime();

        DB::beginTransaction();
        try {
            // Optimistic lock — cegah double submit
            $updated = DB::table('N_WEB_CAREERS_Feedback_Jawaban')
                ->where('Id_Feedback_Jawaban', $feedbackId)
                ->where('Status_Pengisian', 'MENUNGGU')
                ->update([
                    'Status_Pengisian' => 'TERISI',
                    'Submitted_At' => $now,
                    'Updated_At' => $now,
                ]);

            if ($updated === 0) {
                DB::rollBack();
                return false;
            }

            $rows = [];
            foreach ($jawaban as $j) {
                $rows[] = [
                    'Feedback_Jawaban_Id' => $feedbackId,
                    'Master_Feedback_Pertanyaan_Id' => $j['id_pertanyaan'],
                    'Jawaban' => (string) $j['jawaban'],
                    'Label_Snapshot' => $j['label_snapshot'] ?? null,
                    'Tipe_Snapshot' => $j['tipe_snapshot'] ?? null,
                    'Opsi_Snapshot' => $j['opsi_snapshot'] ?? null,
                    'Skala_Min_Snapshot' => $j['skala_min_snapshot'] ?? null,
                    'Skala_Max_Snapshot' => $j['skala_max_snapshot'] ?? null,
                    'Created_At' => $now,
                ];
            }

            DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail')->insert($rows);
            DB::commit();

            Log::channel('feedback')->info('Feedback terisi', [
                'feedback_id' => $feedbackId,
                'jumlah_jawaban' => count($rows),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('feedback')->error('Submit feedback gagal', [
                'feedback_id' => $feedbackId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Agregat untuk dashboard Layer 1.
     */
    public function aggregate(?int $formId, ?int $programId, ?string $dateFrom, ?string $dateTo): array
    {
        $baseQuery = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T');

        if ($formId) $baseQuery->where('fj.Master_Feedback_Form_Id', $formId);
        if ($programId) $baseQuery->where('l.Program_Id', $programId);
        if ($dateFrom) $baseQuery->where('fj.Submitted_At', '>=', $dateFrom);
        if ($dateTo) $baseQuery->where('fj.Submitted_At', '<=', $dateTo);

        $totalDibuat = (clone $baseQuery)->count();
        $totalTerisi = (clone $baseQuery)->where('fj.Status_Pengisian', 'TERISI')->count();

        $skorStats = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'd.Feedback_Jawaban_Id', '=', 'fj.Id_Feedback_Jawaban')
            ->join('N_WEB_CAREERS_Master_Feedback_Pertanyaan as p', 'd.Master_Feedback_Pertanyaan_Id', '=', 'p.Id_Master_Feedback_Pertanyaan')
            ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
            ->where('fj.Flag_Cancellation', 'T')
            ->where('fj.Status_Pengisian', 'TERISI')
            ->whereIn('p.Tipe', ['RATING', 'NPS', 'LIKERT'])
            ->when($formId, fn($q) => $q->where('fj.Master_Feedback_Form_Id', $formId))
            ->when($programId, fn($q) => $q->where('l.Program_Id', $programId))
            ->when($dateFrom, fn($q) => $q->where('fj.Submitted_At', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('fj.Submitted_At', '<=', $dateTo))
            ->selectRaw("p.Tipe, AVG(TRY_CAST(d.Jawaban AS FLOAT)) as rata2, COUNT(*) as jumlah")
            ->groupBy('p.Tipe')
            ->get();

        $avgWaktu = (clone $baseQuery)
            ->where('fj.Status_Pengisian', 'TERISI')
            ->whereNotNull('fj.Submitted_At')
            ->selectRaw("AVG(DATEDIFF(SECOND, fj.Created_At, fj.Submitted_At)) as avg_detik")
            ->first();

        return [
            'total_dibuat' => $totalDibuat,
            'total_terisi' => $totalTerisi,
            'response_rate' => $totalDibuat > 0 ? round($totalTerisi / $totalDibuat * 100, 1) : 0,
            'skor_stats' => $skorStats,
            'avg_waktu_detik' => $avgWaktu->avg_detik ?? 0,
        ];
    }

    /**
     * NPS: %Promoters(9-10) - %Detractors(0-6). Range: -100 to +100.
     */
    public function calculateNPS(array $scores): float
    {
        $total = count($scores);
        if ($total === 0) return 0.0;

        $promoters = count(array_filter($scores, fn($s) => (int) $s >= 9));
        $detractors = count(array_filter($scores, fn($s) => (int) $s <= 6));

        return round(($promoters - $detractors) / $total * 100, 1);
    }
}
