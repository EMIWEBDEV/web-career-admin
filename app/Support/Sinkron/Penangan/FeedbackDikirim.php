<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\FeedbackService;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Facades\DB;

/**
 * Feedback.Dikirim — kandidat mengisi survei feedback (tautan surel / portal).
 *
 * Jawaban dicek ulang terhadap pertanyaan form-nya (yang tak dikenal
 * dibuang), lalu disimpan FeedbackService::simpanJawaban — kunci optimistis
 * MENUNGGU → TERISI mencegah kiriman ganda.
 */
final class FeedbackDikirim implements Penangan
{
    public function __construct(private FeedbackService $svc) {}

    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $userId = PetaId::akunAdmin((int) $muatan['akun']['id_publik']);
        $feedbackId = (int) $muatan['feedback_id'];

        $fb = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fj.Lamaran_Id')
            ->where('fj.Id_Feedback_Jawaban', $feedbackId)
            ->where('fj.Flag_Cancellation', 'T')
            ->where('l.Kode', (string) $muatan['kode'])
            ->where('l.Id_Users', $userId)
            ->first(['fj.Id_Feedback_Jawaban', 'fj.Master_Feedback_Form_Id', 'fj.Status_Pengisian', 'l.Id_Lamaran']);
        if (! $fb) {
            throw new PeristiwaDitolak('Feedback tidak ditemukan.');
        }
        if ($fb->Status_Pengisian === 'TERISI') {
            throw new PeristiwaDitolak('Feedback sudah dikirim sebelumnya.');
        }

        $pertanyaan = DB::table('N_WEB_CAREERS_Master_Feedback_Pertanyaan')
            ->where('Master_Feedback_Form_Id', $fb->Master_Feedback_Form_Id)
            ->where('Flag_Cancellation', 'T')
            ->pluck('Id_Master_Feedback_Pertanyaan')
            ->map(fn ($v) => (int) $v)
            ->all();

        $jawaban = array_values(array_filter(
            (array) $muatan['jawaban'],
            fn ($j) => is_array($j) && in_array((int) ($j['id_pertanyaan'] ?? 0), $pertanyaan, true),
        ));
        if (! $jawaban) {
            throw new PeristiwaDitolak('Jawaban feedback kosong.');
        }

        if (! $this->svc->simpanJawaban($feedbackId, $jawaban)) {
            throw new PeristiwaDitolak('Feedback sudah dikirim sebelumnya.');
        }

        return HasilPenanganan::ok('feedback:'.$feedbackId, 'Feedback berhasil dikirim.')
            ->segarkan((int) $fb->Id_Lamaran);
    }
}
