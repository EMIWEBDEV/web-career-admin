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
    /**
     * @param  iterable  $subAktif  sub-tes (aktivitas) TAHAP AKTIF — dipakai
     *                              menilai apakah hasilnya sudah tercatat
     */
    public static function state(object $l, ?object $tAktif, ?object $tk, iterable $subAktif = []): array
    {
        $siap = $tAktif && ($tAktif->Siap_Diputus ?? 'N') === 'Y';

        // ── SIAPA YANG BOLEH MEMUTUS TAHAP INI ──────────────────────────────
        // Ditentukan Master Alur, bukan ditebak dari provider.
        //
        //  SYSTEM  "Otomatis — maju sendiri bila lulus". Mesin yang memutus
        //          begitu aktivitasnya selesai; admin tidak mengetuk palu. Kalau
        //          admin tetap bisa, mode otomatis yang disetel di Master Alur
        //          jadi tak ada artinya.
        //  MANUAL  Admin yang memutus — tapi hanya setelah hasil aktivitas
        //          penentu tercatat. Meloloskan tes yang nilainya belum ada
        //          sama saja memutus tanpa dasar.
        $otomatis = strtoupper((string) ($tAktif->Keputusan_Mode ?? 'MANUAL')) === 'SYSTEM';

        // Aktivitas yang WAJIB punya hasil dulu = penentu & benar-benar tes
        // (punya jenis tes / dari pihak ke-3). Aktivitas tanpa jenis tes — mis.
        // wawancara atau verifikasi berkas — hasilnya ya keputusan admin itu
        // sendiri, jadi tidak boleh saling mengunci.
        $belumTercatat = 0;
        foreach ($subAktif as $s) {
            $penentu = ($s->Peran ?? 'PENENTU') === 'PENENTU';
            $adalahTes = ! empty($s->Jenis_Tes_Kode) || ($s->Provider ?? '') === 'THIRD_PARTY';
            if ($penentu && $adalahTes && ($s->Flag_Selesai ?? 'N') !== 'Y') {
                $belumTercatat++;
            }
        }

        $butuhKeputusan = $tAktif
            && $tAktif->Status === 'BERJALAN'
            && ! $otomatis
            && ($siap || $belumTercatat === 0);

        $alasanKunci = null;
        if ($tAktif && $tAktif->Status === 'BERJALAN' && ! $butuhKeputusan) {
            $alasanKunci = $otomatis
                ? 'Tahap ini disetel OTOMATIS di Master Alur — sistem yang memutuskan begitu aktivitasnya selesai.'
                : "Menunggu hasil {$belumTercatat} aktivitas penentu dicatat lebih dulu.";
        }

        return [
            'skor' => $tk->Skor ?? null,
            'isTes' => ($tk->Provider ?? null) === 'THIRD_PARTY',
            'siap' => $siap,
            'nungguSistem' => $tAktif && ! $siap && ($otomatis || $belumTercatat > 0),
            'butuhKeputusan' => $butuhKeputusan,
            'modeKeputusan' => $tAktif ? ($otomatis ? 'SYSTEM' : 'MANUAL') : null,
            'otomatis' => $otomatis && $tAktif !== null,
            'aktivitasBelumTercatat' => $belumTercatat,
            'alasanKunci' => $alasanKunci,
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
