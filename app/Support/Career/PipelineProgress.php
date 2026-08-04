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

    /**
     * Tahap aktif — ada selama masih BERJALAN, ATAU selama lamaran sudah
     * LULUS tapi masih ada tahap administratif pasca-tuntas yang berjalan
     * (kontrak, onboarding). Tanpa pengecualian LULUS ini, kandidat yang
     * sudah diterima tapi belum tanda tangan kontrak terbaca "tidak punya
     * tahap aktif" — Worklist dan Monitoring kehilangan jejaknya persis di
     * titik paling penting (kandidat sudah DITERIMA, tapi belum ONBOARD).
     */
    public static function tahapAktif(object $l, Collection $tahapList): ?object
    {
        if ($l->Status === 'BERJALAN') {
            return $tahapList->firstWhere('Status', 'BERJALAN');
        }
        if ($l->Status === 'LULUS') {
            return $tahapList->firstWhere('Status', 'BERJALAN');
        }

        return null;
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
     * @param  array  $alasanHold  [Kode => nama] dari Master Alasan Hold.
     *                             Dikirim pemanggil (yang sudah memuatnya sekali
     *                             untuk seluruh daftar) — kelas ini read-only dan
     *                             tidak menyentuh database sendiri.
     */
    public static function state(object $l, ?object $tAktif, ?object $tk, iterable $subAktif = [], array $alasanHold = []): array
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

        // Aktivitas yang WAJIB punya hasil dulu = penentu & dijalankan pihak ke-3
        // (ujian online). Aktivitas yang dikerjakan tim — wawancara, verifikasi
        // berkas — hasilnya ya keputusan admin itu sendiri, jadi tidak boleh
        // saling mengunci.
        $belumTercatat = 0;
        $penentuFinal = [];
        foreach ($subAktif as $s) {
            $penentu = ($s->Peran ?? 'PENENTU') === 'PENENTU';
            if (! $penentu) {
                continue;
            }
            $final = ($s->Flag_Selesai ?? 'N') === 'Y';
            if ($final) {
                $penentuFinal[] = $s;
            } elseif (($s->Provider ?? '') === 'THIRD_PARTY') {
                $belumTercatat++;
            }
        }

        // ── APA KATA DATANYA ────────────────────────────────────────────────
        // Admin yang memutus tahap ber-ujian online butuh tahu hasil tesnya
        // SEBELUM mengetuk palu. Tanpa ini, layar cuma bilang "Siap Diputus" dan
        // admin harus menebak — atau membuka rapor satu per satu — padahal
        // sistem sudah tahu kandidat ini lulus semua atau ada yang gagal.
        $hasilData = null;
        $ringkasHasil = null;
        if ($penentuFinal) {
            $gagal = array_values(array_filter($penentuFinal, fn ($s) => ($s->Hasil ?? null) === 'GAGAL'));
            $lulus = array_values(array_filter($penentuFinal, fn ($s) => ($s->Hasil ?? null) === 'LULUS'));
            $semuaFinal = $belumTercatat === 0;

            if ($gagal) {
                $hasilData = 'GAGAL';
                $nama = implode(', ', array_map(fn ($s) => $s->Label ?? 'tes', $gagal));
                $ringkasHasil = 'Data menyatakan TIDAK LULUS pada: ' . $nama . '.';
            } elseif ($lulus && $semuaFinal) {
                $hasilData = 'LULUS';
                $ringkasHasil = 'Seluruh tes penentu sudah selesai dan LULUS — tinggal dikonfirmasi.';
            } elseif ($lulus) {
                $hasilData = 'SEBAGIAN';
                $ringkasHasil = 'Sebagian tes penentu sudah lulus; ' . $belumTercatat . ' aktivitas lagi menunggu hasil.';
            }
        }

        // ── DITAHAN (HOLD) ──────────────────────────────────────────────────
        // Keadaan yang MENDAHULUI segalanya: selama kandidat ditahan, tak ada
        // keputusan yang boleh diambil dan mesin pun tidak menyimpulkan. Tanpa
        // ini, worklist tetap menampilkan "Perlu Keputusan" untuk orang yang
        // justru sengaja disisihkan — dan tombolnya ditekan oleh siapa pun yang
        // tidak tahu ada alasan di baliknya.
        $ditahan = $tAktif && ($tAktif->Hold_Flag ?? 'T') === 'Y';

        $butuhKeputusan = $tAktif
            && $tAktif->Status === 'BERJALAN'
            && ! $ditahan
            && ! $otomatis
            && ($siap || $belumTercatat === 0);

        $alasanKunci = null;
        if ($tAktif && $tAktif->Status === 'BERJALAN' && ! $butuhKeputusan) {
            $alasanKunci = match (true) {
                $ditahan => 'Kandidat sedang DITAHAN — lepaskan penahanannya dulu sebelum memutuskan.',
                $otomatis => 'Tahap ini disetel OTOMATIS di Master Alur — sistem yang memutuskan begitu aktivitasnya selesai.',
                default => "Menunggu hasil {$belumTercatat} aktivitas penentu dicatat lebih dulu.",
            };
        }

        return [
            'ditahan' => $ditahan,
            // Nama alasannya dari master — badge menyebut SEBAB penahanan
            // ("Ditahan — Menunggu kuota"), bukan sekadar bahwa ia ditahan.
            'holdNama' => $ditahan ? ($alasanHold[$tAktif->Hold_Alasan_Kode ?? ''] ?? null) : null,
            'skor' => $tk->Skor ?? null,
            'isTes' => ($tk->Provider ?? null) === 'THIRD_PARTY',
            'siap' => $siap,
            'nungguSistem' => $tAktif && ! $siap && ($otomatis || $belumTercatat > 0),
            'butuhKeputusan' => $butuhKeputusan,
            'modeKeputusan' => $tAktif ? ($otomatis ? 'SYSTEM' : 'MANUAL') : null,
            'otomatis' => $otomatis && $tAktif !== null,
            'aktivitasBelumTercatat' => $belumTercatat,
            'alasanKunci' => $alasanKunci,
            // Kesimpulan MESIN atas hasil tes — bukan keputusan admin.
            'hasilData' => $hasilData,
            'ringkasHasil' => $ringkasHasil,
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
        // DITAHAN mendahului LULUS/pasca-penerimaan — keputusan produk: HOLD
        // selalu menang. Kandidat yang sudah diterima tapi tahap administratifnya
        // (kontrak, onboarding) sedang ditahan tetap tampil "Ditahan", bukan
        // "Proses Administrasi" — sampai penahanannya dilepas.
        if (! empty($state['ditahan'])) {
            return ['tone' => 'hold', 'teks' => $state['holdNama'] ? 'Ditahan — ' . $state['holdNama'] : 'Ditahan'];
        }
        if ($l->Status === 'LULUS') {
            // DITERIMA tidak selalu berarti TUNTAS SELURUH TAHAP. Bila masih
            // ada tahap administratif (kontrak, onboarding) yang berjalan,
            // itu perlu tetap terlihat sebagai kerjaan — bukan "sudah selesai".
            return $tAktif
                ? ['tone' => 'pascaPenerimaan', 'teks' => 'Diterima — Proses Administrasi']
                : ['tone' => 'lolos', 'teks' => 'Diterima'];
        }
        // Siap diputus + data sudah bicara → sebutkan kesimpulannya di badge,
        // supaya admin tahu mana yang tinggal diketuk dan mana yang perlu ditimbang.
        if ($state['siap']) {
            if (($state['hasilData'] ?? null) === 'LULUS') {
                return ['tone' => 'perlu', 'teks' => 'Lulus — Konfirmasi'];
            }
            if (($state['hasilData'] ?? null) === 'GAGAL') {
                return ['tone' => 'perlu', 'teks' => 'Tidak Lulus — Konfirmasi'];
            }

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
