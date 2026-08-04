<?php

namespace App\Support\Career;

use Illuminate\Support\Collection;

/**
 * WEB CAREER — READ MODEL BUCKET OPERASIONAL PIPELINE (read-only).
 *
 * Satu sumber kebenaran "kandidat ini sedang menunggu siapa/apa", dipakai
 * Worklist (lewat PipelineProgress yang sudah ada), Monitoring Rekrutmen, dan
 * Dashboard Admin. Prioritas bucket TIDAK simetris — HOLD mendominasi seluruh
 * status lain karena selama ditahan, tak ada keputusan yang boleh diambil
 * (lihat LamaranService::evaluasiTahap).
 */
class PipelineReadModel
{
    /**
     * @param  object  $lamaran     baris N_WEB_CAREERS_Lamaran (butuh ->Status)
     * @param  Collection  $tahapList   seluruh Lamaran_Tahap milik lamaran ini
     * @param  iterable  $subAktif    Lamaran_Tahap_Tes milik tahap aktif
     * @param  array  $masterHasil     [Kode => object] Master_Hasil_Keputusan, dari LamaranService::masterHasilKeputusan()
     */
    public static function bucket(object $lamaran, Collection $tahapList, iterable $subAktif = [], array $masterHasil = []): array
    {
        $tAktif = PipelineProgress::tahapAktif($lamaran, $tahapList);

        // ── OUTCOME TERMINAL ────────────────────────────────────────────────
        // Lamaran sudah tidak BERJALAN dan tidak sedang pasca-penerimaan.
        if (! in_array($lamaran->Status, ['BERJALAN', 'LULUS'], true)) {
            $def = $masterHasil[$lamaran->Status] ?? null;

            return [
                'bucket' => 'TERMINAL',
                'outcomeOlehKandidat' => ($def->Flag_Oleh_Kandidat ?? 'T') === 'Y',
                'outcomeKode' => $lamaran->Status,
            ];
        }

        // ── HOLD mendominasi seluruh status lain ─────────────────────────────
        if ($tAktif && ($tAktif->Hold_Flag ?? 'T') === 'Y') {
            return ['bucket' => 'HOLD', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        // ── PASCAPENERIMAAN: LULUS tapi masih ada tahap BERJALAN ────────────
        if ($lamaran->Status === 'LULUS' && $tAktif) {
            return ['bucket' => 'PASCAPENERIMAAN', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        if ($lamaran->Status === 'LULUS') {
            return ['bucket' => 'TERMINAL', 'outcomeOlehKandidat' => false, 'outcomeKode' => 'LULUS'];
        }

        // ── SIAP_DIPUTUS: mesin sudah selesai, admin tinggal memutus ────────
        if ($tAktif && ($tAktif->Siap_Diputus ?? 'N') === 'Y') {
            return ['bucket' => 'SIAP_DIPUTUS', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
        }

        return ['bucket' => 'BERPROSES', 'outcomeOlehKandidat' => false, 'outcomeKode' => null];
    }
}
