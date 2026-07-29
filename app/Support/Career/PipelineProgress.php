<?php

namespace App\Support\Career;

use Illuminate\Support\Collection;

/**
 * WEB CAREER — Aturan BACA posisi pipeline sebuah lamaran (read-only).
 *
 * Satu sumber kebenaran penempatan pelamar pada papan/funnel, dipakai:
 *  - Worklist admin  (LamaranController::detailProgram)
 *  - Monitoring Rekrutmen (MonitoringController)
 *
 * Logika dipindah apa adanya dari worklist. Perubahan aturan di sini
 * berdampak ke SEMUA halaman pemakai — uji keduanya.
 */
class PipelineProgress
{
    /**
     * Tahap yang MEWAKILI lamaran pada papan/funnel:
     *  - GUGUR       → tahap tempat ia gugur (tetap "di loop" tahap itu)
     *  - TALENT_POOL → tahap tempat ia dimasukkan pool
     *  - LULUS       → tahap terakhir
     *  - lainnya     → tahap yang sedang BERJALAN (fallback tahap pertama)
     */
    public static function tahapKini(object $l, Collection $tahapList): ?object
    {
        if ($l->Status === 'GUGUR') {
            return $tahapList->firstWhere('Hasil', 'GUGUR') ?? $tahapList->last();
        }
        if ($l->Status === 'TALENT_POOL') {
            return $tahapList->firstWhere('Hasil', 'TALENT_POOL') ?? $tahapList->last();
        }
        if ($l->Status === 'LULUS') {
            return $tahapList->last();
        }

        return $tahapList->firstWhere('Status', 'BERJALAN') ?? $tahapList->first();
    }

    /** Tahap aktif — hanya ada selama lamaran masih BERJALAN. */
    public static function tahapAktif(object $l, Collection $tahapList): ?object
    {
        return $l->Status === 'BERJALAN' ? $tahapList->firstWhere('Status', 'BERJALAN') : null;
    }

    /**
     * State mesin keputusan pada tahap aktif.
     * BUTUH KEPUTUSAN berbasis STATE mesin, bukan sekadar provider:
     *  - Siap_Diputus='Y' → mesin sudah mengumpulkan semua hasil (multi-tes),
     *    admin tinggal memutuskan — termasuk pada tahap pihak ke-3.
     *  - Tahap manual (bukan THIRD_PARTY) tetap bisa diputus kapan pun.
     */
    public static function state(object $l, ?object $tAktif, ?object $tk): array
    {
        $siap = $tAktif && ($tAktif->Siap_Diputus ?? 'N') === 'Y';

        return [
            'skor' => $tk->Skor ?? null,
            'isTes' => ($tk->Provider ?? null) === 'THIRD_PARTY',
            'siap' => $siap,
            'nungguSistem' => $tAktif && $tAktif->Provider === 'THIRD_PARTY' && ! $siap,
            'butuhKeputusan' => $tAktif && $tAktif->Status === 'BERJALAN' && ($siap || $tAktif->Provider !== 'THIRD_PARTY'),
        ];
    }

    /** Badge "lampu lalu lintas" kartu/baris pelamar. */
    public static function badge(object $l, array $state, ?object $tAktif): array
    {
        if ($l->Status === 'GUGUR') {
            return ['tone' => 'gugur', 'teks' => 'Tidak Lolos'];
        }
        if ($l->Status === 'TALENT_POOL') {
            return ['tone' => 'talent', 'teks' => 'Talent Pool'];
        }
        if ($l->Status === 'LULUS') {
            return ['tone' => 'lolos', 'teks' => 'Diterima'];
        }
        if ($state['siap']) {
            return ['tone' => 'perlu', 'teks' => 'Siap Diputus'];
        }
        if ($state['isTes'] && $state['skor'] !== null) {
            return ['tone' => 'skor', 'teks' => (string) $state['skor']];
        }
        if ($state['nungguSistem']) {
            return ['tone' => 'nunggu', 'teks' => 'Menunggu Tes'];
        }
        if ($state['butuhKeputusan']) {
            return ['tone' => 'perlu', 'teks' => $tAktif->Rekomendasi === 'GUGUR' ? 'Borderline' : 'Perlu Keputusan'];
        }

        return ['tone' => 'berjalan', 'teks' => 'Berjalan'];
    }

    /**
     * Sadar-kuota: kursi terisi (LULUS) vs kuota MPP posisi. Loloskan hanya
     * dibatasi bila kandidat berada di TAHAP TERAKHIR (LULUS = diterima).
     */
    public static function infoKuota(int $kuota, int $terisi, int $urutanAktif, int $totalTahap): array
    {
        return [
            'kuota' => $kuota,
            'terisiKuota' => $terisi,
            'sisaKuota' => $kuota > 0 ? max(0, $kuota - $terisi) : null,
            'kuotaPenuh' => $kuota > 0 && $terisi >= $kuota,
            'diTahapAkhir' => $urutanAktif > 0 && $urutanAktif >= $totalTahap,
        ];
    }
}
