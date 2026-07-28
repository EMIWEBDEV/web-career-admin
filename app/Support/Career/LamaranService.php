<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WEB CAREER — Alur lamaran end-to-end.
 *
 * Menyatukan tiga hal yang harus terjadi berurutan dan tidak boleh terpisah:
 *   1. buatLamaran()      — kandidat melamar: lamaran dibuat, tahap seleksi
 *                           DISALIN dari alur (jadi catatan perjalanan yang beku).
 *   2. simpanPengisian()  — kandidat mengirim formulir sebuah tahap: jawaban
 *                           disimpan, field diproyeksikan, lalu mesin syarat
 *                           menilai dan MEREKOMENDASIKAN (bukan memutuskan).
 *   3. ketukPalu()        — admin memutuskan lolos/gugur; lamaran maju atau gugur.
 *
 * Mesin hanya merekomendasikan. Keputusan resmi tetap milik admin, kecuali
 * sebuah syarat sengaja disetel Aksi=GUGUR (mis. berkas wajib tak diunggah).
 */
class LamaranService
{
    /**
     * Kandidat melamar sebuah posisi.
     *
     * @param  string|null  $gugurAlasan  bila diisi: lamaran langsung ditandai
     *   TIDAK LOLOS pada tahap pertama (mis. knock-out saat finalisasi ApplyForm).
     *   Lamaran TETAP tercatat agar muncul di "Lamaran Saya" berstatus Gugur.
     * @return array{ok:bool, pesan:string, lamaranId?:int}
     */
    public function buatLamaran(int $userId, int $pembukaanId, int $posisiId, ?int $userAdminId = null, ?string $gugurAlasan = null, ?array $jawaban = null): array
    {
        // Kandidat melamar lewat sebuah PEMBUKAAN — bukan program mentah. Pembukaan
        // membawa konteks channel (UMUM/KAMPUS) + whitelist yang nanti dipakai form.
        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')->where('Id_Pembukaan', $pembukaanId)->first();
        if (! $pembukaan) {
            return ['ok' => false, 'pesan' => 'Pembukaan tidak ditemukan.'];
        }
        if ($pembukaan->Status_Publish !== 'TERBIT') {
            return ['ok' => false, 'pesan' => 'Pembukaan ini belum terbit.'];
        }
        // Masa berlaku: EVERGREEN selalu buka; BERBATAS harus dalam rentang
        // tanggal+JAM (window presisi sampai menit).
        if ($pembukaan->Masa_Berlaku === 'BERBATAS') {
            $kini = now();
            if ($pembukaan->Tanggal_Buka && $kini->lt(\Illuminate\Support\Carbon::parse($pembukaan->Tanggal_Buka))) {
                return ['ok' => false, 'pesan' => 'Pendaftaran belum dibuka.'];
            }
            if ($pembukaan->Tanggal_Tutup && $kini->gt(\Illuminate\Support\Carbon::parse($pembukaan->Tanggal_Tutup))) {
                return ['ok' => false, 'pesan' => 'Pendaftaran sudah ditutup.'];
            }
        }

        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $pembukaan->Program_Id)->first();
        if (! $program || $program->Status !== 'BERJALAN') {
            return ['ok' => false, 'pesan' => 'Program ini sedang tidak menerima lamaran.'];
        }
        $programId = $program->Id_Program;

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Id_Program_Posisi', $posisiId)
            ->where('Program_Id', $programId)
            ->first();
        if (! $posisi) {
            return ['ok' => false, 'pesan' => 'Posisi tidak ditemukan pada program ini.'];
        }
        if (($posisi->Status ?? 'BUKA') !== 'BUKA') {
            return ['ok' => false, 'pesan' => 'Posisi ini sudah tidak menerima pelamar.'];
        }

        // Satu kandidat tidak boleh melamar posisi yang sama dua kali — apa pun
        // channel-nya (constraint DB: Users + Program + Posisi).
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->where('Program_Id', $programId)
            ->where('Program_Posisi_Id', $posisiId)
            ->first();
        if ($sudah) {
            return ['ok' => true, 'pesan' => 'Anda sudah melamar posisi ini.', 'lamaranId' => $sudah->Id_Lamaran];
        }

        // ── KELAYAKAN (aturan jalur MT/REKRUTMEN + cooldown, master DB) ──
        // Dinilai berdasarkan RIWAYAT akun. Blok sebelum lamaran dibuat.
        $kelayakan = (new KelayakanLamaran())->cek($userId, $program->Kategori);
        if (! $kelayakan['boleh']) {
            return ['ok' => false, 'pesan' => $kelayakan['alasan'], 'kode' => $kelayakan['kode'] ?? 'TIDAK_LAYAK'];
        }

        // Alur seleksi yang BERLAKU SAAT MELAMAR — di-snapshot supaya perubahan
        // alur program tidak mengacak proses kandidat yang sudah berjalan.
        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $tahap = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alur->Id_Master_Alur)
                ->orderBy('Urutan')
                ->get()
            : collect();

        $kode = 'LMR-' . strtoupper(Str::random(8));
        $now = now();
        $nama = session('career_auth.nama', 'KANDIDAT');

        $tahapPertama = $tahap->values()->first();
        $labelTahap1 = $tahapPertama->Label ?? 'Seleksi Administrasi';

        // Bila jawaban formulir pendaftaran ikut dikirim & tahap pertama menuntut
        // formulir → SERVER yang menilai syarat (bukan flag gugur dari klien). Tahap
        // pertama dibuat BERJALAN dulu supaya bisa diproses syarat engine setelahnya.
        $pakaiSyaratServer = $jawaban !== null && $tahapPertama && ! empty($tahapPertama->Formulir_Kode);
        if ($pakaiSyaratServer) {
            $gugurAlasan = null;
        }

        $lamaranId = DB::transaction(function () use ($pembukaan, $program, $posisi, $alur, $tahap, $userId, $userAdminId, $kode, $now, $nama, $gugurAlasan, $labelTahap1) {
            $gugur = $gugurAlasan !== null && $gugurAlasan !== '';
            $id = DB::table('N_WEB_CAREERS_Lamaran')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $userId,
                'Kategori' => $program->Kategori,
                'Program_Id' => $program->Id_Program,
                'Program_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Mpp_Ref' => $posisi->Mpp_Ref ?? null,
                // Jejak channel apply: dari pembukaan mana kandidat masuk.
                'Pembukaan_Id' => $pembukaan->Id_Pembukaan,
                'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                'Master_Alur_Id' => $alur->Id_Master_Alur ?? null,
                'Urutan_Tahap' => 1,
                'Total_Tahap' => $tahap->count(),
                // Tidak lolos saat finalisasi → langsung GUGUR; selain itu BERJALAN.
                'Status' => $gugur ? 'GUGUR' : 'BERJALAN',
                'Hasil_Akhir' => $gugur ? 'TIDAK_LOLOS' : null,
                'Gugur_Di_Tahap' => $gugur ? $labelTahap1 : null,
                'Waktu_Lamar' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userAdminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userAdminId,
            ], 'Id_Lamaran');

            // Salin tiap tahap alur jadi baris perjalanan. Tahap pertama langsung
            // BERJALAN; sisanya MENUNGGU sampai tahap sebelumnya diputus.
            foreach ($tahap->values() as $i => $t) {
                $lamaranTahapId = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insertGetId([
                    'Lamaran_Id' => $id,
                    'Master_Alur_Tahap_Id' => $t->Id_Master_Alur_Tahap,
                    'Urutan' => $t->Urutan,
                    'Kode' => $t->Kode,
                    'Label' => $t->Label,
                    'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                    'Provider' => $t->Provider,
                    'Keputusan_Mode' => $t->Keputusan ?? null,
                    'Formulir_Kode' => $t->Formulir_Kode,
                    'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode,
                    // Tahap pertama: GUGUR bila knock-out, kalau tidak BERJALAN.
                    'Status' => $i === 0 ? ($gugur ? 'GUGUR' : 'BERJALAN') : 'MENUNGGU',
                    'Rekomendasi' => $i === 0 && $gugur ? 'GUGUR' : null,
                    'Rekomendasi_Alasan' => $i === 0 && $gugur ? $gugurAlasan : null,
                    'Rekomendasi_At' => $i === 0 && $gugur ? $now : null,
                    // Aturan pengumuman ikut dibekukan dari cetakan (Batch 6).
                    'Mode_Pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                    'Flag_Notifikasi' => $t->Flag_Notifikasi ?? 'Y',
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userAdminId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userAdminId,
                ], 'Id_Lamaran_Tahap');

                // Bekukan sub-tes tahap (snapshot) — dipakai mesin keputusan.
                $this->snapshotSubTes($lamaranTahapId, $t->Id_Master_Alur_Tahap, $now, $nama, $userAdminId);
            }

            return $id;
        });

        // ── Snapshot jawaban pendaftaran ke tahap 1 + keputusan OTOMATIS ──
        // Registrasi = gerbang otomatis: syarat terpenuhi → langsung maju ke tahap
        // berikutnya (mis. Psikotes); syarat wajib gagal → GUGUR. Tanpa admin.
        if ($pakaiSyaratServer) {
            $stage1 = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->where('Urutan', 1)
                ->first();

            if ($stage1 && $stage1->Status === 'BERJALAN') {
                $hasilIsi = $this->simpanPengisian($stage1->Id_Lamaran_Tahap, $userId, $jawaban);

                // Bila TIDAK auto-gugur (rekomendasi LOLOS) → loloskan otomatis & maju.
                $stage1Kini = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $stage1->Id_Lamaran_Tahap)->first();
                if ($stage1Kini && $stage1Kini->Status === 'BERJALAN') {
                    $this->tetapkanTahap($stage1->Id_Lamaran_Tahap, 'LULUS', 'Lolos seleksi administrasi otomatis — seluruh syarat terpenuhi.', null, now());

                    return ['ok' => true, 'pesan' => 'Lamaran terkirim. Anda lolos seleksi administrasi dan lanjut ke tahap berikutnya.', 'lamaranId' => $lamaranId];
                }

                // Auto-gugur di tahap administrasi.
                return ['ok' => true, 'pesan' => $hasilIsi['pesan'] ?? 'Lamaran tercatat (tidak lolos).', 'lamaranId' => $lamaranId];
            }
        }

        return ['ok' => true, 'pesan' => $gugurAlasan ? 'Lamaran tercatat (tidak lolos).' : 'Lamaran dibuat.', 'lamaranId' => $lamaranId];
    }

    /**
     * Kandidat mengirim formulir untuk sebuah tahap.
     *
     * @param  array  $jawaban  isi Jawaban_Json (key => nilai)
     * @return array{ok:bool, pesan:string, rekomendasi?:string}
     */
    public function simpanPengisian(int $lamaranTahapId, int $userId, array $jawaban, ?string $ip = null): array
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $tahap) {
            return ['ok' => false, 'pesan' => 'Tahap tidak ditemukan.'];
        }
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->first();
        if (! $lamaran || (int) $lamaran->Id_Users !== $userId) {
            return ['ok' => false, 'pesan' => 'Lamaran bukan milik Anda.'];
        }
        if (! $tahap->Formulir_Kode) {
            return ['ok' => false, 'pesan' => 'Tahap ini tidak menuntut pengisian formulir.'];
        }
        if ($tahap->Status !== 'BERJALAN') {
            return ['ok' => false, 'pesan' => 'Tahap ini tidak sedang berjalan.'];
        }

        $formulir = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Kode', $tahap->Formulir_Kode)->first();
        $now = now();
        $nama = session('career_auth.nama', 'KANDIDAT');

        // Field turunan (usia dari tanggal lahir, jenjang gabungan) — dihitung
        // sistem, lalu digabung ke jawaban untuk dinilai & diproyeksikan.
        $turunan = FieldTurunan::hitung($jawaban);
        $nilai = array_merge($jawaban, $turunan);

        // Syarat milik tahap ini. Yang mode uji tetap dihitung tapi tidak
        // memengaruhi rekomendasi.
        $syaratRows = DB::table('N_WEB_CAREERS_Program_Syarat')
            ->where('Program_Id', $lamaran->Program_Id)
            ->where('Master_Alur_Tahap_Id', $tahap->Master_Alur_Tahap_Id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get();

        $evaluasi = $this->evaluasiSyarat($syaratRows, $nilai);

        $pengisianId = DB::transaction(function () use ($tahap, $lamaran, $formulir, $jawaban, $nilai, $turunan, $evaluasi, $userId, $ip, $now, $nama, $syaratRows) {
            $kode = 'FLL-' . strtoupper(Str::random(8));

            $pengisianId = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $userId,
                'Lamaran_Id' => $lamaran->Id_Lamaran,
                'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
                'Master_Formulir_Id' => $formulir->Id_Master_Formulir ?? null,
                'Komponen_Kode' => $formulir->Komponen_Kode ?? null,
                'Sumber' => $tahap->Urutan == 1 ? 'PENDAFTARAN' : 'TAHAP',
                'Master_Alur_Tahap_Id' => $tahap->Master_Alur_Tahap_Id,
                'Program_Id' => $lamaran->Program_Id,
                'Program_Batch_Id' => $lamaran->Program_Batch_Id,
                'Jawaban_Json' => json_encode($jawaban, JSON_UNESCAPED_UNICODE),
                'Langkah_Terakhir' => 0,
                'Status' => 'TERKIRIM',
                'Waktu_Kirim' => $now,
                'Ip_Pengirim' => $ip,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ], 'Id_Formulir_Pengisian');

            // Proyeksi field yang dipakai syarat + field turunan ke index —
            // supaya bisa dipakai penyaringan massal & audit belakangan.
            $this->proyeksikan($pengisianId, $lamaran, $syaratRows, $nilai, $turunan, $userId);

            // Tempelkan pengisian & rekomendasi mesin ke tahap.
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $tahap->Id_Lamaran_Tahap)
                ->update([
                    'Formulir_Pengisian_Id' => $pengisianId,
                    'Rekomendasi' => $evaluasi['rekomendasi'],
                    'Rekomendasi_Alasan' => $evaluasi['alasan'],
                    'Rekomendasi_At' => $now,
                    'Jejak_Json' => json_encode($evaluasi['jejak'], JSON_UNESCAPED_UNICODE),
                    'Waktu_Mulai' => $tahap->Waktu_Mulai ?? $now,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
                ]);

            // Syarat ber-Aksi GUGUR & bukan mode uji: langsung gugurkan tanpa
            // menunggu admin. Sisanya menunggu ketuk palu.
            // Auto-gugur: yang tersimpan sebagai alasan (dilihat kandidat) adalah
            // pesan HANGAT, bukan detail teknis. Detailnya sudah ada di Jejak_Json
            // untuk ditelaah admin.
            if ($evaluasi['gugurOtomatis']) {
                $this->tetapkanTahap($tahap->Id_Lamaran_Tahap, 'GUGUR', $evaluasi['pesanKandidat'], null, $now);
            }

            return $pengisianId;
        });

        return [
            'ok' => true,
            'pesan' => $evaluasi['gugurOtomatis'] ? ($evaluasi['pesanKandidat'] ?: self::PESAN_GUGUR_BAWAAN) : 'Formulir berhasil dikirim.',
            'rekomendasi' => $evaluasi['rekomendasi'],
            'pengisianId' => $pengisianId,
        ];
    }

    /**
     * Admin memutuskan sebuah tahap (ketuk palu). Lamaran maju ke tahap
     * berikutnya bila LULUS, atau ditutup GUGUR.
     *
     * @return array{ok:bool, pesan:string}
     */
    public function ketukPalu(int $lamaranTahapId, string $hasil, ?string $catatan, ?int $adminId): array
    {
        $hasil = strtoupper($hasil);
        // Tiga keputusan worklist: LULUS (maju), GUGUR (tutup), TALENT_POOL
        // (tidak lolos di lowongan ini tapi disimpan untuk kesempatan berikutnya).
        if (! in_array($hasil, ['LULUS', 'GUGUR', 'TALENT_POOL'], true)) {
            return ['ok' => false, 'pesan' => 'Hasil harus LULUS, GUGUR, atau TALENT_POOL.'];
        }

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $tahap) {
            return ['ok' => false, 'pesan' => 'Tahap tidak ditemukan.'];
        }
        if ($tahap->Status === 'SELESAI') {
            return ['ok' => false, 'pesan' => 'Tahap ini sudah diputus.'];
        }

        // GATE WAJIB UPLOAD: tahap dgn "upload hasil WAJIB" (mis. MCU) tak bisa
        // diloloskan sebelum berkas hasil diunggah.
        if ($hasil === 'LULUS' && ! empty($tahap->Master_Alur_Tahap_Id)) {
            $m = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Id_Master_Alur_Tahap', $tahap->Master_Alur_Tahap_Id)->first();
            $uploadAktif = $m && (($m->Flag_Upload_Hasil ?? 'T') === 'Y' || ($m->Tipe_Tahap_Kode ?? '') === 'MCU');
            if ($m && $uploadAktif && ($m->Flag_Wajib_Upload ?? 'T') === 'Y') {
                $adaBerkas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Lamaran_Tahap_Id', $lamaranTahapId)->exists();
                if (! $adaBerkas) {
                    return ['ok' => false, 'pesan' => 'Tahap ini wajib mengunggah berkas hasil (PDF/JPG) sebelum diloloskan.'];
                }
            }
        }

        // GERBANG KUOTA: LULUS di tahap TERAKHIR = kandidat DITERIMA → menempati
        // kursi. Bila kuota MPP posisi sudah penuh, tolak — arahkan ke Tidak Lolos
        // atau Masuk Talent Pool. Tahap antara (masih ada tahap berikut) tak dibatasi.
        if ($hasil === 'LULUS') {
            $adaBerikut = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $tahap->Lamaran_Id)
                ->where('Urutan', '>', $tahap->Urutan)
                ->exists();
            if (! $adaBerikut) {
                $lam = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->first();
                if ($lam && $lam->Program_Posisi_Id) {
                    $kuota = (int) DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $lam->Program_Posisi_Id)->value('Kuota');
                    if ($kuota > 0) {
                        $terisi = DB::table('N_WEB_CAREERS_Lamaran')
                            ->where('Program_Posisi_Id', $lam->Program_Posisi_Id)
                            ->where('Status', 'LULUS')->count();
                        if ($terisi >= $kuota) {
                            return ['ok' => false, 'pesan' => "Kuota posisi sudah penuh ({$terisi}/{$kuota}). Pilih \"Tidak Lolos\" atau \"Masuk Talent Pool\"."];
                        }
                    }
                }
            }
        }

        $this->tetapkanTahap($lamaranTahapId, $hasil, $catatan, $adminId, now());

        $pesan = [
            'LULUS' => 'Kandidat diloloskan ke tahap berikutnya.',
            'GUGUR' => 'Kandidat digugurkan.',
            'TALENT_POOL' => 'Kandidat dialihkan ke Talent Pool.',
        ][$hasil];

        return ['ok' => true, 'pesan' => $pesan];
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    /**
     * Nilai seluruh syarat sebuah tahap.
     *
     * @return array{rekomendasi:string, alasan:string, jejak:array, gugurOtomatis:bool}
     */
    /** Pesan penolakan bawaan bila admin tidak menuliskannya sendiri. */
    private const PESAN_GUGUR_BAWAAN = 'Terima kasih sudah mendaftar di EVO Group. Setelah kami tinjau, untuk kesempatan kali ini Anda belum memenuhi kualifikasi yang dibutuhkan. Kami sangat menghargai minat & waktu Anda, dan berharap dapat bertemu kembali di kesempatan berikutnya.';

    private function evaluasiSyarat($syaratRows, array $nilai): array
    {
        $jejak = [];
        $teknis = [];          // ringkasan TEKNIS — untuk admin (Rekomendasi_Alasan)
        $pesanKandidat = null; // pesan HANGAT — untuk kandidat (Alasan_Gugur)
        $gugurOtomatis = false;

        foreach ($syaratRows as $s) {
            $aturan = json_decode($s->Aturan_Json ?: '{}', true) ?: [];
            $hasil = MesinSyarat::nilai($aturan, $nilai);
            $uji = $s->Flag_Uji === 'Y';

            $jejak[] = [
                'syarat' => $s->Nama,
                'lolos' => $hasil['lolos'],
                'uji' => $uji,
                'aksi' => $s->Aksi,
                'rincian' => $hasil['jejak'],
            ];

            // Mode uji tidak memengaruhi kandidat sama sekali.
            if ($uji || $hasil['lolos']) {
                continue;
            }

            // Admin melihat alasan teknis (usia = 30, diminta <= 25). Kandidat
            // TIDAK — mereka hanya melihat pesan hangat. Dipisah agar nada ke
            // kandidat tetap sopan dan tidak membocorkan ambang batas.
            $teknis[] = MesinSyarat::ringkas($hasil['jejak']);
            if ($pesanKandidat === null && ! empty($s->Pesan_Gugur)) {
                $pesanKandidat = $s->Pesan_Gugur;
            }
            if ($s->Aksi === 'GUGUR') {
                $gugurOtomatis = true;
            }
        }

        $rekomendasi = $teknis ? 'GUGUR' : 'LOLOS';

        return [
            'rekomendasi' => $rekomendasi,
            'alasan' => $teknis ? implode(' | ', $teknis) : 'Seluruh syarat terpenuhi.',
            'pesanKandidat' => $teknis ? ($pesanKandidat ?: self::PESAN_GUGUR_BAWAAN) : null,
            'jejak' => $jejak,
            'gugurOtomatis' => $gugurOtomatis,
        ];
    }

    /** Tulis nilai field (yang dipakai syarat + turunan) ke index penyaringan. */
    private function proyeksikan(int $pengisianId, $lamaran, $syaratRows, array $nilai, array $turunan, int $userId): void
    {
        // Kumpulkan field yang perlu diindeks: yang dirujuk syarat + semua turunan.
        $dipakai = [];
        foreach ($syaratRows as $s) {
            $aturan = json_decode($s->Aturan_Json ?: '{}', true) ?: [];
            $dipakai = array_merge($dipakai, MesinSyarat::fieldDipakai($aturan));
        }
        $keys = array_values(array_unique(array_merge($dipakai, array_keys($turunan))));

        $now = now();
        foreach ($keys as $key) {
            if (! array_key_exists($key, $nilai)) {
                continue;
            }
            $v = $nilai[$key];
            if (is_array($v)) {
                $v = implode(', ', $v);
            }

            DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->insert([
                'Formulir_Pengisian_Id' => $pengisianId,
                'Lamaran_Id' => $lamaran->Id_Lamaran,
                'Id_Users' => $userId,
                'Program_Id' => $lamaran->Program_Id,
                'Field_Key' => $key,
                'Tipe_Nilai' => is_numeric($v) ? 'ANGKA' : 'TEKS',
                'Nilai_Teks' => (string) $v,
                'Nilai_Angka' => is_numeric($v) ? (float) $v : null,
                'Flag_Turunan' => array_key_exists($key, $turunan) ? 'Y' : 'T',
                'Created_At' => $now,
                'Created_By_Id' => $userId,
            ]);
        }
    }

    /**
     * Tetapkan hasil sebuah tahap dan gerakkan lamaran. Dipakai baik oleh
     * auto-gugur mesin maupun ketuk palu admin.
     */
    private function tetapkanTahap(int $lamaranTahapId, string $hasil, ?string $catatan, ?int $adminId, $now): void
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        $nama = session('career_auth.nama', 'SISTEM');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->update([
            'Status' => 'SELESAI',
            'Hasil' => $hasil,
            'Catatan' => $catatan,
            'Waktu_Selesai' => $now,
            'Diputus_By' => $nama,
            'Diputus_By_Id' => $adminId,
            'Diputus_At' => $now,
            'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
        ]);

        if ($hasil === 'GUGUR') {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'GUGUR',
                'Hasil_Akhir' => 'DITOLAK',
                'Gugur_Di_Tahap' => $tahap->Label,
                'Alasan_Gugur' => $catatan,
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            return;
        }

        // TALENT POOL: kandidat tidak lolos di lowongan ini, tapi cukup baik untuk
        // disimpan. Lamaran ditutup dengan status khusus (bukan GUGUR biasa) dan
        // sebuah kartu Talent Pool dibuat (idempoten — tak menduplikasi lamaran sama).
        if ($hasil === 'TALENT_POOL') {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'TALENT_POOL',
                'Hasil_Akhir' => 'TALENT_POOL',
                'Gugur_Di_Tahap' => $tahap->Label,
                'Alasan_Gugur' => $catatan,
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            $this->simpanKeTalentPool($tahap, $catatan, $adminId, $nama, $now);

            return;
        }

        // LULUS: buka tahap berikutnya, atau tutup lamaran bila ini tahap terakhir.
        $berikut = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $tahap->Lamaran_Id)
            ->where('Urutan', '>', $tahap->Urutan)
            ->orderBy('Urutan')
            ->first();

        if ($berikut) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $berikut->Id_Lamaran_Tahap)->update([
                'Status' => 'BERJALAN',
                'Updated_At' => $now,
            ]);
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Urutan_Tahap' => $berikut->Urutan,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            // REUSE nilai tes yang masih berlaku: bila tahap berikut adalah tes
            // pihak ke-3 (mis. psikotes CAT) & kandidat punya nilai valid dalam masa
            // berlaku (Master Jenis Tes → Masa_Berlaku_Bulan), pakai ulang — tak
            // perlu tes lagi. Nonaktif otomatis bila masa berlaku tak diset (null/0).
            if (($berikut->Provider ?? null) === 'THIRD_PARTY' && ! empty($berikut->Jenis_Tes_Kode)) {
                $userId = (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->value('Id_Users');
                $lama = $this->nilaiTesBerlaku($userId, $berikut->Jenis_Tes_Kode);
                if ($lama) {
                    $tgl = $lama->Waktu_Callback ? \Illuminate\Support\Carbon::parse($lama->Waktu_Callback)->format('d M Y') : '-';
                    $lulusLama = $this->tentukanLulusTes($lama);
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $berikut->Id_Lamaran_Tahap)
                        ->update(['Skor' => $lama->Total_Nilai, 'Updated_At' => $now]);
                    Log::channel('web_career')->info("Reuse nilai {$berikut->Jenis_Tes_Kode} lamaran #{$tahap->Lamaran_Id} → " . ($lulusLama ? 'LULUS' : 'GUGUR') . " (nilai {$lama->Total_Nilai}, {$tgl}).");
                    // Pakai mekanisme yang sama: tetapkan tahap ini otomatis lalu maju.
                    $this->tetapkanTahap((int) $berikut->Id_Lamaran_Tahap, $lulusLama ? 'LULUS' : 'GUGUR',
                        "Nilai {$berikut->Jenis_Tes_Kode} sebelumnya ({$lama->Total_Nilai}, {$tgl}) masih berlaku — dipakai ulang, kandidat tak tes lagi.", $adminId, $now);
                }
            }
        } else {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'LULUS',
                'Hasil_Akhir' => 'DITERIMA',
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);
        }
    }

    /**
     * Nilai tes pihak ke-3 milik kandidat yang MASIH BERLAKU untuk jenis tes ini.
     * Berlaku = ada hasil selesai dalam N bulan terakhir (Master Jenis Tes →
     * Masa_Berlaku_Bulan). Null bila masa berlaku tak diset atau tak ada hasil.
     */
    public function nilaiTesBerlaku(int $userId, string $jenisTesKode): ?object
    {
        if (! $userId || $jenisTesKode === '') {
            return null;
        }
        $bulan = (int) DB::table('N_WEB_CAREERS_Master_Jenis_Tes')->where('Kode', $jenisTesKode)->value('Masa_Berlaku_Bulan');
        if ($bulan <= 0) {
            return null; // masa berlaku tak diset → selalu tes baru (tak reuse)
        }
        $batas = now()->copy()->subMonths($bulan);

        return DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
            ->join('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'pp.Penjadwalan_Tahap_Id')
            ->where('pp.Users_Id', $userId)
            ->where('pt.Jenis_Tes_Kode', $jenisTesKode)
            ->where('pp.Flag_Selesai', 'Y')
            ->whereNotNull('pp.Waktu_Callback')
            ->where('pp.Waktu_Callback', '>=', $batas)
            ->orderByDesc('pp.Waktu_Callback')
            ->select('pp.Total_Nilai', 'pp.Total_Soal', 'pp.Ambang_Batas_Nilai', 'pp.Status_Kelulusan', 'pp.Waktu_Callback')
            ->first();
    }

    /** Simpulkan LULUS/GUGUR dari sebuah hasil tes lama (status teks → nilai vs ambang). */
    private function tentukanLulusTes(object $r): bool
    {
        $teks = strtoupper(trim((string) ($r->Status_Kelulusan ?? '')));
        if (in_array($teks, ['LULUS', 'LOLOS', 'PASS', 'ACCEPT'], true)) {
            return true;
        }
        if (in_array($teks, ['TIDAK LULUS', 'TIDAK_LULUS', 'GAGAL', 'FAIL', 'GUGUR'], true)) {
            return false;
        }
        if ($r->Total_Nilai !== null && $r->Ambang_Batas_Nilai !== null) {
            return (float) $r->Total_Nilai >= (float) $r->Ambang_Batas_Nilai;
        }

        return true; // ada hasil selesai tapi status tak jelas → konservatif: lolos
    }

    /**
     * Simpan kandidat ke Talent Pool saat admin memilih "Masuk Talent Pool".
     * Snapshot ringan (posisi, program, tahap, skor) diambil live agar kartu pool
     * tetap terbaca walau lamaran/posisi berubah. Idempoten per lamaran aktif.
     */
    private function simpanKeTalentPool($tahap, ?string $catatan, ?int $adminId, string $nama, $now): void
    {
        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $tahap->Lamaran_Id)
            ->select('l.Id_Lamaran', 'l.Id_Users', 'l.Program_Id', 'l.Program_Posisi_Id', 'p.Nama as ProgramNama', 'x.Posisi', 'x.Departemen')
            ->first();
        if (! $lam) {
            return;
        }

        // Jangan menduplikasi kartu AKTIF untuk lamaran yang sama.
        $sudahAda = DB::table('N_WEB_CAREERS_Talent_Pool')
            ->where('Lamaran_Id', $tahap->Lamaran_Id)
            ->where('Status', 'AKTIF')
            ->exists();
        if ($sudahAda) {
            return;
        }

        DB::table('N_WEB_CAREERS_Talent_Pool')->insert([
            'Lamaran_Id' => $lam->Id_Lamaran,
            'Id_Users' => $lam->Id_Users,
            'Program_Id' => $lam->Program_Id,
            'Program_Posisi_Id' => $lam->Program_Posisi_Id,
            'Posisi' => $lam->Posisi,
            'Program_Nama' => $lam->ProgramNama,
            'Tahap_Asal' => $tahap->Label,
            'Departemen' => $lam->Departemen ?? null,
            'Skor' => $tahap->Skor ?? null,
            'Tag' => null,
            'Catatan' => $catatan,
            'Status' => 'AKTIF',
            // Masa berlaku dihitung dari Master Masa Talent Pool yang AKTIF (mis. 6 bulan).
            'Tanggal_Masuk' => $now,
            'Tanggal_Kedaluwarsa' => self::hitungKedaluwarsa($now),
            'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
            'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
        ]);
    }

    /**
     * Tanggal kedaluwarsa kartu Talent Pool = sekarang + durasi master AKTIF.
     * Master data-driven (HARI/BULAN/TAHUN); fallback 6 bulan bila belum diset.
     */
    public static function hitungKedaluwarsa($now)
    {
        $dasar = $now instanceof \Carbon\CarbonInterface ? $now->copy() : \Illuminate\Support\Carbon::parse($now);

        $masa = DB::table('N_WEB_CAREERS_Master_Masa_Talent_Pool')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Master_Masa_Talent_Pool')
            ->first();

        $angka = (int) ($masa->Durasi_Angka ?? 6);
        $satuan = strtoupper($masa->Durasi_Satuan ?? 'BULAN');

        return match ($satuan) {
            'HARI' => $dasar->addDays($angka),
            'TAHUN' => $dasar->addYears($angka),
            default => $dasar->addMonths($angka),
        };
    }

    /**
     * Tarik kandidat dari Talent Pool ke lowongan lain (BISA lintas MPP).
     * Membuat lamaran baru di posisi tujuan mengikuti alur program tujuan; tahap
     * sebelum $mulaiDariUrutan ditandai LULUS + bypass (fast-track). Kartu Talent
     * Pool ditandai DITARIK beserta jejak tujuannya.
     *
     * @return array{ok:bool, pesan:string, lamaranId?:int}
     */
    public function tarikDariTalentPool(int $talentPoolId, int $posisiId, int $mulaiDariUrutan, ?int $adminId): array
    {
        $kartu = DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $talentPoolId)->first();
        if (! $kartu) {
            return ['ok' => false, 'pesan' => 'Kartu Talent Pool tidak ditemukan.'];
        }
        if ($kartu->Status === 'DITARIK') {
            return ['ok' => false, 'pesan' => 'Kandidat sudah pernah ditarik dari kartu ini.'];
        }
        if (! $kartu->Id_Users) {
            return ['ok' => false, 'pesan' => 'Data kandidat tidak valid.'];
        }

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')->where('Id_Program_Posisi', $posisiId)->first();
        if (! $posisi) {
            return ['ok' => false, 'pesan' => 'Posisi tujuan tidak ditemukan.'];
        }
        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $posisi->Program_Id)->first();
        if (! $program) {
            return ['ok' => false, 'pesan' => 'Program tujuan tidak ditemukan.'];
        }

        // Cegah tarik ke posisi yang kandidatnya sudah punya lamaran aktif di sana.
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $kartu->Id_Users)
            ->where('Program_Posisi_Id', $posisiId)
            ->where('Status', '!=', 'GUGUR')
            ->exists();
        if ($sudah) {
            return ['ok' => false, 'pesan' => 'Kandidat sudah punya lamaran aktif di posisi tujuan.'];
        }

        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $tahap = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alur->Id_Master_Alur)->orderBy('Urutan')->get()
            : collect();
        if ($tahap->isEmpty()) {
            return ['ok' => false, 'pesan' => 'Program tujuan belum memiliki alur/tahap seleksi.'];
        }

        // Normalisasi titik masuk ke urutan tahap yang valid.
        $urutanValid = $tahap->pluck('Urutan')->map(fn ($u) => (int) $u)->all();
        $mulai = in_array((int) $mulaiDariUrutan, $urutanValid, true) ? (int) $mulaiDariUrutan : min($urutanValid);

        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan')
            ->where('Program_Id', $program->Id_Program)->where('Status_Publish', 'TERBIT')
            ->orderByDesc('Id_Pembukaan')->first();

        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');
        $kode = 'LMR-' . strtoupper(Str::random(8));

        $lamaranId = DB::transaction(function () use ($kartu, $posisi, $program, $pembukaan, $alur, $tahap, $mulai, $kode, $now, $nama, $adminId, $talentPoolId) {
            $id = DB::table('N_WEB_CAREERS_Lamaran')->insertGetId([
                'Kode' => $kode,
                'Id_Users' => $kartu->Id_Users,
                'Kategori' => $program->Kategori,
                'Program_Id' => $program->Id_Program,
                'Program_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Mpp_Ref' => $posisi->Mpp_Ref ?? null,
                'Pembukaan_Id' => $pembukaan->Id_Pembukaan ?? null,
                'Program_Batch_Id' => $pembukaan->Program_Batch_Id ?? null,
                'Master_Alur_Id' => $alur->Id_Master_Alur ?? null,
                'Urutan_Tahap' => $mulai,
                'Total_Tahap' => $tahap->count(),
                'Status' => 'BERJALAN',
                'Asal_Talent_Pool_Id' => $talentPoolId,
                'Mulai_Dari_Urutan' => $mulai,
                'Waktu_Lamar' => $now,
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ], 'Id_Lamaran');

            foreach ($tahap->values() as $t) {
                $bypass = (int) $t->Urutan < $mulai;
                $isEntry = (int) $t->Urutan === $mulai;
                $lamaranTahapId = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insertGetId([
                    'Lamaran_Id' => $id,
                    'Master_Alur_Tahap_Id' => $t->Id_Master_Alur_Tahap,
                    'Urutan' => $t->Urutan,
                    'Kode' => $t->Kode,
                    'Label' => $t->Label,
                    'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                    'Provider' => $t->Provider,
                    'Keputusan_Mode' => $t->Keputusan ?? null,
                    'Formulir_Kode' => $t->Formulir_Kode,
                    'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode,
                    'Status' => $bypass ? 'SELESAI' : ($isEntry ? 'BERJALAN' : 'MENUNGGU'),
                    'Hasil' => $bypass ? 'LULUS' : null,
                    'Catatan' => $bypass ? 'Dilewati — fast-track dari Talent Pool.' : null,
                    'Waktu_Selesai' => $bypass ? $now : null,
                    'Flag_Bypass' => $bypass ? 'Y' : 'T',
                    'Mode_Pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                    'Flag_Notifikasi' => $t->Flag_Notifikasi ?? 'Y',
                    'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                    'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
                ], 'Id_Lamaran_Tahap');

                // Sub-tes hanya dibekukan untuk tahap yang benar-benar dijalani.
                if (! $bypass) {
                    $this->snapshotSubTes($lamaranTahapId, $t->Id_Master_Alur_Tahap, $now, $nama, $adminId);
                }
            }

            DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $talentPoolId)->update([
                'Status' => 'DITARIK',
                'Ditarik_Ke_Lamaran_Id' => $id,
                'Ditarik_Ke_Posisi_Id' => $posisi->Id_Program_Posisi,
                'Ditarik_At' => $now,
                'Ditarik_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            return $id;
        });

        Log::channel('web_career')->info("Talent Pool #{$talentPoolId} ditarik ke posisi #{$posisiId} (lamaran #{$lamaranId}) mulai tahap {$mulai}.");

        return ['ok' => true, 'pesan' => 'Kandidat ditarik ke lowongan tujuan.', 'lamaranId' => $lamaranId];
    }

    // ═══════════════════ MESIN KEPUTUSAN TAHAP (multi-tes) ═══════════════════
    // Satu tahap bisa punya banyak sub-tes. Cara tahap MENYIMPULKAN dibaca dari
    // Master_Mode_Keputusan (kolom perilaku), bukan hardcode. Untuk tahap 1-tes
    // hasilnya identik dengan perilaku lama.

    /** Bekukan sub-tes master → runtime (dipakai saat lamaran dibuat). */
    private function snapshotSubTes(int $lamaranTahapId, int $masterAlurTahapId, $now, string $nama, ?int $adminId): void
    {
        $tes = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
            ->where('Master_Alur_Tahap_Id', $masterAlurTahapId)
            ->orderBy('Urutan')
            ->get();

        foreach ($tes as $x) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->insert([
                'Lamaran_Tahap_Id' => $lamaranTahapId,
                'Master_Alur_Tahap_Tes_Id' => $x->Id_Master_Alur_Tahap_Tes,
                'Urutan' => $x->Urutan,
                'Jenis_Tes_Kode' => $x->Jenis_Tes_Kode,
                'Provider' => $x->Provider,
                'Peran' => $x->Peran,
                'Wajib' => $x->Wajib,
                'Ambang_Dipakai' => $x->Ambang_Batas,
                'Label' => $x->Label,
                'Status' => 'BELUM',
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $adminId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);
        }
    }

    /** Pastikan tahap punya sub-tes (self-heal lamaran lama pra-mesin). */
    private function pastikanSubTes(int $lamaranTahapId): void
    {
        if (DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->exists()) {
            return;
        }
        $t = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $t) {
            return;
        }
        if ($t->Master_Alur_Tahap_Id) {
            $this->snapshotSubTes($lamaranTahapId, (int) $t->Master_Alur_Tahap_Id, now(), 'SISTEM', null);
        }
        // Master tak punya sub-tes (data lama) → buat 1 dari tahap itu sendiri.
        if (! DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->exists()) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->insert([
                'Lamaran_Tahap_Id' => $lamaranTahapId, 'Urutan' => 1,
                'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode, 'Provider' => $t->Provider ?? 'INTERNAL',
                'Peran' => 'PENENTU', 'Wajib' => 'Y', 'Label' => $t->Label, 'Status' => 'BELUM',
                'Created_At' => now(), 'Created_By' => 'SISTEM', 'Updated_At' => now(), 'Updated_By' => 'SISTEM',
            ]);
        }
    }

    /**
     * Rekam hasil satu tes pihak ke-3 (dipanggil callback HCLearn) lalu evaluasi
     * mode tahap. Idempoten via Flag_Selesai per sub-tes.
     *
     * @return array{outcome:string}
     */
    public function rekamHasilTesEksternal(int $lamaranTahapId, ?string $jenisTesKode, string $hasil, ?float $nilai, ?int $totalSoal, ?int $penjadwalanTahapId = null): array
    {
        $this->pastikanSubTes($lamaranTahapId);

        $base = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $lamaranTahapId)
            ->where('Flag_Selesai', 'N');

        // Arahkan ke sub-tes yang cocok: jenis tes → THIRD_PARTY → apa saja.
        $sub = $jenisTesKode ? (clone $base)->where('Jenis_Tes_Kode', $jenisTesKode)->orderBy('Urutan')->first() : null;
        $sub ??= (clone $base)->where('Provider', 'THIRD_PARTY')->orderBy('Urutan')->first();
        $sub ??= (clone $base)->orderBy('Urutan')->first();

        if (! $sub) {
            return ['outcome' => 'NOOP']; // semua sub-tes sudah final (idempoten)
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $sub->Id_Lamaran_Tahap_Tes)->update([
            'Status' => 'SELESAI',
            // Tes INFORMATIF tidak menyatakan lulus — Hasil dibiarkan NULL.
            'Hasil' => $sub->Peran === 'INFORMATIF' ? null : ($hasil === 'LULUS' ? 'LULUS' : 'GAGAL'),
            'Nilai' => $nilai,
            'Total_Soal' => $totalSoal,
            'Penjadwalan_Tahap_Id' => $penjadwalanTahapId,
            'Flag_Selesai' => 'Y',
            'Waktu_Selesai' => now(),
            'Updated_At' => now(),
        ]);

        return $this->evaluasiTahap($lamaranTahapId, null);
    }

    /**
     * MESIN: evaluasi apakah tahap gugur / maju / menunggu / siap diputus,
     * berdasarkan sub-tes yang sudah masuk + Mode_Keputusan. Transisi ATOMIK
     * (lock baris tahap + compare-and-set) supaya callback bersamaan tak maju dobel.
     *
     * @return array{outcome:string}
     */
    public function evaluasiTahap(int $lamaranTahapId, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($lamaranTahapId, $adminId) {
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $lamaranTahapId)
                ->lockForUpdate()
                ->first();
            if (! $tahap || $tahap->Status !== 'BERJALAN') {
                return ['outcome' => 'NOOP'];
            }

            $subs = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $lamaranTahapId)->get();
            if ($subs->isEmpty()) {
                return ['outcome' => 'NOOP'];
            }

            $mode = $this->modeKeputusan((int) $tahap->Master_Alur_Tahap_Id);
            $selesai = fn ($x) => in_array($x->Status, ['SELESAI', 'TIDAK_HADIR'], true);
            $penentu = $subs->where('Peran', 'PENENTU');
            $wajib = $subs->where('Wajib', 'Y');

            // 1) Auto-gugur — ada tes penentu yang selesai & GAGAL.
            $gagal = $penentu->first(fn ($x) => $selesai($x) && $x->Hasil === 'GAGAL');
            if ($mode->Auto_Gugur === 'Y' && $gagal) {
                $this->tetapkanTahap($lamaranTahapId, 'GUGUR', 'Gugur otomatis — gagal pada tes "' . ($gagal->Label ?? '') . '".', $adminId, now());
                $this->tulisJejak($tahap, $mode->Kode, 'GUGUR', 'Auto-gugur: tes penentu gagal.');

                return ['outcome' => 'GUGUR'];
            }

            // 2) Kondisi tunggu.
            $wajibTerakhir = $wajib->sortByDesc('Urutan')->first();
            $tungguOk = $mode->Tunggu === 'SEGERA'
                || ($mode->Tunggu === 'SEMUA' && $wajib->every(fn ($x) => $selesai($x)))
                || ($mode->Tunggu === 'TERAKHIR' && (! $wajibTerakhir || $selesai($wajibTerakhir)));
            if (! $tungguOk) {
                $this->tulisJejak($tahap, $mode->Kode, 'TUNGGU', 'Menunggu sub-tes wajib lain selesai.');

                return ['outcome' => 'TUNGGU'];
            }

            // 3) Syarat lulus.
            if ($mode->Syarat_Lulus === 'SEMUA_PENENTU') {
                if (! $penentu->every(fn ($x) => $selesai($x))) {
                    $this->tulisJejak($tahap, $mode->Kode, 'TUNGGU', 'Menunggu seluruh tes penentu selesai.');

                    return ['outcome' => 'TUNGGU'];
                }
                $semuaLulus = $penentu->every(fn ($x) => $x->Hasil === 'LULUS');
                if ($semuaLulus && $mode->Auto_Lanjut === 'Y') {
                    $this->tetapkanTahap($lamaranTahapId, 'LULUS', 'Lolos otomatis — semua tes penentu lulus.', $adminId, now());
                    $this->tulisJejak($tahap, $mode->Kode, 'LANJUT', 'Semua penentu lulus → maju otomatis.');

                    return ['outcome' => 'LANJUT'];
                }
                $this->tandaiSiapDiputus($lamaranTahapId);
                $this->tulisJejak($tahap, $mode->Kode, 'SIAP_DIPUTUS', $semuaLulus ? 'Semua lulus — menunggu keputusan admin.' : 'Ada tes tidak lulus — menunggu keputusan admin.');

                return ['outcome' => 'SIAP_DIPUTUS'];
            }

            // MANUAL — semua sub-tes selesai, admin yang memutuskan.
            $this->tandaiSiapDiputus($lamaranTahapId);
            $this->tulisJejak($tahap, $mode->Kode, 'SIAP_DIPUTUS', 'Semua sub-tes selesai — menunggu keputusan admin.');

            return ['outcome' => 'SIAP_DIPUTUS'];
        });
    }

    /** Baca mode keputusan tahap; fallback AMAN (MANUAL) bila konfigurasi hilang. */
    private function modeKeputusan(int $masterAlurTahapId): object
    {
        $kode = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Id_Master_Alur_Tahap', $masterAlurTahapId)->value('Mode_Keputusan_Kode');
        $mode = $kode ? DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Kode', $kode)->first() : null;

        return $mode ?: (object) ['Kode' => 'MANUAL_REVIEW', 'Tunggu' => 'SEMUA', 'Auto_Lanjut' => 'N', 'Syarat_Lulus' => 'MANUAL', 'Auto_Gugur' => 'N'];
    }

    /** Tandai tahap siap diputus admin (dibaca worklist di Fase 3). */
    private function tandaiSiapDiputus(int $lamaranTahapId): void
    {
        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->update(['Siap_Diputus' => 'Y', 'Updated_At' => now()]);
    }

    /** Jejak keputusan — audit "kenapa" tiap evaluasi. Tak boleh menggagalkan keputusan. */
    private function tulisJejak(object $tahap, string $modeKode, string $verdict, string $ringkasan): void
    {
        try {
            DB::table('N_WEB_CAREERS_Lamaran_Keputusan_Jejak')->insert([
                'Lamaran_Id' => $tahap->Lamaran_Id ?? null,
                'Lamaran_Tahap_Id' => $tahap->Id_Lamaran_Tahap,
                'Mode_Kode' => $modeKode,
                'Verdict' => $verdict,
                'Ringkasan' => mb_substr($ringkasan, 0, 500),
                'Created_At' => now(),
                'Created_By' => session('career_auth.nama', 'SISTEM'),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Gagal tulis jejak keputusan: ' . $e->getMessage());
        }
    }
}
