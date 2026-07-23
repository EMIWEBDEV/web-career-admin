<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
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
                DB::table('N_WEB_CAREERS_Lamaran_Tahap')->insert([
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
                ]);
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
        if (! in_array($hasil, ['LULUS', 'GUGUR'], true)) {
            return ['ok' => false, 'pesan' => 'Hasil harus LULUS atau GUGUR.'];
        }

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $lamaranTahapId)->first();
        if (! $tahap) {
            return ['ok' => false, 'pesan' => 'Tahap tidak ditemukan.'];
        }
        if ($tahap->Status === 'SELESAI') {
            return ['ok' => false, 'pesan' => 'Tahap ini sudah diputus.'];
        }

        $this->tetapkanTahap($lamaranTahapId, $hasil, $catatan, $adminId, now());

        return ['ok' => true, 'pesan' => $hasil === 'LULUS' ? 'Kandidat diloloskan ke tahap berikutnya.' : 'Kandidat digugurkan.'];
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
        } else {
            DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $tahap->Lamaran_Id)->update([
                'Status' => 'LULUS',
                'Hasil_Akhir' => 'DITERIMA',
                'Waktu_Selesai' => $now,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);
        }
    }
}
