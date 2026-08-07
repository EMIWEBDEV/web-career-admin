<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Career\MasterLokasi\MasterLokasiController;
use App\Jobs\Career\WcApplyEmailJob;
use App\Jobs\Career\WcBiodataHrisJob;
use App\Jobs\Career\WcJadwalEmailJob;
use App\Jobs\Career\WcApplyFormJob;
use App\Jobs\Career\WcLaporanKandidatJob;
use App\Support\Career\AlurKolom;
use App\Support\Career\GcsBerkas;
use App\Support\Career\HtmlBersih;
use App\Support\Career\LamaranService;
use App\Support\Career\LamaranTargetValidator;
use App\Support\Career\PipelineProgress;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — Lamaran (sisi kandidat + worklist admin).
 *
 * Sisi kandidat  : lihat loker, melamar, isi formulir tiap tahap, pantau status.
 * Sisi admin     : worklist tahap yang menunggu keputusan + ketuk palu.
 *
 * Seluruh mutasi berat (snapshot tahap, mesin syarat, gerak lamaran) ada di
 * LamaranService supaya bisa diuji terpisah dari HTTP.
 */
class LamaranController extends Controller
{
    public function __construct(
        private LamaranService $svc,
        private LamaranTargetValidator $targetValidator,
    )
    {
    }

    /**
     * MASTER TIPE TAHAP — satu-satunya sumber "aktivitas ini dijalankan bagaimana".
     *
     * Dikunci per-request supaya tidak dikueri berulang di dalam perulangan
     * tahap/aktivitas. Kolomnya yang menggantikan kode-kode yang dulu ditulis
     * langsung di PHP & Vue:
     *   Perilaku_Kode 'CAT' → ujian online berjadwal (dulu: cek Provider/jenis tes)
     *   Flag_Formulir       → tipe ini menempelkan formulir (dulu: 'FORM'/'DOCUMENT')
     *   Flag_Upload_Hasil   → tipe ini berbasis berkas hasil (dulu: 'MCU')
     *   Pesan_Kandidat      → kalimat yang dibaca kandidat saat menunggu di sini
     */
    private static ?\Illuminate\Support\Collection $tipeCache = null;

    public static function masterTipeTahap(): \Illuminate\Support\Collection
    {
        // SELURUH KOLOM, bukan daftar pilih.
        //
        // Daftar kolom yang ditulis manual di sini sudah dua kali diam-diam
        // membuang kolom baru: `Mode_Jadwal_Bawaan` (bentuk jadwal bawaan tiap
        // tipe) dan `Label_Berkas` sama-sama tersimpan rapi di master lalu
        // hilang sebelum sampai ke layar — tanpa satu pun galat, karena
        // `$tipe->Kolom_Baru ?? null` dengan patuh menghasilkan null.
        //
        // Masternya belasan baris; mengambil semua kolomnya tidak lebih mahal,
        // dan menutup kelas kekeliruan yang tak terlihat sampai ada yang
        // bertanya kenapa setelannya "tidak berfungsi".
        return self::$tipeCache ??= DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->get()
            ->keyBy('Kode');
    }

    private static ?\Illuminate\Support\Collection $modePengumumanCache = null;

    /**
     * Master "kapan hasil boleh dilihat kandidat".
     *
     * Dua kolom yang menentukan, keduanya dari master — bukan dari `if` kode:
     *   Flag_Terbit_Otomatis = 'Y' -> terbit begitu keputusan dibuat;
     *   Butuh_Jeda           = 'Y' -> tertahan sampai Waktu_Diumumkan terlewati;
     *   selain itu                 -> tertahan sampai admin mengisi Waktu_Diumumkan.
     */
    public static function masterModePengumuman(): \Illuminate\Support\Collection
    {
        return self::$modePengumumanCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
            ->get(['Kode', 'Nama', 'Label', 'Ikon', 'Warna', 'Butuh_Jeda', 'Flag_Terbit_Otomatis'])
            ->keyBy('Kode');
    }

    // ═══════════════════════ KANDIDAT ═══════════════════════

    /** /kandidat/loker — daftar posisi yang sedang dibuka. */
    public function loker()
    {
        return Inertia::render('Career/portal/Loker', CareerShell::props('/kandidat/loker', 'Cari Lowongan', [
            'loker' => $this->daftarLoker(),
        ]));
    }

    /** JSON daftar loker — posisi BUKA pada program BERJALAN. */
    public function lokerList()
    {
        return ResponseHelper::success($this->daftarLoker(), 'Daftar loker');
    }

    /**
     * Loker = PEMBUKAAN yang terbit & dalam masa berlaku, dikalikan posisi program-nya.
     * Satu kartu = satu (pembukaan × posisi). Channel UMUM/KAMPUS sudah digabung —
     * tiap program cukup satu pembukaan; pembatasan kampus lewat SYARAT, bukan channel.
     */
    private function daftarLoker(): array
    {
        // Window pendaftaran presisi sampai JAM (kolom kini datetime).
        $kini = now();

        $pembukaan = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('pb.Status_Publish', 'TERBIT')
            ->where('p.Status', 'BERJALAN')
            // EVERGREEN selalu buka; BERBATAS harus dalam rentang tanggal+jam.
            ->where(function ($q) use ($kini) {
                $q->where('pb.Masa_Berlaku', 'EVERGREEN')
                    ->orWhere(function ($w) use ($kini) {
                        $w->where('pb.Masa_Berlaku', 'BERBATAS')
                            ->where(function ($a) use ($kini) {
                                $a->whereNull('pb.Tanggal_Buka')->orWhere('pb.Tanggal_Buka', '<=', $kini);
                            })
                            ->where(function ($b) use ($kini) {
                                $b->whereNull('pb.Tanggal_Tutup')->orWhere('pb.Tanggal_Tutup', '>=', $kini);
                            });
                    });
            })
            ->select('pb.*', 'p.Nama as ProgramNama', 'p.Kategori', 'p.Warna', 'p.Kode as ProgramKode')
            ->get();

        if ($pembukaan->isEmpty()) {
            return [];
        }

        // Posisi BUKA per program, dikelompokkan agar tidak query berulang.
        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->whereIn('Program_Id', $pembukaan->pluck('Program_Id')->unique())
            ->where('Status', 'BUKA')
            ->get()
            ->groupBy('Program_Id');

        $out = [];
        foreach ($pembukaan as $pb) {
            foreach ($posisi->get($pb->Program_Id, []) as $x) {
                $out[] = [
                    'applyId' => 'PB-' . $pb->Kode . '-' . $x->Id_Program_Posisi,
                    'programDetailId' => 'PB-' . $pb->Kode,
                    'pembukaanId' => Hashids::encode($pb->Id_Pembukaan),
                    'posisiId' => Hashids::encode($x->Id_Program_Posisi),
                    'program' => $pb->ProgramNama,
                    'kategori' => $pb->Kategori,
                    'warna' => $pb->Warna,
                    'tutup' => $pb->Masa_Berlaku === 'BERBATAS' ? $pb->Tanggal_Tutup : null,
                    'posisi' => $x->Posisi,
                    'departemen' => $x->Departemen,
                    'lokasi' => $x->Lokasi,
                    'level' => $x->Level ?? null,
                    'kuota' => (int) $x->Kuota,
                ];
            }
        }

        return $out;
    }

    /**
     * POST /api/v1/lamaran — kandidat melamar. MULTIPART (dinamis: field + berkas + foto).
     *
     * Request: validasi → STREAM berkas ke GCS (bukan base64/JSON, aman dari batas
     * ukuran) → simpan payload (form + PATH berkas) di staging → dispatch job.
     * Job: insert DB (lamaran+tahap+pengisian+berkas); bila gagal, berkas GCS dihapus.
     *
     * Fleksibel: dengan berkas atau tanpa berkas sama-sama jalan.
     * Berkas: hanya PDF & JPG, maks 2 MB/berkas. Foto verifikasi: JPG.
     */
    public function lamar(Request $request)
    {
        $userId = (int) session('career_auth.id');
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        try {
            $data = $request->validate([
                'pembukaanId' => 'required|string',
                'posisiId' => 'required|string',
                'gugur' => 'nullable',
                'alasan' => 'nullable|string',
                'jawaban' => 'nullable',
                'berkas' => 'nullable|array',
                'berkas.*' => 'file|max:2048|mimetypes:application/pdf,image/jpeg',
                'foto' => 'nullable|file|max:2048|mimetypes:image/jpeg',
            ], [
                'berkas.*.max' => 'Setiap berkas maksimal 2 MB.',
                'berkas.*.mimetypes' => 'Berkas hanya boleh PDF atau JPG.',
                'foto.max' => 'Foto verifikasi maksimal 2 MB.',
                'foto.mimetypes' => 'Foto verifikasi harus JPG.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Berkas tidak valid.', 422);
        }

        $pembukaanId = Hashids::decode($data['pembukaanId'])[0] ?? null;
        $posisiId = Hashids::decode($data['posisiId'])[0] ?? null;
        if (! $pembukaanId || ! $posisiId) {
            return ResponseHelper::error('Data lamaran tidak valid.', 422);
        }

        // Tolak pasangan pembukaan-posisi yang tidak berhubungan sebelum berkas
        // diunggah. Job memvalidasi ulang jika status berubah selama mengantre.
        $target = $this->targetValidator->validasi((int) $pembukaanId, (int) $posisiId);
        if (! $target['ok']) {
            return ResponseHelper::error($target['pesan'], 422);
        }

        // Cek duplikat lebih awal (umpan balik cepat) — job juga idempoten.
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Users', $userId)
            ->where('Program_Id', $target['program']->Id_Program)
            ->where('Program_Posisi_Id', (int) $posisiId)
            ->exists();
        if ($sudah) {
            return ResponseHelper::error('Anda sudah melamar posisi ini.', 422);
        }

        // ── KELAYAKAN JALUR (aturan MT/REKRUTMEN + cooldown, master DB) ──
        // Feedback INSTAN sebelum unggah berkas (hindari berkas GCS yatim). Job &
        // service tetap cek ulang (defense-in-depth).
        $kategoriProgram = $target['program']->Kategori;
        $kelayakan = (new \App\Support\Career\KelayakanLamaran())->cek($userId, $kategoriProgram);
        if (! $kelayakan['boleh']) {
            return ResponseHelper::error($kelayakan['alasan'], 422);
        }

        // jawaban dikirim sebagai JSON string di multipart.
        $jawaban = $request->input('jawaban');
        if (is_string($jawaban)) {
            $jawaban = json_decode($jawaban, true) ?: [];
        }
        $gugurAlasan = filter_var($request->input('gugur'), FILTER_VALIDATE_BOOLEAN) ? ($data['alasan'] ?? 'Tidak memenuhi syarat wajib.') : null;

        $nama = self::namaDariJawaban($jawaban) ?: session('career_auth.nama', 'Kandidat');
        $processId = (string) Str::uuid();
        $now = now();

        // ── UNGGAH BERKAS KE GCS SAAT REQUEST (stream binary, BUKAN base64/JSON) ──
        // Payload staging hanya menyimpan PATH-nya → tidak ada gambar di JSON, aman
        // dari batas ukuran. Apply fleksibel: tanpa berkas pun tetap jalan.
        $gcs = app(\App\Support\Career\GcsBerkas::class);
        $folder = $gcs->folderKandidat($now->format('Y'), $now->format('m'), $now->format('d'), $nama);
        $terunggah = [];
        $berkasMeta = [];

        try {
            foreach ((array) $request->file('berkas', []) as $field => $file) {
                if (! $file) {
                    continue;
                }
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                $konten = $file->getContent(); // andal (getPathname), bukan getRealPath yang bisa kosong di Apache
                $gcs->validasi($file->getClientOriginalName(), $ext, strlen($konten));
                $path = $gcs->unggah($folder, $field, $ext, $konten);

                $terunggah[] = $path;
                $berkasMeta[] = [
                    'field' => $field,
                    'nama' => $file->getClientOriginalName(),
                    'path' => $path,
                    'ext' => $gcs->normalkanExt($ext),
                    'mime' => $file->getMimeType(),
                    'ukuran' => strlen($konten),
                    'hash' => hash('sha256', $konten),
                ];
            }

            if ($request->hasFile('foto')) {
                $f = $request->file('foto');
                $konten = $f->getContent();
                $path = $gcs->unggahFoto($folder, $konten);
                $terunggah[] = $path;
                $berkasMeta[] = [
                    'field' => 'foto_verifikasi',
                    'nama' => 'verifikasi.jpg',
                    'path' => $path,
                    'ext' => 'jpg',
                    'mime' => $f->getMimeType() ?: 'image/jpeg',
                    'ukuran' => strlen($konten),
                    'hash' => hash('sha256', $konten),
                ];
            }
        } catch (\Throwable $e) {
            // Ada berkas gagal unggah → bersihkan yang sempat masuk, batalkan (tak ada insert).
            $gcs->hapus($terunggah);
            Log::channel('web_career')->error('[APPLY] unggah berkas gagal: ' . $e->getMessage()
                . ' | at ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

            return ResponseHelper::error('Gagal mengunggah berkas: ' . $e->getMessage(), 422);
        }

        try {
            DB::table('N_WEB_CAREERS_Apply_Payload')->insert([
                'Process_Id' => $processId,
                'Users_Id' => $userId,
                'Pembukaan_Id' => (int) $pembukaanId,
                'Program_Posisi_Id' => (int) $posisiId,
                'Nama_Kandidat' => $nama,
                'Tahun' => $now->format('Y'),
                'Bulan' => $now->format('m'),
                'Tanggal' => $now->format('d'),
                'Payload_Json' => json_encode([
                    'userId' => $userId,
                    'pembukaanId' => (int) $pembukaanId,
                    'posisiId' => (int) $posisiId,
                    'jawaban' => $jawaban,
                    'gugurAlasan' => $gugurAlasan,
                ], JSON_UNESCAPED_UNICODE),
                'Berkas_Json' => json_encode($berkasMeta),
                'Status' => 'MENUNGGU',
                'Created_At' => $now, 'Created_By' => $nama, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $userId,
            ]);

            WcApplyFormJob::dispatch($processId);

            Log::channel('web_career')->info("[APPLY] {$processId} diantrikan (wc-applyform) — " . count($berkasMeta) . ' berkas.');

            return ResponseHelper::success([
                'processId' => $processId,
                'mode' => 'ANTRIAN',
            ], 'Lamaran diterima & sedang diproses.', 202);
        } catch (\Throwable $e) {
            // Payload/dispatch gagal setelah berkas terunggah → bersihkan (tak ada yatim).
            $gcs->hapus($terunggah);
            Log::channel('web_career')->error('Gagal mengantrikan lamaran: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses lamaran.', 500);
        }
    }

    /**
     * GET /api/v1/lamaran/apply-status/{processId} — status pemrosesan apply (queue).
     * Dipoll frontend supaya kandidat lihat HASIL NYATA (lolos/gugur), bukan sukses palsu.
     */
    public function applyStatus(string $processId)
    {
        $userId = (int) session('career_auth.id');
        $row = DB::table('N_WEB_CAREERS_Apply_Payload')->where('Process_Id', $processId)->first();
        if (! $row || (int) $row->Users_Id !== $userId) {
            return ResponseHelper::error('Proses tidak ditemukan.', 404);
        }

        $out = ['status' => $row->Status, 'pesan' => $row->Pesan_Error];

        if ($row->Status === 'SELESAI' && $row->Lamaran_Id) {
            $l = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $row->Lamaran_Id)->first();

            // Sudah ada tahap yang benar-benar diputus LULUS?
            // Kalau belum, kandidat masih MENUNGGU keputusan admin — tahap
            // pertamanya bermode manual. Tanpa penanda ini layar hasil akan
            // menyatakan "Lolos Seleksi Administrasi" untuk lamaran yang belum
            // diputus siapa pun. Memakai kolom Hasil (bukan Status) karena saat
            // lolos tahap disimpan Status='SELESAI' + Hasil='LULUS'.
            $adaLulus = $l && DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $l->Id_Lamaran)->where('Hasil', 'LULUS')->exists();

            $tahapKini = $l ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $l->Id_Lamaran)->where('Urutan', $l->Urutan_Tahap)
                ->value('Label') : null;

            $out['lamaran'] = $l ? [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'status' => $l->Status,          // BERJALAN / GUGUR
                'hasilAkhir' => $l->Hasil_Akhir,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'alasanGugur' => $l->Alasan_Gugur,
                'tahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'tahapLabel' => $tahapKini,
                // true → belum ada tahap yang diputus lolos; jangan ucapkan selamat.
                'menungguKeputusan' => $l->Status === 'BERJALAN' && ! $adaLulus,
            ] : null;
        }

        return ResponseHelper::success($out, 'Status lamaran');
    }

    /** DELETE /api/v1/lamaran/{id} — kandidat MENGHAPUS/membatalkan lamarannya sendiri. */
    public function batalkan(string $id)
    {
        $userId = (int) session('career_auth.id');
        if (! $userId) {
            return ResponseHelper::error('Sesi tidak sah.', 401);
        }

        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        // Hanya boleh menghapus lamaran MILIK SENDIRI.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Id_Lamaran', $realId)->where('Id_Users', $userId)->first();
        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        try {
            DB::transaction(function () use ($realId) {
                // Anak-anaknya dulu (urutan aman terhadap referensi).
                $pengisianIds = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->where('Lamaran_Id', $realId)->pluck('Id_Formulir_Pengisian');
                if ($pengisianIds->isNotEmpty()) {
                    DB::table('N_WEB_CAREERS_Formulir_Berkas')->whereIn('Formulir_Pengisian_Id', $pengisianIds)->delete();
                }
                DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Lamaran_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $realId)->delete();
            });

            Log::channel('web_career')->info("Lamaran #{$realId} dibatalkan oleh kandidat #{$userId}");

            return ResponseHelper::success(null, 'Lamaran dibatalkan.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membatalkan lamaran: ' . $e->getMessage());

            return ResponseHelper::error('Gagal membatalkan lamaran.', 500);
        }
    }

    /** /kandidat/portal — lamaran milik kandidat yang sedang login. */
    public function portalIndex()
    {
        $lamaran = $this->lamaranSaya();

        // Statistik REAL untuk strip widget dashboard (bukan hardcode di Vue).
        $stats = [
            'total' => count($lamaran),
            'berjalan' => count(array_filter($lamaran, fn ($l) => $l['status'] === 'BERJALAN')),
            'lulus' => count(array_filter($lamaran, fn ($l) => $l['status'] === 'LULUS')),
            'gugur' => count(array_filter($lamaran, fn ($l) => in_array($l['status'], ['GUGUR', 'MUNDUR'], true))),
        ];

        return Inertia::render('Career/portal/LamaranSaya', CareerShell::props('/kandidat/portal', 'Lamaran Saya', [
            'lamaran' => $lamaran,
            'stats' => $stats,
        ]));
    }

    private function lamaranSaya(): array
    {
        $userId = (int) session('career_auth.id');

        $rows = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Users', $userId)
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Warna', 'x.Posisi', 'x.Lokasi')
            ->get();

        // KARTU sama persis dengan landing (MT & rekrutmen) — dibangun ulang dari
        // sumber yang sama, lalu dicocokkan ke lamaran lewat pembukaan (+posisi).
        $landing = app(\App\Http\Controllers\Career\CareerLandingController::class);
        $petaMt = collect($landing->dbMtCards())->keyBy('pembukaanId');
        $petaRek = collect($landing->dbLowonganCards())->keyBy(fn ($c) => ($c['pembukaanId'] ?? '') . '|' . ($c['posisiId'] ?? ''));

        // TAHAPAN nyata tiap lamaran (nama + status per urutan) — dipakai stepper
        // "PROGRES SELEKSI" pada kartu Lamaran Aktif, sekali kueri untuk semuanya.
        $tahapPeta = collect();
        if ($rows->isNotEmpty()) {
            $tahapPeta = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->whereIn('Lamaran_Id', $rows->pluck('Id_Lamaran')->all())
                ->orderBy('Urutan')
                ->select('Lamaran_Id', 'Urutan', 'Label', 'Status', 'Hasil')
                ->get()
                ->groupBy('Lamaran_Id');
        }

        return $rows->map(function ($l) use ($petaMt, $petaRek, $tahapPeta) {
            $pbHash = $l->Pembukaan_Id ? Hashids::encode($l->Pembukaan_Id) : null;
            $posHash = $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null;
            $kartu = $l->Kategori === 'MT'
                ? ($petaMt[$pbHash] ?? null)
                : ($petaRek[$pbHash . '|' . $posHash] ?? null);

            $tahapan = ($tahapPeta[$l->Id_Lamaran] ?? collect())->map(fn ($t) => [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
            ])->values()->all();

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'kode' => $l->Kode,
                'program' => $l->ProgramNama,
                'posisi' => $l->Posisi,
                'lokasi' => $l->Lokasi,
                'warna' => $l->Warna,
                'kategori' => $l->Kategori,
                'status' => $l->Status,
                'statusLabel' => self::statusKandidat($l->Status)['label'],
                'statusNada' => self::statusKandidat($l->Status)['nada'],
                'hasilAkhir' => $l->Hasil_Akhir,
                'urutanTahap' => (int) $l->Urutan_Tahap,
                'totalTahap' => (int) $l->Total_Tahap,
                'gugurDi' => $l->Gugur_Di_Tahap,
                'waktuLamar' => $l->Waktu_Lamar,
                'tahapan' => $tahapan,
                // Data kartu gaya-landing (bisa null bila pembukaan sudah tak terbit).
                'kartu' => $kartu,
            ];
        })->values()->all();
    }

    /** /kandidat/lamaran/{id} — detail satu lamaran + tahap aktif + formulirnya. */
    public function portalDetail(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Batch as b', 'b.Id_Program_Batch', '=', 'l.Program_Batch_Id')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as pb', 'pb.Id_Pembukaan', '=', 'l.Pembukaan_Id')
            ->where('l.Id_Lamaran', $realId)
            ->where('l.Id_Users', $userId)
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori', 'p.Penyelenggara',
                'x.Posisi', 'x.Lokasi', 'x.Departemen', 'b.Nama as BatchNama', 'pb.Kode as PembukaanKode')
            ->first();

        if (! $lamaran) {
            abort(404);
        }

        // Token ujian pihak ke-3 milik pelamar ini, dikunci per Penjadwalan_Tahap.
        // Cocokkan ke baris peserta lewat lamaran, akun login, atau kode pelamar.
        $tahapRows = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Lamaran_Id', $realId)
            ->orderBy('Urutan')
            ->get();

        // SUB-TES tiap tahap. Satu tahap bisa berisi beberapa aktivitas (mis.
        // Psikotes 2 + DISC + Wawancara) yang dijadwalkan sendiri-sendiri, jadi
        // status "sudah/belum dijadwalkan" hidup di sini, bukan di level tahap.
        $subTesRows = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahapRows->pluck('Id_Lamaran_Tahap')->all())
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        $penjadwalanTahapIds = $tahapRows->pluck('Penjadwalan_Tahap_Id')
            ->merge($subTesRows->flatten(1)->pluck('Penjadwalan_Tahap_Id'))
            ->filter()->unique()->values()->all();

        $ujianByTahap = collect();
        if ($penjadwalanTahapIds) {
            $ujianByTahap = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
                ->join('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'ps.Penjadwalan_Tahap_Id')
                ->whereIn('ps.Penjadwalan_Tahap_Id', $penjadwalanTahapIds)
                ->where(function ($w) use ($realId, $userId) {
                    $w->where('ps.Lamaran_Id', $realId)->orWhere('ps.Users_Id', $userId);
                })
                // Jendela PESERTA menang atas jendela tahap: satu kandidat bisa
                // digeser sendiri (sakit, salah zona waktu) tanpa menggeser
                // seisi angkatan. Kosong → ikut jendela tahap seperti biasa.
                ->select('ps.*', 'pt.Nama_Ujian')
                ->selectRaw('COALESCE(ps.Waktu_Mulai, pt.Waktu_Mulai) as Jendela_Mulai')
                ->selectRaw('COALESCE(ps.Waktu_Akhir, pt.Waktu_Akhir) as Jendela_Akhir')
                ->get()
                ->keyBy('Penjadwalan_Tahap_Id');
        }

        $sekarang = now();

        // Perilaku & kalimat tiap tipe — dipakai portal untuk bicara sesuai
        // aktivitas yang sedang ditunggu, bukan "lamaranmu sedang diproses".
        $tipeTahap = self::masterTipeTahap();

        $bentukUjian = function ($u) use ($sekarang) {
            if (! $u) {
                return null;
            }
            $mulai = $u->Jendela_Mulai ? \Carbon\Carbon::parse($u->Jendela_Mulai) : null;
            $akhir = $u->Jendela_Akhir ? \Carbon\Carbon::parse($u->Jendela_Akhir) : null;
            $dalamJendela = $mulai && $akhir && $sekarang->betweenIncluded($mulai, $akhir);

            return [
                'terjadwal' => (bool) $u->Link_Ujian || (bool) $u->Short_Token,
                'token' => $u->Short_Token,
                'otp' => $u->Akses_OTP,
                'link' => $u->Link_Ujian,
                'namaUjian' => $u->Nama_Ujian,
                'waktuMulai' => optional($mulai)->toIso8601String(),
                'waktuSelesai' => optional($akhir)->toIso8601String(),
                'statusKirim' => $u->Status_Kirim,
                'statusPengerjaan' => $u->Status_Pengerjaan,
                'nilai' => $u->Total_Nilai,
                'kelulusan' => $u->Status_Kelulusan,
                'belumMulai' => $mulai ? $sekarang->lt($mulai) : false,
                'sudahLewat' => $akhir ? $sekarang->gt($akhir) : false,
                'bisaAkses' => $dalamJendela && (bool) $u->Link_Ujian && $u->Status_Pengerjaan !== 'selesai',
            ];
        };

        // Berkas hasil yang diunggah TIM per tahap (MCU, hasil wawancara, dst.).
        // Boleh dilihat kandidat — yang ditahan hanya nilainya, bukan dokumennya.
        $berkasTahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
            ->where('Lamaran_Id', $realId)
            ->orderBy('Id_Lamaran_Tahap_Berkas')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        // Lokasi yang dipakai jadwal tatap muka — diambil sekali, bukan
        // satu kueri per aktivitas.
        $lokasiJadwal = DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->whereIn('Id_Master_Lokasi', $subTesRows->flatten(1)->pluck('Jadwal_Lokasi_Id')->filter()->unique()->all() ?: [0])
            ->get()
            ->keyBy('Id_Master_Lokasi');

        $modePengumuman = self::masterModePengumuman();

        $tahap = $tahapRows->map(function ($t) use ($ujianByTahap, $subTesRows, $tipeTahap, $bentukUjian, $modePengumuman, $sekarang, $berkasTahap, $lokasiJadwal) {
            $u = $t->Penjadwalan_Tahap_Id ? $ujianByTahap->get($t->Penjadwalan_Tahap_Id) : null;
            $ujian = $bentukUjian($u);

            $info = $tipeTahap->get($t->Tipe_Tahap_Kode);

            // TIAP AKTIVITAS punya tipenya sendiri: satu tahap boleh berisi ujian
            // online, tes manual, dan wawancara sekaligus. Yang dikatakan kepada
            // kandidat mengikuti tipe AKTIVITAS, bukan tipe tahap — kalau tidak,
            // sesi wawancara ikut diberi kalimat "menunggu token ujian".
            // AKTIVITAS INTERNAL DISARING DI SINI.
            //
            // Background check, cek referensi, verifikasi ijazah: tim wajib
            // mencatatnya, kandidat tidak punya urusan apa pun dengannya. Baris
            // "Background Check — menunggu" di portal cuma memancing pertanyaan
            // tentang sesuatu yang tak bisa ia kerjakan, dan sekaligus
            // mengumumkan bahwa referensinya sedang dihubungi.
            //
            // Disaring di SERVER, bukan disembunyikan di layar — apa pun yang
            // masuk payload bisa dibaca lewat DevTools.
            $terlihat = collect($subTesRows->get($t->Id_Lamaran_Tahap, []))
                ->filter(fn ($s) => ($s->Tampil_Kandidat ?? 'Y') === 'Y')
                ->values();

            // Giliran pengerjaan pada tahap BERURUTAN. Dihitung dari SELURUH
            // aktivitas (termasuk yang tersembunyi): background check yang belum
            // selesai tetap menahan wawancara sesudahnya, dan kandidat harus
            // melihat wawancaranya belum bisa dimulai — bukan tombol yang
            // ditekan lalu ditolak server.
            $penghalang = self::modeUrutanMengunci($t->Urutan_Aktivitas ?? null)
                ? collect($subTesRows->get($t->Id_Lamaran_Tahap, []))
                    ->sortBy('Urutan')
                    ->first(fn ($s) => ($s->Flag_Selesai ?? 'N') !== 'Y')
                : null;

            $tes = $terlihat->map(function ($s) use ($ujianByTahap, $bentukUjian, $tipeTahap, $t, $lokasiJadwal, $penghalang) {
                $su = $s->Penjadwalan_Tahap_Id ? $ujianByTahap->get($s->Penjadwalan_Tahap_Id) : null;
                $ti = $tipeTahap->get($s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode);
                $online = ($ti->Perilaku_Kode ?? 'MANUAL') === 'CAT';

                return [
                    // Dipakai portal untuk menunggah berkas aktivitas ini.
                    'id' => Hashids::encode($s->Id_Lamaran_Tahap_Tes),
                    'urutan' => (int) $s->Urutan,
                    'label' => $s->Label,
                    'tipe' => $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode,
                    'tipeNama' => $ti->Nama ?? null,
                    'tipeIkon' => $ti->Ikon ?? null,
                    // Kalimat tunggu untuk aktivitas ini — dari master, bukan
                    // peta teks di dalam kode.
                    'pesan' => $ti->Pesan_Kandidat ?? null,
                    'eksternal' => $online,
                    'peran' => $s->Peran,
                    'status' => $s->Status,
                    'hasil' => $s->Hasil,
                    // NILAI DAN CATATAN PENILAI SENGAJA TIDAK DIKIRIM.
                    //
                    // Keduanya bahan penilaian INTERNAL. Catatan aktivitas dulu
                    // ikut terkirim dengan anggapan ia berisi keterangan untuk
                    // kandidat — padahal yang ditulis tim di sana adalah
                    // penilaian ("gugup, tidak direkomendasikan"), dan sejak
                    // catatannya bisa memuat lembar penilaian terpindai,
                    // membocorkannya berarti menyerahkan seluruh rapor internal.
                    //
                    // Yang memang untuk kandidat tetap ada dan tidak berubah:
                    // `jadwal.catatan` (pesan undangan) dan `mcu.catatan` (hasil
                    // kesehatan dirinya sendiri).
                    //
                    // Ditahan di SERVER, bukan disembunyikan di layar: apa pun
                    // yang masuk payload bisa dibaca lewat DevTools.
                    // Aturan unggahan untuk KANDIDAT pada aktivitas ini.
                    // Formatnya ikut dikirim supaya portal menampilkan aturan
                    // yang benar-benar berlaku, bukan aturan umum yang ditebak.
                    'unggah' => ($s->Unggah_Kandidat ?? 'T') === 'Y' ? [
                        'wajib' => ($s->Unggah_Wajib ?? 'T') === 'Y',
                        'format' => array_values(array_filter(array_map('trim', explode(',', (string) ($s->Unggah_Format ?: 'pdf'))))),
                        'maksMb' => (int) ($s->Unggah_Maks_Mb ?: 5),
                        'petunjuk' => $s->Unggah_Petunjuk,
                        // SUDAH DINYATAKAN LENGKAP oleh kandidat sendiri?
                        // Yang membedakan "masih mengunggah" dari "menunggu
                        // dinilai" — dua keadaan yang menuntut hal berbeda dari
                        // kandidat maupun dari tim.
                        'terkirim' => $s->Unggah_Kirim_At ? (string) $s->Unggah_Kirim_At : null,
                    ] : null,
                    // Jadwal tatap muka: kapan, di mana / lewat tautan apa.
                    // Inilah yang dicari kandidat begitu diundang wawancara.
                    // Status MCU boleh dilihat kandidat — itu menyangkut dirinya
                    // sendiri. Penyedia & tanggal ikut supaya ia tahu hasil mana
                    // yang dimaksud bila perlu menanyakannya ke klinik.
                    'mcu' => $s->Mcu_Status ? [
                        'status' => $s->Mcu_Status,
                        // Label dari MASTER — status keempat (Temporary Unfit)
                        // yang baru ditambahkan langsung ikut terbaca, tanpa
                        // jatuh ke `default` dan memuntahkan kode mentah
                        // "TEMPORARY_UNFIT" ke layar kandidat.
                        'label' => self::masterMcuStatus()->get($s->Mcu_Status)->Nama ?? $s->Mcu_Status,
                        'penyedia' => $s->Mcu_Penyedia,
                        'tanggal' => (string) ($s->Mcu_Tanggal ?: ''),
                        'catatan' => $s->Mcu_Catatan,
                    ] : null,
                    'jadwal' => $s->Jadwal_Mulai ? [
                        'mode' => $s->Jadwal_Mode,
                        'daring' => strtoupper((string) $s->Jadwal_Mode) === 'DARING',
                        'mulai' => (string) $s->Jadwal_Mulai,
                        'selesai' => (string) ($s->Jadwal_Selesai ?: ''),
                        'link' => $s->Jadwal_Link,
                        // Nomor yang dijanjikan akan dihubungi — kandidat harus
                        // bisa memastikan nomornya benar sebelum harinya tiba.
                        'kontak' => $s->Jadwal_Kontak ?? null,
                        // Detail yang diketik rekruter ("Gedung B lantai 3").
                        'lokasi' => $s->Jadwal_Lokasi,
                        // Tempatnya sendiri, LENGKAP dengan peta. Kandidat butuh
                        // tahu di mana persisnya — alamat teks menuntut dia
                        // menyalinnya sendiri ke aplikasi peta.
                        //
                        // Berlaku juga untuk tempat yang DIKETIK rekruter (RS
                        // yang belum terdaftar): petanya disusun dari nama +
                        // alamatnya, jadi kandidat tidak menerima undangan yang
                        // lebih miskin hanya karena tempatnya belum sempat
                        // didaftarkan.
                        'tempat' => self::tempatJadwal(
                            $s,
                            $s->Jadwal_Lokasi_Id ? $lokasiJadwal->get($s->Jadwal_Lokasi_Id) : null,
                        ),
                        'catatan' => $s->Jadwal_Catatan,
                    ] : null,
                    'selesai' => $s->Flag_Selesai === 'Y',
                    'butuhJadwal' => $online && $s->Flag_Selesai !== 'Y' && ! $su,
                    // Tahap BERURUTAN: aktivitas ini belum gilirannya. Kandidat
                    // perlu tahu urutannya — kalau tidak, ia melihat tes yang
                    // tak bisa dimulai tanpa satu pun keterangan kenapa.
                    //
                    // Nama penghalangnya TIDAK disebut bila aktivitas itu
                    // internal: menyebut "menunggu Background Check" justru
                    // membocorkan langkah yang sengaja disembunyikan.
                    'terkunci' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes,
                    'menunggu' => $penghalang
                        && $s->Flag_Selesai !== 'Y'
                        && $penghalang->Id_Lamaran_Tahap_Tes !== $s->Id_Lamaran_Tahap_Tes
                        && ($penghalang->Tampil_Kandidat ?? 'Y') === 'Y'
                            ? $penghalang->Label
                            : null,
                    'ujian' => $bentukUjian($su),
                ];
            })->values();

            // Tahap menunggu dijadwalkan bila ada aktivitas ujian online yang
            // belum punya sesi. Aktivitas manual tidak ikut dihitung.
            $butuhJadwal = ! $ujian && $tes->contains(fn ($x) => $x['butuhJadwal']);

            // BOLEHKAH hasil tahap ini diperlihatkan kepada kandidat?
            //
            // Keputusan admin dan pengumuman kepada kandidat adalah dua hal
            // berbeda: admin bisa saja sudah mengetuk palu sementara hasilnya
            // baru boleh dibuka serentak nanti. Aturannya diambil dari Master
            // Mode Pengumuman, jadi mengubah kebijakan cukup lewat data.
            $mp = $modePengumuman->get(strtoupper((string) ($t->Mode_Pengumuman ?: 'OTOMATIS')));
            $diumumkan = $t->Waktu_Diumumkan ? \Carbon\Carbon::parse($t->Waktu_Diumumkan) : null;

            $terbit = ($mp->Flag_Terbit_Otomatis ?? 'N') === 'Y'
                || (($mp->Butuh_Jeda ?? 'N') === 'Y'
                    ? ($diumumkan && $sekarang->gte($diumumkan))
                    : (bool) $diumumkan);

            $hasilTampil = (bool) $t->Hasil && $terbit;

            return [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'tipeNama' => $info->Nama ?? null,
                'tipeIkon' => $info->Ikon ?? null,
                // 'CAT' = ujian online berjadwal; 'MANUAL' = diatur/dinilai tim.
                'perilaku' => $info->Perilaku_Kode ?? 'MANUAL',
                // Kalimat tunggu bawaan tahap (dipakai bila aktivitasnya tunggal).
                'pesan' => $info->Pesan_Kandidat ?? null,
                'provider' => $t->Provider,
                'formulir' => $t->Formulir_Kode,
                'status' => $t->Status,
                // Ditahan di SERVER, bukan disembunyikan di CSS: hasil yang belum
                // boleh diumumkan tidak dikirim sama sekali ke browser kandidat.
                'hasil' => $hasilTampil ? $t->Hasil : null,
                'skor' => $hasilTampil ? $t->Skor : null,
                'catatan' => $t->Catatan,
                'waktuMulai' => optional($t->Waktu_Mulai)->__toString(),
                'waktuSelesai' => optional($t->Waktu_Selesai)->__toString(),
                'sudahIsi' => (bool) $t->Formulir_Pengisian_Id,
                'butuhJadwal' => $butuhJadwal,
                // Setelah tes dikerjakan, yang ditunggu kandidat berbeda-beda:
                //   otomatis  → sistem langsung memutuskan, tak ada jeda manusia;
                //   manual    → hasil masuk lalu ditinjau tim sebelum diumumkan.
                // Tanpa dua penanda ini, kandidat yang sudah selesai tes hanya
                // melihat layar yang sama dengan yang belum — dan mengira bisa
                // (atau harus) mengulang tesnya.
                'otomatis' => strtoupper((string) ($t->Keputusan_Mode ?? 'MANUAL')) === 'SYSTEM',
                'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                // PENAWARAN: tahap ini menuntut JAWABAN kandidat, bukan sekadar
                // menunggu tim. Tanpa penanda ini portal hanya berkata "tim akan
                // menghubungimu" — dan kandidat yang sudah memegang penawaran
                // tidak punya cara menyatakan menerima atau mundur.
                'penawaran' => ($info->Flag_Penawaran ?? 'T') === 'Y',
                // `penawaranDiajukan` dan `tanggapan` TIDAK LAGI DIKIRIM ke
                // portal: keduanya hanya melayani tombol "Terima / Mundur" yang
                // sudah dicabut. Apa pun yang masuk muatan bisa dibaca lewat
                // DevTools, jadi data yang tak lagi dipakai layar sebaiknya
                // tidak ikut keluar sama sekali.
                //
                // Worklist admin tetap menerima keduanya lewat muatannya
                // sendiri — jawaban lama yang pernah tercatat tidak hilang.
                // Keputusan tahap — hanya diberikan bila memang sudah boleh
                // diumumkan. Bila belum, `hasil` di atas TIDAK dipakai portal.
                'hasilTampil' => $hasilTampil,
                // Berkas hasil tahap. Hanya ikut bila hasilnya sudah boleh
                // diumumkan — dokumen penilaian tidak boleh mendahului keputusan.
                'berkas' => $hasilTampil
                    ? collect($berkasTahap->get($t->Id_Lamaran_Tahap, []))->map(function ($b) {
                        $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

                        return [
                            'nama' => $b->Nama_File,
                            'ext' => $ext,
                            'ukuran' => (int) $b->Ukuran,
                            'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                            'url' => route('career.portal.tahap.berkas', ['id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas)]),
                        ];
                    })->values()
                    : [],
                'diputusAt' => optional($t->Diputus_At)->__toString(),
                'menungguPengumuman' => (bool) $t->Hasil && ! $terbit,
                'pengumuman' => $mp ? [
                    'kode' => $mp->Kode,
                    'nama' => $mp->Nama,
                    'label' => $mp->Label,
                    'ikon' => $mp->Ikon,
                    'warna' => $mp->Warna,
                    'tanggal' => optional($diumumkan)->toIso8601String(),
                ] : null,
                'ujian' => $ujian,
                'tes' => $tes,
            ];
        })->values();

        // Tahap aktif yang menuntut formulir & belum diisi -> kandidat kerjakan.
        $aktif = DB::table('N_WEB_CAREERS_Lamaran_Tahap as t')
            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
            ->where('t.Lamaran_Id', $realId)
            ->where('t.Status', 'BERJALAN')
            ->whereNotNull('t.Formulir_Kode')
            ->whereNull('t.Formulir_Pengisian_Id')
            ->orderBy('t.Urutan')
            ->select('t.Id_Lamaran_Tahap', 't.Label', 't.Formulir_Kode', 't.Formulir_Komponen', 't.Formulir_Versi',
                'f.Nama as FormulirNama', 'f.Komponen_Kode')
            ->first();

        // Schema dikunci ke versi yang dibekukan saat tahap dibuat (bila ada),
        // supaya kandidat yang sedang mengisi tidak tiba-tiba mendapat schema
        // dinamis versi baru gara-gara Master Formulir disunting di tengah jalan.
        $schemaAktif = $aktif
            ? \App\Support\Career\FormulirSchema::byKodeDanVersi(
                $aktif->Formulir_Kode,
                $aktif->Formulir_Versi !== null ? (int) $aktif->Formulir_Versi : null
            )
            : null;
        $tugas = $aktif ? [
            'tahapId' => Hashids::encode($aktif->Id_Lamaran_Tahap),
            'label' => $aktif->Label,
            'formulir' => $aktif->Formulir_Kode,
            'formulirNama' => $aktif->FormulirNama,
            // KOMPONEN YANG DIBEKUKAN, master hanya cadangan untuk lamaran lama.
            //
            // Ini yang menentukan pertanyaan mana yang muncul di layar kandidat.
            // Dulu selalu dibaca hidup-hidup dari master, sehingga admin yang
            // mengarahkan Master Formulir ke komponen versi baru langsung
            // mengubah formulir orang yang sudah berjalan berminggu-minggu —
            // termasuk yang tinggal menekan kirim.
            'komponen' => $aktif->Formulir_Komponen ?: ($schemaAktif['komponen'] ?? $aktif->Komponen_Kode),
            'schema' => $schemaAktif['schema'] ?? null,
            'versiId' => $schemaAktif['versiId'] ?? null,
            'versi' => $schemaAktif['versi'] ?? null,
        ] : null;

        // KONTEKS FORMULIR — opsi yang memang PENDEK dan khusus lamaran ini.
        //
        // Daftar kampus TIDAK lagi dikirim dari sini. Master Kampus berisi
        // 328.998 baris hasil impor Dapodik/PDDIKTI (±7,8 MB JSON) — mengirimnya
        // di setiap pembukaan halaman membuat halaman berat tanpa guna, karena
        // kandidat hanya memilih satu. Field bertipe `referensi` sekarang
        // mencarinya sendiri ke /api/v1/referensi/{sumber} sambil mengetik.
        //
        // Pembatasan kampus mitra (bila perlu) tetap lewat SYARAT auto-gugur
        // (operator ADA_DI) di Program Kegiatan, bukan lewat daftar opsi.
        //
        // Dicetak sebagai objek, bukan array: array PHP kosong menjadi `[]` di
        // JSON, sedangkan prop `konteks` di sisi Vue bertipe Object.
        $konteks = (object) [];

        // FORMULIR & BERKAS yang SUDAH kandidat kirim (panel "Formulir & Berkas Saya").
        $formulir = $this->formulirTerkirim($realId, '/kandidat/lamaran/berkas/file/');

        // URL detail lowongan/program publik (tombol "Lihat Detail Lowongan").
        // Kartu landing ber-id 'PB-{PembukaanKode}[-{Program_Posisi_Id}]'.
        $lowonganUrl = null;
        if ($lamaran->PembukaanKode) {
            $lowonganUrl = $lamaran->ProgramKategori === 'MT'
                ? '/karir/landing-page/mt/PB-' . $lamaran->PembukaanKode
                : '/karir/landing-page/lowongan/PB-' . $lamaran->PembukaanKode . '-' . $lamaran->Program_Posisi_Id;
        }

        // KARTU lowongan (info kaya: tanggung jawab, syarat, skill, benefit, tipe
        // kerja, tempat kerja, pengalaman) — sumber sama dengan landing/MPP.
        $kartu = null;
        try {
            $landing = app(\App\Http\Controllers\Career\CareerLandingController::class);
            if ($lamaran->ProgramKategori === 'MT') {
                $pbHash = $lamaran->Pembukaan_Id ? Hashids::encode($lamaran->Pembukaan_Id) : null;
                $kartu = collect($landing->dbMtCards())->firstWhere('pembukaanId', $pbHash);
            } else {
                $pbHash = $lamaran->Pembukaan_Id ? Hashids::encode($lamaran->Pembukaan_Id) : null;
                $posHash = $lamaran->Program_Posisi_Id ? Hashids::encode($lamaran->Program_Posisi_Id) : null;
                $kartu = collect($landing->dbLowonganCards())->first(fn ($c) => ($c['pembukaanId'] ?? null) === $pbHash && ($c['posisiId'] ?? null) === $posHash);
            }
        } catch (\Throwable $e) {
            $kartu = null;
        }

        $akun = DB::table('N_WEB_CAREERS_Users')
            ->where('Id_Users', $userId)
            ->first(['Nama', 'Email', 'No_Hp']);

        return Inertia::render('Career/portal/LamaranDetail', CareerShell::props('/kandidat/portal', 'Detail Lamaran', [
            'lamaran' => [
                'id' => $id,
                'kode' => $lamaran->Kode,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->ProgramKategori,
                'penyelenggara' => $lamaran->Penyelenggara,
                'posisi' => $lamaran->Posisi,
                'lokasi' => $lamaran->Lokasi,
                'departemen' => $lamaran->Departemen,
                'batch' => $lamaran->BatchNama,
                'status' => $lamaran->Status,
                // Kata & nada yang dipakai layar — dari master, bukan peta
                // literal di dalam Vue yang selalu ketinggalan satu hasil.
                'statusLabel' => self::statusKandidat($lamaran->Status)['label'],
                'statusNada' => self::statusKandidat($lamaran->Status)['nada'],
                'urutanTahap' => (int) $lamaran->Urutan_Tahap,
                'totalTahap' => (int) $lamaran->Total_Tahap,
                'hasilAkhir' => $lamaran->Hasil_Akhir,
                'gugurDi' => $lamaran->Gugur_Di_Tahap,
                'alasanGugur' => $lamaran->Alasan_Gugur,
                'waktuLamar' => optional($lamaran->Waktu_Lamar)->__toString(),
            ],
            'tahap' => $tahap,
            'tugas' => $tugas,
            'konteks' => $konteks,
            'formulir' => $formulir,
            'lowonganUrl' => $lowonganUrl,
            'kartu' => $kartu,
            // Profil untuk prefill field bertipe "terisi otomatis".
            // Prefill formulir diambil dari TABEL akun, bukan dari session.
            // Session hanya berisi apa yang sempat ditaruh saat login — `hp`
            // tidak pernah ada di sana, sehingga "No. WhatsApp Terdaftar" di
            // formulir selalu tampil kosong padahal datanya ada di akun.
            //
            // `kampus` datang dari jawaban formulir PENDAFTARAN lamaran ini —
            // kandidat sudah memilihnya dari Master Kampus saat melamar, jadi
            // formulir tahap berikutnya cukup menampilkannya kembali (terkunci)
            // alih-alih menanyakan ulang dan berisiko dapat dua jawaban berbeda.
            'profil' => [
                'nama' => $akun->Nama ?? session('career_auth.nama'),
                'email' => $akun->Email ?? session('career_auth.email'),
                'hp' => $akun->No_Hp ?? session('career_auth.hp'),
                // Kampus & tahun lulus DITARIK DARI JAWABAN FORMULIR PENDAFTARAN
                // lamaran ini — bukan dari tabel akun. Keduanya sudah dijawab
                // kandidat saat melamar; menanyakannya lagi di formulir tahap
                // berikutnya membuka peluang dua jawaban berbeda untuk orang
                // yang sama, dan tim tidak punya cara tahu mana yang benar.
                ...array_intersect_key(
                    LamaranService::dataKandidatEmail($realId),
                    array_flip(['kampus', 'tahunLulus']),
                ),
            ],
        ]));
    }

    /**
     * Bentuk daftar formulir yang SUDAH dikirim untuk sebuah lamaran (jawaban +
     * berkas). $urlBerkasPrefix menentukan basis URL berkas (admin vs kandidat).
     */
    private function formulirTerkirim(int $lamaranId, string $urlBerkasPrefix): array
    {
        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $lamaranId)
            ->orderBy('t.Urutan')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel')
            ->get();

        $berkasPer = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $pengisian->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        return $pengisian->values()->map(function ($fp, $i) use ($berkasPer, $urlBerkasPrefix) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];

            $berkas = collect($berkasPer->get($fp->Id_Formulir_Pengisian, []))->map(function ($b) use ($urlBerkasPrefix) {
                $ext = strtolower($b->Ekstensi ?: pathinfo($b->Nama_Asli, PATHINFO_EXTENSION));

                return [
                    'field' => $b->Field_Key,
                    'nama' => $b->Nama_Asli,
                    'url' => url($urlBerkasPrefix . Hashids::encode($b->Id_Formulir_Berkas)),
                    'ext' => $ext,
                    'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'ukuran' => (int) $b->Ukuran_Byte,
                    'status' => $b->Status_Verifikasi,
                ];
            })->values();

            return [
                'no' => $i + 1,
                'urutan' => (int) ($fp->TahapUrutan ?? 0),
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'sumber' => $fp->Sumber,
                // Kode komponen skema (FORMULIR_1/2/…) — dipakai layar untuk
                // mencari LABEL ASLI tiap isian. Tanpa ini label cuma bisa
                // ditebak dari nama kuncinya, dan `v_nama`/`v_wa` terbaca
                // "V Nama"/"V Wa": bahasa mesin, bukan bahasa manusia.
                'komponen' => $fp->Komponen_Kode,
                // String mentah dari SQL Server: optional()->__toString()
                // di atasnya menghasilkan NULL, sehingga formulir yang jelas
                // sudah dikirim tetap dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Jawaban yang ternyata BERKAS dibawa berikut url/tipe berkasnya.
                // Tanpa ini nama berkas hanya tampil sebagai teks mati di daftar
                // isian, padahal dokumennya ada dan seharusnya bisa dibuka.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    $b = $berkas->firstWhere('field', $k);
                    // Satu aturan untuk seluruh bentuk jawaban — termasuk field
                    // berulang yang dulu menjatuhkan halaman ini. Lihat nilaiIsian().
                    $isi = self::nilaiIsian($v);

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => $isi['nilai'],
                        'baris' => $isi['baris'],
                        'berkas' => $b ? [
                            'field' => $b['field'],
                            'nama' => $b['nama'],
                            'url' => $b['url'],
                            'isImage' => $b['isImage'],
                            'isPdf' => $b['isPdf'],
                        ] : null,
                    ];
                })->values(),
                'berkas' => $berkas,
            ];
        })->all();
    }

    /**
     * Satu jawaban formulir → bentuk yang bisa DIBACA ORANG.
     *
     * ══ KENAPA ADA ══
     *
     * Sebelumnya jawaban dirapikan langsung di tempat, dengan satu baris yang
     * sama persis disalin di DUA layar:
     *
     *     is_array($v) ? implode(', ', $v) : (is_bool($v) ? … : $v)
     *
     * Baris itu benar hanya selama isinya DATAR. Begitu formulir memakai field
     * BERULANG — riwayat kerja, organisasi, sertifikasi, daftar kenalan — tiap
     * barisnya adalah OBJEK berisi beberapa sub-isian, dan implode() tidak bisa
     * memampatkan objek jadi teks: PHP melempar "Array to string conversion"
     * dan SELURUH halaman detail lamaran mati. Kandidat tidak bisa membuka
     * lamarannya sendiri hanya karena ia mengisi riwayat kerjanya.
     *
     * Bahkan seandainya tidak melempar, hasilnya "Array, Array" — sama tidak
     * bergunanya.
     *
     * SATU TEMPAT, bukan dua salinan: layar kandidat dan worklist admin
     * menampilkan jawaban yang sama, dan dua salinan aturan pasti berselisih —
     * yang satu diperbaiki, yang lain tertinggal, dan selisihnya baru ketahuan
     * saat ada yang membandingkan dua layar itu berdampingan.
     *
     * @return array{nilai: string, baris: array} `baris` hanya terisi untuk
     *         field berulang, supaya layar bisa menampilkannya sebagai daftar
     *         alih-alih satu paragraf panjang.
     */
    private static function nilaiIsian(mixed $v, int $dalam = 0): array
    {
        if ($v === null) {
            return ['nilai' => '', 'baris' => []];
        }

        if (is_bool($v)) {
            return ['nilai' => $v ? 'Ya' : 'Tidak', 'baris' => []];
        }

        if ($v instanceof \stdClass) {
            $v = (array) $v;
        }

        if (! is_array($v)) {
            return ['nilai' => trim((string) $v), 'baris' => []];
        }

        // Pagar kedalaman: jawaban formulir tak pernah bersarang sedalam ini,
        // dan tanpa pagar satu data rusak bisa membuat halaman berputar
        // sampai kehabisan memori — kegagalan yang jauh lebih sulit dilacak
        // daripada nilai yang sekadar tidak tampil.
        if ($dalam > 3) {
            return ['nilai' => '…', 'baris' => []];
        }

        $bersarang = false;
        foreach ($v as $x) {
            if (is_array($x) || $x instanceof \stdClass) {
                $bersarang = true;
                break;
            }
        }

        // ── DATAR: centang berganda / pilihan berganda ───────────────────────
        if (! $bersarang) {
            $isi = [];
            foreach ($v as $x) {
                $t = self::nilaiIsian($x, $dalam + 1)['nilai'];
                if ($t !== '') {
                    $isi[] = $t;
                }
            }

            return ['nilai' => implode(', ', $isi), 'baris' => []];
        }

        // ── BERULANG: satu objek per baris ───────────────────────────────────
        $baris = [];
        foreach ($v as $row) {
            if ($row instanceof \stdClass) {
                $row = (array) $row;
            }

            if (! is_array($row)) {
                $t = self::nilaiIsian($row, $dalam + 1)['nilai'];
                if ($t !== '') {
                    $baris[] = [['label' => '', 'nilai' => $t]];
                }
                continue;
            }

            $awalan = self::awalanBersama(array_keys($row));
            $pasangan = [];

            foreach ($row as $k => $x) {
                $t = self::nilaiIsian($x, $dalam + 1)['nilai'];
                if ($t === '') {
                    // Sub-isian kosong DILEWATI, bukan ditampilkan "—".
                    // Baris riwayat kerja yang uraiannya belum diisi tetap
                    // terbaca utuh; deretan tanda hubung hanya menutupi yang
                    // benar-benar ada.
                    continue;
                }

                $nama = $awalan !== '' && str_starts_with((string) $k, $awalan)
                    ? substr((string) $k, strlen($awalan))
                    : (string) $k;

                $pasangan[] = [
                    'label' => ucwords(str_replace(['_', '-'], ' ', $nama)),
                    'nilai' => $t,
                ];
            }

            if ($pasangan) {
                $baris[] = $pasangan;
            }
        }

        // Ringkasan teks tetap disediakan: dipakai ekspor, pencarian, dan layar
        // lama yang belum membaca `baris`.
        $ringkas = [];
        foreach ($baris as $i => $pasangan) {
            $isi = implode(', ', array_map(fn ($p) => ($p['label'] !== '' ? $p['label'] . ': ' : '') . $p['nilai'], $pasangan));
            $ringkas[] = count($baris) > 1 ? ($i + 1) . ') ' . $isi : $isi;
        }

        return ['nilai' => implode(' | ', $ringkas), 'baris' => $baris];
    }

    /**
     * Awalan yang DIPAKAI BERSAMA seluruh kunci satu baris berulang, mis.
     * `kerja_` pada kerja_perusahaan / kerja_jabatan / kerja_periode.
     *
     * Dibuang dari label supaya terbaca "Perusahaan, Jabatan, Periode" —
     * bukan "Kerja Perusahaan, Kerja Jabatan, Kerja Periode" yang mengulang
     * nama fieldnya di tiap kolom.
     *
     * Syaratnya ketat: minimal dua kunci, seluruhnya berawalan segmen yang
     * sama, dan tiap kunci masih menyisakan sesuatu sesudah awalan itu. Tanpa
     * syarat itu, `nama` dan `nomor` akan terpotong jadi `a` dan `omor`.
     */
    private static function awalanBersama(array $kunci): string
    {
        if (count($kunci) < 2) {
            return '';
        }

        $awal = null;
        foreach ($kunci as $k) {
            $bagian = explode('_', (string) $k);
            if (count($bagian) < 2 || $bagian[0] === '') {
                return '';
            }
            $awal ??= $bagian[0];
            if ($bagian[0] !== $awal) {
                return '';
            }
        }

        return $awal . '_';
    }

    /**
     * Aktivitas milik kandidat yang sedang login, atau null.
     *
     * Kepemilikan diperiksa lewat join ke Lamaran.Id_Users — mengetahui id
     * aktivitas orang lain tidak cukup untuk mengunggah atau membaca berkasnya.
     */
    private function tesMilikSaya(string $id): ?object
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId || ! $userId) {
            return null;
        }

        return DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            ->where('l.Id_Users', $userId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Status as StatusTahap')
            ->first();
    }

    /**
     * Mode urutan ini MENGUNCI aktivitas berikutnya atau tidak?
     *
     * Jawabannya dibaca dari `Master_Mode_Urutan.Flag_Berurutan`, bukan dari
     * membandingkan Kode-nya dengan 'BERURUTAN'. Bedanya baru terasa saat mode
     * ketiga ditambahkan lewat master: dengan perbandingan Kode, mode baru itu
     * diam-diam berperilaku seperti PARALEL di setiap tempat yang lupa diubah.
     *
     * Di-cache per permintaan — dipanggil sekali per aktivitas per baris rapor.
     */
    private static ?\Illuminate\Support\Collection $modeUrutanCache = null;

    private static function modeUrutanMengunci(?string $kode): bool
    {
        self::$modeUrutanCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Urutan')->get()->keyBy('Kode');

        return (self::$modeUrutanCache->get((string) $kode)->Flag_Berurutan ?? 'T') === 'Y';
    }

    /**
     * Mode lanjut ini menuntut pemicu admin?
     *
     * Dibaca dari `Master_Mode_Lanjut.Flag_Butuh_Trigger`, bukan dari
     * membandingkan Kode dengan 'MANUAL' — mode baru lewat master tidak boleh
     * menuntut kode ini ikut diubah.
     *
     * Nilai kosong = OTOMATIS. Itu perilaku sebelum fitur ini ada, dan aturan
     * baru tidak boleh berlaku surut ke aktivitas yang belum disetel.
     */
    private static ?\Illuminate\Support\Collection $modeLanjutCache = null;

    private static function lanjutButuhTrigger(?string $kode): bool
    {
        if (! $kode) {
            return false;
        }

        self::$modeLanjutCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Lanjut')->get()->keyBy('Kode');

        return (self::$modeLanjutCache->get($kode)->Flag_Butuh_Trigger ?? 'T') === 'Y';
    }

    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/lanjutkan — buka aktivitas
     * berikutnya pada tahap BERURUTAN yang ber-mode MANUAL.
     *
     * Inilah perantara yang selama ini tidak ada. Tanpa ini, aktivitas
     * berikutnya terbuka begitu yang sebelumnya selesai — dan kandidat yang
     * nilainya jelas di bawah ambang sudah telanjur diundang ke asesmen
     * berikutnya sebelum ada yang sempat membaca hasilnya.
     */
    public function subTesLanjutkan(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (($sub->Flag_Selesai ?? 'N') !== 'Y') {
            return ResponseHelper::error('Aktivitas ini belum selesai — belum ada hasil yang bisa ditinjau.', 409);
        }

        if (! self::lanjutButuhTrigger($sub->Lanjut_Mode ?? null)) {
            return ResponseHelper::error('Aktivitas ini disetel lanjut otomatis — tidak perlu dilanjutkan manual.', 409);
        }

        if (! empty($sub->Lanjut_At)) {
            return ResponseHelper::error('Aktivitas ini sudah dilanjutkan.', 409);
        }

        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
            'Lanjut_At' => $now,
            'Lanjut_By' => $nama,
            'Lanjut_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
            'Updated_By' => $nama,
        ]);

        Log::channel('web_career')->info("Aktivitas #{$realId} ({$sub->Label}) dilanjutkan oleh {$nama}.");

        return ResponseHelper::success(null, 'Aktivitas berikutnya dibuka.');
    }

    /**
     * GERBANG URUTAN AKTIVITAS.
     *
     * Pada tahap ber-`Urutan_Aktivitas='BERURUTAN'`, hanya aktivitas terdepan
     * yang boleh disentuh: wawancara HR baru masuk akal setelah hasil psikotes
     * keluar, dan menjadwalkannya lebih dulu berarti mengundang orang untuk
     * sesuatu yang mungkin tidak akan terjadi.
     *
     * DITEGAKKAN DI SERVER, bukan cukup dengan menyembunyikan tombolnya.
     * Tombol yang hilang tetap bisa dipanggil lewat DevTools, dan yang lebih
     * sering terjadi: layar basi. Admin yang membuka drawer sebelum rekannya
     * menyelesaikan aktivitas pertama masih memegang daftar tombol versi lama.
     *
     * @return string|null pesan penolakan, atau null bila boleh dikerjakan
     */
    private static function kunciUrutan(object $sub): ?string
    {
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
            ->first(['Urutan_Aktivitas']);

        if (! self::modeUrutanMengunci($tahap->Urutan_Aktivitas ?? null)) {
            return null;
        }

        // Aktivitas SEBELUM ini yang belum membuka jalan. Dua sebab berbeda:
        //
        //   1. belum selesai            → kerjakan dulu
        //   2. selesai tapi ber-mode MANUAL dan belum ditekan "Lanjutkan"
        //      → tim sengaja menahannya untuk membaca hasilnya dulu
        //
        // Keduanya menutup jalan, tapi tindakan yang diminta berbeda — jadi
        // pesannya pun harus berbeda, kalau tidak admin mencari tombol yang
        // salah.
        $sebelum = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $sub->Lamaran_Tahap_Id)
            ->where('Urutan', '<', $sub->Urutan)
            ->orderBy('Urutan')
            ->get(['Label', 'Flag_Selesai', 'Lanjut_Mode', 'Lanjut_At']);

        foreach ($sebelum as $s) {
            if (($s->Flag_Selesai ?? 'N') !== 'Y') {
                return "Tahap ini dikerjakan BERURUTAN. Selesaikan \"{$s->Label}\" lebih dulu.";
            }

            if (self::lanjutButuhTrigger($s->Lanjut_Mode ?? null) && empty($s->Lanjut_At)) {
                return "\"{$s->Label}\" sudah selesai tetapi belum dilanjutkan. Tekan \"Lanjutkan\" pada aktivitas itu dulu.";
            }
        }

        return null;
    }

    /**
     * Status lamaran → kata yang dibaca KANDIDAT + nadanya.
     *
     * Portal dulu hanya mengenal tiga status (BERJALAN / LULUS / GUGUR) lewat
     * peta literal di dalam layar. Sejak hasil keputusan jadi master, Status
     * bisa berisi MENGUNDURKAN_DIRI atau DITOLAK_KANDIDAT — dan keduanya jatuh
     * ke luar peta itu: kandidat yang mundur melihat tulisan `MENGUNDURKAN_DIRI`
     * apa adanya, sementara halaman detailnya menganggap lamaran masih berjalan
     * karena nadanya pun tak ketemu.
     *
     * Diambil dari MASTER, bukan peta baru yang lebih panjang: menambah hasil
     * keputusan berikutnya tidak boleh menuntut layar ikut diubah.
     *
     * Nada dipilih dari flag, BUKAN dari kodenya. "Mengundurkan diri" memang
     * bukan kelulusan, tapi ia juga bukan penolakan — mewarnainya merah seperti
     * GUGUR mengatakan kepada kandidat bahwa ia ditolak, padahal ia yang pergi.
     *
     * @return array{label:string, nada:string}
     */
    private static function statusKandidat(?string $status): array
    {
        $status = strtoupper((string) $status);

        if ($status === 'BERJALAN') {
            return ['label' => 'Berjalan', 'nada' => 'berjalan'];
        }

        $def = LamaranService::masterHasilKeputusan()->get($status);

        if (! $def) {
            return ['label' => $status ?: '—', 'nada' => 'berjalan'];
        }

        if (($def->Flag_Lolos ?? 'T') === 'Y') {
            return ['label' => 'Diterima', 'nada' => 'lolos'];
        }

        // Keputusan yang datang DARI KANDIDAT tidak pernah diwarnai sebagai
        // kegagalan — termasuk saat ia berakhir di Talent Pool.
        if (($def->Flag_Oleh_Kandidat ?? 'T') === 'Y') {
            return ['label' => $def->Nama, 'nada' => 'netral'];
        }

        return [
            'label' => $def->Nama,
            'nada' => ($def->Flag_Talent_Pool ?? 'T') === 'Y' ? 'menunggu' : 'gugur',
        ];
    }

    /** Bentuk satu berkas kandidat untuk dikirim ke layar. */
    private static function bentukTesBerkas(object $b): array
    {
        $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

        return [
            'id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas),
            'nama' => $b->Nama_File,
            'ext' => $ext,
            'ukuran' => (int) $b->Ukuran,
            'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'url' => route('career.portal.tes.berkas.file', ['id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas)]),
            // SUDAH IKUT DIKIRIM = tidak bisa dihapus kandidat lagi. Dikirim
            // per berkas supaya layar menyembunyikan tombol hapus tepat pada
            // yang memang terkunci — bukan mengunci seluruh daftar hanya karena
            // salah satunya sudah diserahkan.
            'terkunci' => ! empty($b->Terkirim_At),
            'terkirim' => ($b->Terkirim_At ?? null) ? (string) $b->Terkirim_At : null,
        ];
    }

    /** GET daftar berkas yang SUDAH diunggah kandidat untuk satu aktivitas. */
    public function tesBerkas(string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
            ->orderBy('Id_Lamaran_Tes_Berkas')
            ->get();

        return ResponseHelper::success($rows->map(fn ($b) => self::bentukTesBerkas($b))->all(), 'Berkas aktivitas');
    }

    /**
     * POST unggah berkas kandidat untuk sebuah aktivitas.
     *
     * Format & ukuran diambil dari aturan yang DIBEKUKAN pada aktivitas ini,
     * bukan aturan umum: tes menggambar menerima gambar, tes tertulis menerima
     * PDF, dan batasnya berbeda-beda. Divalidasi di server juga — layar bisa
     * dilewati, dan berkas raksasa yang lolos membebani bucket diam-diam.
     */
    public function tesBerkasUnggah(Request $request, string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (($tes->Unggah_Kandidat ?? 'T') !== 'Y') {
            return ResponseHelper::error('Aktivitas ini tidak meminta unggahan berkas.', 409);
        }

        if ($tes->Flag_Selesai === 'Y' || $tes->StatusTahap !== 'BERJALAN') {
            return ResponseHelper::error('Aktivitas ini sudah selesai - berkas tidak bisa diubah lagi.', 409);
        }

        // SUDAH DIKIRIM → LANGKAH UNGGAH DITUTUP SEPENUHNYA.
        //
        // "Kirim Berkas" adalah pernyataan bahwa berkasnya LENGKAP. Setelah itu
        // aktivitas ini pindah ke meja penilai, dan apa yang dinilai harus sama
        // persis dengan apa yang dinyatakan kandidat — tidak bertambah, tidak
        // berkurang. Membiarkan berkas menyusup masuk setelah pernyataan berarti
        // penilai bisa membaca lampiran yang belum pernah dinyatakan lengkap,
        // atau selesai menilai lalu isinya berubah di belakangnya.
        //
        // DITAHAN DI SERVER, bukan sekadar kotak seret-lepasnya disembunyikan:
        // pintu ini tetap bisa diketuk langsung tanpa lewat layar.
        if ($tes->Unggah_Kirim_At) {
            return ResponseHelper::error(
                'Berkas untuk aktivitas ini sudah kamu kirim dan sedang dinilai tim — '
                . 'tidak bisa ditambah atau diubah lagi. Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        $format = array_values(array_filter(array_map('trim', explode(',', (string) ($tes->Unggah_Format ?: 'pdf')))));
        $maksMb = (int) ($tes->Unggah_Maks_Mb ?: 5);

        $data = $request->validate([
            'berkas' => 'required|file|mimes:' . implode(',', $format) . '|max:' . ($maksMb * 1024),
        ], [
            'berkas.mimes' => 'Hanya menerima berkas ' . implode(', ', $format) . '.',
            'berkas.max' => "Ukuran berkas melebihi {$maksMb} MB.",
        ]);

        $file = $data['berkas'];
        $gcs = app(GcsBerkas::class);
        $now = now();
        $nama = session('career_auth.nama');

        try {
            // getContent(), BUKAN getRealPath(): di bawah Apache getRealPath()
            // bisa mengembalikan string kosong dan isinya gagal terbaca.
            $konten = $file->getContent();
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

            $folder = $gcs->folderTahap(
                $now->format('Y'), $now->format('m'), $now->format('d'),
                (string) ($nama ?: 'kandidat'),
            );

            $path = $gcs->unggah($folder, $tes->Label . '-' . Str::lower(Str::random(6)), $ext, $konten);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[TES-BERKAS] unggah gagal: ' . $e->getMessage());

            return ResponseHelper::error('Berkas gagal diunggah: ' . Str::limit($e->getMessage(), 140), 500);
        }

        $baruId = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->insertGetId([
            'Lamaran_Tahap_Tes_Id' => $tes->Id_Lamaran_Tahap_Tes,
            'Lamaran_Id' => $tes->Lamaran_Id,
            'Id_Users' => (int) session('career_auth.id'),
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getMimeType(),
            'Ext' => $ext,
            'Ukuran' => strlen($konten),
            'Created_At' => $now,
            'Created_By' => $nama,
            'Created_By_Id' => session('career_auth.id'),
        ], 'Id_Lamaran_Tes_Berkas');

        $baris = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $baruId)->first();

        // Dulu di sini ada blok yang MEMBATALKAN pernyataan "sudah lengkap"
        // setiap kali berkas susulan masuk, supaya penanda di worklist tidak
        // berbunyi "lengkap sejak 09:12" atas data yang sudah berubah.
        //
        // Blok itu tidak diperlukan lagi — dan tidak akan pernah tercapai:
        // gerbang di atas menolak unggahan begitu aktivitasnya dikirim, jadi
        // keadaan "ada susulan setelah pernyataan" mustahil terbentuk. Yang
        // dinilai tim selalu persis yang dinyatakan kandidat.

        return ResponseHelper::success(self::bentukTesBerkas($baris), 'Berkas terunggah.');
    }

    /**
     * PATCH kandidat menyatakan berkasnya SUDAH LENGKAP untuk aktivitas ini.
     *
     * KENAPA PERLU TOMBOL TERSENDIRI
     * Mengunggah dan "selesai mengunggah" bukan hal yang sama. Tanpa pernyataan
     * ini dua pihak sama-sama menebak: kandidat tidak tahu apakah masih ada
     * yang harus dilakukan, dan admin tidak tahu apakah berkas yang masuk sudah
     * lengkap atau baru satu dari tiga. Menilai pekerjaan yang belum lengkap
     * adalah keputusan yang tidak bisa ditarik kembali.
     */
    public function tesBerkasKirim(Request $request, string $id)
    {
        $tes = $this->tesMilikSaya($id);
        if (! $tes) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (($tes->Unggah_Kandidat ?? 'T') !== 'Y') {
            return ResponseHelper::error('Aktivitas ini tidak meminta unggahan berkas.', 409);
        }

        if ($tes->Flag_Selesai === 'Y' || $tes->StatusTahap !== 'BERJALAN') {
            return ResponseHelper::error('Aktivitas ini sudah selesai — berkas tidak bisa diubah lagi.', 409);
        }

        $jumlah = DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
            ->count();

        // Gerbang ini berlaku untuk SEMUA aktivitas berunggahan, bukan hanya
        // yang wajib: menyatakan "berkas saya lengkap" tanpa satu pun berkas
        // adalah pernyataan yang tidak berarti apa-apa, dan admin yang
        // membacanya akan mencari sesuatu yang tidak pernah ada.
        if ($jumlah < 1) {
            return ResponseHelper::error('Belum ada berkas yang diunggah. Unggah dulu, baru tekan kirim.', 422);
        }

        $now = now();
        $nama = session('career_auth.nama');

        DB::transaction(function () use ($tes, $now, $nama, $request) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Id_Lamaran_Tahap_Tes', $tes->Id_Lamaran_Tahap_Tes)
                ->update([
                    'Unggah_Kirim_At' => $now,
                    'Unggah_Kirim_By' => $nama,
                    'Unggah_Kirim_Ip' => Str::limit((string) $request->ip(), 60, ''),
                    'Updated_At' => $now,
                    'Updated_By' => $nama,
                ]);

            // GEMBOK MELEKAT PADA BERKASNYA, bukan hanya pada aktivitasnya.
            //
            // Aktivitasnya sendiri sudah tertutup — tesBerkasUnggah() menolak
            // unggahan apa pun sesudah ini. Stempel per berkas tetap ditulis
            // karena ia menjawab hal yang tidak bisa dijawab kolom aktivitas:
            // KAPAN berkas INI diserahkan, dan bahwa ia memang termasuk yang
            // dinyatakan lengkap. Dari situlah gerbang hapus membaca izinnya,
            // jadi keputusan "boleh dihapus atau tidak" tidak pernah bergantung
            // pada satu kolom yang letaknya jauh dari berkasnya.
            //
            // `whereNull` menjaga stempel PERTAMA tetap utuh — itulah saat
            // berkas ini benar-benar diserahkan.
            DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
                ->where('Lamaran_Tahap_Tes_Id', $tes->Id_Lamaran_Tahap_Tes)
                ->whereNull('Terkirim_At')
                ->update(['Terkirim_At' => $now]);
        });

        return ResponseHelper::success(
            ['waktu' => $now->toDateTimeString(), 'jumlah' => $jumlah],
            "Berkas kamu sudah dikirim ({$jumlah} berkas). Tim rekrutmen akan menilainya."
        );
    }

    /** DELETE berkas kandidat - selama aktivitasnya belum selesai. */
    public function tesBerkasHapus(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas as b')
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as t', 't.Id_Lamaran_Tahap_Tes', '=', 'b.Lamaran_Tahap_Tes_Id')
            ->where('b.Id_Lamaran_Tes_Berkas', $realId)
            ->where('b.Id_Users', $userId)
            ->select('b.*', 't.Flag_Selesai')
            ->first() : null;

        if (! $b) {
            return ResponseHelper::error('Berkas tidak ditemukan.', 404);
        }

        if ($b->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas sudah selesai - berkas tidak bisa dihapus.', 409);
        }

        // SUDAH IKUT DIKIRIM → TIDAK BISA DITARIK LAGI.
        //
        // Menekan "Kirim Berkas" memindahkan berkas ini ke meja penilai. Sejak
        // detik itu ia bukan lagi draf pribadi kandidat melainkan BAHAN
        // PENILAIAN yang bisa sedang dibaca — sementara penghapusan di bawah
        // permanen sampai ke GCS: tidak ada tong sampah, tidak ada pemulihan.
        // Kandidat yang berubah pikiran (atau salah pencet) bisa mengosongkan
        // lampiran yang sudah dinilai, dan rapornya memuat penilaian atas
        // dokumen yang tak lagi ada.
        //
        // Penanda dibaca dari BERKASNYA, bukan dari Unggah_Kirim_At aktivitas —
        // lihat alasan lengkapnya di tesBerkasKirim().
        //
        // DITAHAN DI SERVER, bukan sekadar tombolnya disembunyikan: pintu ini
        // tetap bisa diketuk langsung tanpa lewat layar.
        if ($b->Terkirim_At) {
            return ResponseHelper::error(
                'Berkas ini sudah kamu kirim dan sedang dinilai tim — tidak bisa dihapus lagi. '
                . 'Hubungi tim rekrutmen bila ada yang perlu diperbaiki.',
                409,
            );
        }

        // BASIS DATA DULU, GCS BELAKANGAN — lihat alasan lengkapnya di
        // hapusBerkasTahap(). Singkatnya: berkas yang terdaftar tapi isinya
        // sudah lenyap lebih merugikan daripada objek yatim di penyimpanan.
        DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->delete();

        try {
            Storage::disk(GcsBerkas::DISK)->delete($b->Path_File);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[TES-BERKAS] sisa GCS gagal dihapus: ' . $e->getMessage());
        }

        return ResponseHelper::success(null, 'Berkas dihapus.');
    }

    /** GET pratinjau berkas aktivitas milik kandidat (signed URL 15 menit). */
    public function tesBerkasFile(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
            ->where('Id_Lamaran_Tes_Berkas', $realId)
            ->where('Id_Users', $userId)
            ->first() : null;

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[TES-BERKAS] signed URL gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    /**
     * GET berkas HASIL TAHAP milik kandidat login (signed URL GCS 15 menit).
     *
     * Endpoint admin (berkasTahapFile) bergerbang izin `pelamarPage`, jadi tidak
     * bisa dipakai kandidat. Yang ini memeriksa kepemilikan lewat
     * Lamaran.Id_Users — mengetahui id berkas orang lain tidak cukup untuk
     * membukanya.
     */
    public function portalBerkasTahap(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas as tb')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'tb.Lamaran_Id')
            ->where('tb.Id_Lamaran_Tahap_Berkas', $realId)
            ->where('l.Id_Users', $userId)
            ->select('tb.Path_File', 'tb.Nama_File')
            ->first();

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[PORTAL] signed URL berkas tahap gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    // portalTanggapanPenawaran() DICABUT.
    //
    // Kandidat tidak lagi menyatakan menerima / mengundurkan diri sendiri
    // lewat portal. Keputusan itu kini hanya dicatat tim di worklist, lewat
    // "Keputusan dari Kandidat" — satu pintu, dengan jejak siapa mencatatnya.
    //
    // Kolom Tanggapan_Kandidat / Tanggapan_Catatan / Tanggapan_At SENGAJA
    // DIBIARKAN di tabel: membuangnya akan menghapus riwayat jawaban yang
    // pernah tercatat, dan worklist masih membacanya untuk menampilkan
    // jawaban lama. Yang dicabut kemampuannya menulis dari sisi kandidat.

    /**
     * Kode hasil untuk kandidat yang mundur, dipilih dari MASTER.
     *
     * Menolak penawaran resmi dan mengundurkan diri sebelum ada penawaran
     * adalah dua sebab berbeda; menyamakannya membuat laporan tidak bisa
     * menjawab "penawaran kita kalah" versus "kandidat pergi lebih dulu".
     */
    private static function kodeMundurKandidat(?string $tipeKode): ?string
    {
        $kandidat = \App\Support\Career\LamaranService::masterHasilKeputusan()
            ->filter(fn ($h) => ($h->Flag_Oleh_Kandidat ?? 'T') === 'Y');

        if ($kandidat->isEmpty()) {
            return null;
        }

        // Tahap penawaran resmi → "menolak penawaran" bila ada di master.
        if ($tipeKode === 'OFFERING' && $kandidat->has('DITOLAK_KANDIDAT')) {
            return 'DITOLAK_KANDIDAT';
        }

        return $kandidat->has('MENGUNDURKAN_DIRI')
            ? 'MENGUNDURKAN_DIRI'
            : $kandidat->keys()->first();
    }

    /** GET pratinjau berkas MILIK kandidat login (cek kepemilikan lamaran). */
    public function portalBerkasFile(string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fp.Lamaran_Id')
            ->where('fb.Id_Formulir_Berkas', $realId)
            ->where('l.Id_Users', $userId) // hanya berkas milik kandidat sendiri
            ->select('fb.*')
            ->first();
        if (! $b) {
            abort(404);
        }

        if ($b->Path_File) {
            try {
                $gcs = Storage::disk(GcsBerkas::DISK);
                if ($gcs->exists($b->Path_File)) {
                    return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Signed URL GCS gagal (portal) berkas ' . $b->Id_Formulir_Berkas . ': ' . $e->getMessage());
            }
        }

        foreach ([storage_path('app/' . $b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan.');
    }

    /**
     * WEBHOOK CAT/HCLearn — hasil tes pihak ke-3 (server-to-server, guard secret).
     * Dipanggil CAT saat tes difinalisasi → WC memperbarui nilai peserta lalu
     * MENGGERAKKAN tahap otomatis: LULUS → tahap berikutnya, GUGUR → ditutup.
     * Idempoten: bila peserta sudah 'selesai', abaikan (balas ok).
     */
    public function hasilUjianCallback(Request $request)
    {
        // Guard: cocokkan secret (header X-WC-Secret). Tanpa login.
        $secret = (string) config('hclearn.callback_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-WC-Secret'))) {
            return ResponseHelper::error('Secret tidak valid.', 401);
        }

        $data = $request->validate([
            'Id_WC_Penjadwalan_Peserta' => 'required|integer',
            'Status_Kelulusan' => 'nullable|string|max:30',
            'lulus' => 'nullable|boolean',
            'Total_Nilai' => 'nullable|numeric',
            'Total_Soal' => 'nullable|integer',
            'Ambang_Batas_Nilai' => 'nullable|numeric',
            'Status_Pengerjaan' => 'nullable|string|max:30',
        ]);

        // PENCOCOKAN LEWAT PENGENAL YANG KITA KIRIM.
        //
        // CAT mengembalikan angka yang KITA berikan saat menjadwalkan, dan sejak
        // 3 Agustus 2026 itu adalah `Ref_Cat_Peserta` (nomor SEQUENCE), bukan
        // lagi kolom IDENTITY kita — id IDENTITY bisa terulang setelah tabel
        // di-reset, dan itu membuat token tertukar antar kandidat.
        //
        // Pencarian lewat IDENTITY DIPERTAHANKAN sebagai cadangan: hasil ujian
        // dari penjadwalan lama masih memakai id yang lama, dan kehilangan
        // hasilnya berarti kandidat tampak tak pernah mengerjakan tes.
        //
        // TAPI cadangan itu DIBATASI pada baris yang memang belum punya
        // pengenal baru (`Ref_Cat_Peserta IS NULL`). Tanpa batasan itu, hasil
        // lama bernomor 3 akan mendarat pada peserta yang KEBETULAN kini
        // ber-IDENTITY 3 — orang yang berbeda. Itu persis kelas kesalahan yang
        // sedang diperbaiki: nilai ujian tertukar antar kandidat.
        $ref = (int) $data['Id_WC_Penjadwalan_Peserta'];
        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Ref_Cat_Peserta', $ref)->first()
            ?: DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $ref)
                ->whereNull('Ref_Cat_Peserta')
                ->first();

        if (! $peserta) {
            Log::channel('web_career')->warning("[HASIL-UJIAN] peserta tak dikenal untuk pengenal {$ref}.");

            return ResponseHelper::error('Peserta penjadwalan tidak ditemukan.', 404);
        }

        $hasil = $this->prosesHasilUjian($peserta, $data, 'CALLBACK');

        if (! $hasil['diproses']) {
            return ResponseHelper::success($hasil, 'Hasil diterima (tahap sudah final).');
        }

        return ResponseHelper::success($hasil, 'Hasil tes diproses.');
    }

    /**
     * SATU JALUR untuk memasukkan hasil ujian pihak ke-3 ke mesin keputusan.
     *
     * Dipakai dua pemanggil yang membawa data dari sumber yang sama (CAT):
     *   - webhook `hasilUjianCallback` — CAT mendorong begitu tes difinalisasi;
     *   - `subTesSinkron` — admin menarik sendiri saat dorongan itu tak sampai.
     *
     * Disatukan supaya keduanya mustahil berbeda perilaku: verdict dihitung
     * dengan aturan yang sama, nilai disimpan ke kolom yang sama, mesin tahap
     * dievaluasi lewat pintu yang sama, dan emailnya pun sama. Idempoten:
     * tahap yang sudah final tidak diproses ulang.
     *
     * @return array{diproses:bool, hasil?:string, outcome?:string}
     */
    private function prosesHasilUjian(object $peserta, array $data, string $asal): array
    {
        // Verdict LULUS/GUGUR dari CAT (bool 'lulus' diprioritaskan; fallback teks).
        $teks = strtoupper((string) ($data['Status_Kelulusan'] ?? ''));
        $lulus = array_key_exists('lulus', $data) && $data['lulus'] !== null
            ? (bool) $data['lulus']
            : in_array($teks, ['LULUS', 'LOLOS', 'PASS', 'ACCEPT'], true);
        $hasil = $lulus ? 'LULUS' : 'GUGUR';

        // SATU TRANSAKSI: nilai yang direkam dan tahap yang menyimpulkannya
        // adalah satu peristiwa. CAT tidak mengirim hasil yang sama dua kali,
        // dan gerbang idempoten di bawah menolak percobaan ulang — jadi
        // kegagalan di antara keduanya berarti nilai tersimpan tanpa pernah
        // dinilai, dan tahapnya menunggu selamanya.
        $jalan = DB::transaction(function () use ($peserta, $data, $hasil) {
            // Rekam nilai apa pun keputusannya (untuk tampil di worklist admin).
            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                ->update(array_filter([
                    'Total_Nilai' => $data['Total_Nilai'] ?? null,
                    'Total_Soal' => $data['Total_Soal'] ?? null,
                    'Ambang_Batas_Nilai' => $data['Ambang_Batas_Nilai'] ?? null,
                    'Status_Kelulusan' => $data['Status_Kelulusan'] ?? $hasil,
                    'Status_Pengerjaan' => $data['Status_Pengerjaan'] ?? 'selesai',
                    'Flag_Selesai' => 'Y',
                    'Waktu_Callback' => now(),
                    'Updated_At' => now(),
                ], fn ($v) => $v !== null));

            // Tahap lamaran yang tertaut penjadwalan tahap ini & masih BERJALAN.
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $peserta->Lamaran_Id)
                ->where('Penjadwalan_Tahap_Id', $peserta->Penjadwalan_Tahap_Id)
                ->first();

            if (! $tahap || $tahap->Status !== 'BERJALAN') {
                return ['diproses' => false]; // sudah diproses sebelumnya (idempoten)
            }

            // MESIN KEPUTUSAN: rekam hasil SUB-TES ini, lalu evaluasi mode tahap.
            // Tahap 1-tes → identik dgn perilaku lama (maju/gugur). Tahap multi-tes →
            // tahap hanya maju/gugur bila kondisi mode terpenuhi; selain itu menunggu.
            // Sub-tes dikenali lewat Penjadwalan_Tahap_Id — ikatan pasti antara sesi
            // ujian dan aktivitas, penting saat satu tahap memuat beberapa ujian.
            $eval = $this->svc->rekamHasilTesEksternal(
                (int) $tahap->Id_Lamaran_Tahap,
                null,
                $hasil,
                isset($data['Total_Nilai']) ? (float) $data['Total_Nilai'] : null,
                isset($data['Total_Soal']) ? (int) $data['Total_Soal'] : null,
                (int) $peserta->Penjadwalan_Tahap_Id
            );

            return [
                'diproses' => true,
                'outcome' => $eval['outcome'] ?? 'TUNGGU',
                'label' => $tahap->Label,
            ];
        });

        if (! $jalan['diproses']) {
            return ['diproses' => false];
        }

        $outcome = $jalan['outcome'];

        // Email hanya saat tahap BENAR-BENAR menyimpulkan (bukan per sub-tes) —
        // supaya baterai tes tidak membanjiri kandidat dgn email tiap hasil.
        // DI LUAR TRANSAKSI: lihat alasannya di subTesKehadiran().
        if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
            $this->kirimEmailHasilTahap((int) $peserta->Lamaran_Id, $outcome === 'LANJUT');
        }

        Log::channel('web_career')->info("[{$asal}] hasil tes peserta #{$peserta->Id_Penjadwalan_Peserta} = {$hasil} → tahap {$jalan['label']}: {$outcome}.");

        return ['diproses' => true, 'hasil' => $hasil, 'outcome' => $outcome];
    }

    /** Kirim email hasil setelah tahap tes diputus otomatis. */
    private function kirimEmailHasilTahap(int $lamaranId, bool $lulus): void
    {
        try {
            $l = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->where('l.Id_Lamaran', $lamaranId)
                ->select('l.Id_Users', 'l.Kode', 'l.Status', 'l.Hasil_Akhir', 'l.Program_Id',
                    'l.Urutan_Tahap', 'l.Total_Tahap', 'p.Nama as ProgramNama', 'x.Posisi')
                ->first();
            if (! $l || ! $l->Id_Users) {
                return;
            }

            // Metode ini HANYA dipanggil saat sebuah tahap benar-benar menyimpulkan
            // (LANJUT / GUGUR) — lihat pemanggilnya. Jadi hasilnya selalu LOLOS atau
            // GUGUR, tidak pernah MENUNGGU.
            //
            // Dulu di sini: lulus tahap ANTARA dikirimi status 'MENUNGGU', yang di
            // template berbunyi "Pendaftaranmu sedang ditinjau" — kandidat yang baru
            // saja diloloskan justru diberi tahu bahwa lamarannya belum diperiksa.
            // Kalimat "sedang ditinjau" tetap benar untuk email SAAT MELAMAR
            // (WcApplyFormJob), bukan untuk keputusan tahap.
            // Metode ini HANYA dipanggil saat tahap benar-benar menyimpulkan,
            // jadi hasilnya wajib LOLOS atau GUGUR. 'MENUNGGU' milik jalur APPLY
            // (WcApplyFormJob) dan tidak boleh bocor ke sini — pagar ini yang
            // membuat kekeliruan itu mustahil terulang diam-diam.
            $status = $lulus ? 'LOLOS' : 'GUGUR';

            if (! in_array($status, ['LOLOS', 'GUGUR'], true)) {
                Log::channel('web_career')->error(
                    "[EMAIL] status keputusan tahap tidak sah: '{$status}' (lamaran #{$lamaranId}) — email dibatalkan."
                );

                return;
            }

            // `diterima` = seluruh seleksi tuntas, bukan sekadar satu tahap lewat.
            // Template memakainya untuk memilih antara "Kamu Diterima" dan
            // "Kamu Lolos tahap X — lanjut ke tahap Y".
            $diterima = $lulus && ($l->Status === 'LULUS' || $l->Hasil_Akhir === 'DITERIMA');

            // Tahap yang BARU SAJA diputus + tahap sesudahnya, supaya emailnya
            // menyebut nama tahapnya alih-alih kalimat umum.
            $tahapDiputus = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Lamaran_Id', $lamaranId)
                ->whereNotNull('Diputus_At')
                ->orderByDesc('Diputus_At')
                ->orderByDesc('Urutan')
                ->first(['Urutan', 'Label']);

            $tahapBerikut = $tahapDiputus
                ? DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)
                    ->where('Urutan', '>', $tahapDiputus->Urutan)
                    ->orderBy('Urutan')
                    ->value('Label')
                : null;

            $kandidat = LamaranService::dataKandidatEmail($lamaranId);

            // [feat/feedback] Buat feedback record untuk keputusan FINAL
            $feedbackUrl = null;
            if (in_array($status, ['GUGUR', 'LOLOS'], true)) {
                $userEmail = DB::table('N_WEB_CAREERS_Users')
                    ->where('Id_Users', $l->Id_Users)
                    ->value('Email');
                if ($userEmail) {
                    $feedbackService = app(\App\Support\Career\FeedbackService::class);
                    $feedbackId = $feedbackService->buatFeedback(
                        (int) $lamaranId,
                        $userEmail,
                        $l->Program_Id
                    );
                    if ($feedbackId) {
                        $tokens = $feedbackService->generateTokenPair($feedbackId, $userEmail);
                        $feedbackUrl = rtrim(config('app.url'), '/')
                            . '/feedback/' . $tokens['hashids'] . '/' . $tokens['signature'];
                    }
                }
            }
            // Akhir [feat/feedback]

            WcApplyEmailJob::dispatch((int) $l->Id_Users, $status, [
                'kode' => $l->Kode,
                'posisi' => $l->Posisi ?: $l->ProgramNama,
                'program' => $l->ProgramNama,
                'feedbackUrl' => $feedbackUrl, // [feat/feedback]
                // Konteks tahap — tanpa ini email lolos berbunyi umum saja.
                'tahapLolos' => $tahapDiputus->Label ?? null,
                'tahapBerikut' => $tahapBerikut,
                'urutan' => $tahapDiputus->Urutan ?? null,
                'total' => $l->Total_Tahap ?? null,
                'diterima' => $diterima,
                // Kartu data kandidat — disebar utuh, jadi field yang kelak
                // ditambahkan di dataKandidatEmail() ikut terbawa tanpa perlu
                // menyalin namanya satu per satu di sini.
                ...$kandidat,
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[CALLBACK] gagal antre email hasil lamaran #{$lamaranId}: " . $e->getMessage());
        }
    }

    /** POST /api/v1/lamaran/tahap/{id}/kirim — kandidat mengirim formulir tahap. */
    public function kirimFormulir(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'jawaban' => 'required|array',
        ]);

        try {
            // SATU TRANSAKSI: jawaban tersimpan DAN berkasnya berpindah
            // kepemilikan, atau tidak sama sekali.
            //
            // Dulu ketiganya berdiri sendiri. Bila pemindahan berkas gagal di
            // tengah, pengisiannya sudah telanjur tercatat — dan kandidat tidak
            // bisa mengulang karena tahapnya sudah dianggap terisi. Yang tersisa
            // adalah formulir terkirim yang lampirannya masih berstatus draf:
            // tim rekrutmen membaca nama berkas yang tidak bisa dibuka.
            $hasil = DB::transaction(function () use ($realId, $userId, $data, $request) {
                $r = $this->svc->simpanPengisian((int) $realId, $userId, $data['jawaban'], $request->ip());

                // Penolakan aturan (syarat tak terpenuhi, tahap sudah terisi)
                // datang sebagai nilai balik. Ditinggikan jadi lemparan supaya
                // ia benar-benar membatalkan transaksinya; penangkapnya di bawah
                // mengembalikannya jadi 422 seperti semula.
                if (! $r['ok']) {
                    throw new \DomainException($r['pesan']);
                }

                // BERKAS DRAF -> BERKAS PERMANEN, lalu draf dibuang.
                //
                // Urutannya penting dan tidak boleh dibalik. Sebelumnya draf langsung
                // dihapus berikut berkasnya di GCS, sehingga pengisian hanya mewarisi
                // NAMA berkas sebagai teks jawaban sementara isinya lenyap — kandidat
                // melihat nama yang tak bisa dibuka dan tim rekrutmen kehilangan
                // dokumennya.
                //
                // hapusBerkas: false karena objek GCS-nya TIDAK disalin, hanya
                // berpindah kepemilikan ke Formulir_Berkas. Menghapusnya di sini
                // berarti membuang berkas yang baru saja diadopsi — dan itu pula
                // yang membuat langkah ini aman berada di dalam transaksi:
                // seluruhnya menyentuh basis data saja, tak satu pun objek GCS
                // dihapus, jadi tidak ada akibat yang mustahil digulung balik.
                $pengisianId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Id_Lamaran_Tahap', $realId)
                    ->value('Formulir_Pengisian_Id');

                if ($pengisianId) {
                    FormulirDrafController::jadikanPermanen((int) $realId, $userId, $pengisianId);
                }

                FormulirDrafController::bersihkan((int) $realId, $userId, hapusBerkas: false);

                return $r;
            });

            // ── BIODATA MENYUSUL KE HRIS REKRUTMEN ──────────────────────────
            //
            // Di sinilah data diri kandidat sebenarnya masuk — tanggal lahir,
            // jenis kelamin, alamat, pendidikan. Dulu tidak ada satu pun dari
            // ini yang sampai ke HCLearn: satu-satunya pengiriman terjadi saat
            // kandidat MENDAFTAR AKUN, tepat ketika ia belum mengisi apa pun.
            //
            // Di luar transaksi dan lewat antrean: panggilan ini menembak server
            // lain, dan formulir kandidat sudah tersimpan dengan selamat. Ia
            // tidak boleh menunggu — apalagi gagal — karena HCLearn sedang sibuk.
            WcBiodataHrisJob::dispatch($userId, 'KIRIM_FORMULIR');

            return ResponseHelper::success(['rekomendasi' => $hasil['rekomendasi'] ?? null], $hasil['pesan']);
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal simpan pengisian: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan formulir.', 500);
        }
    }

    // ═══════════════════════ ADMIN — WORKLIST ═══════════════════════

    /** /karir/pelamar — panel kiri: daftar PROGRAM berjalan; panel kanan: kanban alur program terpilih. */
    public function worklist()
    {
        return Inertia::render('Career/admin/Pelamar', CareerShell::props('/karir/pelamar', 'Worklist Pelamar', [
            'talent' => $this->talentTabs(),
            'programAwal' => $this->daftarProgram(1, self::PROGRAM_PER_HALAMAN, '', ''),
            // TOMBOL KEPUTUSAN DIBACA DARI MASTER, bukan tiga tombol yang
            // ditulis mati di layar. Master sudah lama memuat lima hasil —
            // termasuk "Kandidat Menolak" dan "Mengundurkan Diri" — tetapi
            // worklist hanya pernah menampilkan tiga, sehingga dua sebab
            // berhentinya proses yang datang DARI KANDIDAT tak punya jalan
            // dicatat sama sekali dan terpaksa dicatat sebagai "Tidak Lolos".
            'hasilKeputusan' => \App\Support\Career\LamaranService::masterHasilKeputusan()
                ->values()
                ->map(fn ($h) => [
                    'kode' => $h->Kode,
                    'nama' => $h->Nama,
                    'labelTombol' => $h->Label_Tombol,
                    'labelKonfirmasi' => $h->Label_Konfirmasi,
                    'ikon' => $h->Ikon,
                    'warna' => $h->Warna,
                    'deskripsi' => $h->Deskripsi,
                    'lolos' => ($h->Flag_Lolos ?? 'T') === 'Y',
                    'kirimEmail' => ($h->Flag_Kirim_Email ?? 'T') === 'Y',
                    // Bila `pilihTalentPool` menyala, ini PILIHAN AWAL yang
                    // dicentangkan — bukan nasib yang sudah ditetapkan.
                    'talentPool' => ($h->Flag_Talent_Pool ?? 'T') === 'Y',
                    // Admin yang memutuskan kandidat disimpan atau tidak.
                    // Dipakai pengunduran diri & penolakan penawaran: keduanya
                    // bukan kegagalan seleksi, jadi jawabannya beda-beda per
                    // orang dan tidak boleh dipatok oleh jenis hasilnya.
                    'pilihTalentPool' => ($h->Flag_Pilih_Talent_Pool ?? 'T') === 'Y',
                    'labelTalentPool' => $h->Label_Talent_Pool ?? 'Simpan kandidat ini di Talent Pool',
                    // Keputusan yang datang dari KANDIDAT, bukan dari perusahaan.
                    // Dipisah di layar supaya tidak terbaca sebagai penilaian tim.
                    'olehKandidat' => ($h->Flag_Oleh_Kandidat ?? 'T') === 'Y',
                    'butuhAlasan' => ($h->Butuh_Alasan ?? 'T') === 'Y',
                    // HASIL INI MENUNTUT AKTIVITAS TAHAPNYA TUNTAS DULU?
                    //
                    // Asimetri yang disengaja: "Lolos" dan "Talent Pool"
                    // menyatakan orang ini cukup baik — pernyataan yang tak boleh
                    // dibuat di atas bukti yang belum ada. "Tidak Lolos",
                    // "Mengundurkan Diri", dan "Tahan" justru paling sering
                    // dibutuhkan JUSTRU saat segalanya belum lengkap; menguncinya
                    // menutup jalan keluar yang sah.
                    //
                    // Dari master, bukan daftar kode di sini: hasil keputusan
                    // baru yang ditambahkan admin harus menyatakan sikapnya
                    // sendiri, bukan diam-diam lolos dari gerbang.
                    'butuhTuntas' => ($h->Flag_Butuh_Tuntas ?? 'T') === 'Y',
                ])->all(),
            // JAWABAN KANDIDAT ATAS PENAWARAN — dicatat tim saat menandai
            // kehadiran negosiasi. `menutup` menyatakan jawaban itu langsung
            // menutup lamaran atau tidak, supaya layar bisa memperingatkan
            // sebelum ditekan alih-alih sesudahnya.
            'jawabanPenawaran' => self::masterJawabanPenawaran()->values()->map(fn ($j) => [
                'kode' => $j->Kode,
                'nama' => $j->Nama,
                'label' => $j->Label_Panjang ?: $j->Nama,
                'keterangan' => $j->Keterangan,
                'ikon' => $j->Ikon,
                'nada' => $j->Nada ?: 'ok',
                'lolos' => ($j->Flag_Lolos ?? 'T') === 'Y',
                'menutup' => (bool) $j->Hasil_Keputusan_Kode,
                'butuhAlasan' => ($j->Flag_Butuh_Alasan ?? 'T') === 'Y',
            ])->all(),
            // STATUS HASIL MCU — dari master, bukan array yang ditulis di layar.
            // Empat nilai baku ketenagakerjaan; `lolos` menerjemahkannya jadi
            // verdict aktivitas, `butuhCatatan` menandai yang tak berarti
            // apa-apa tanpa keterangannya.
            'mcuStatusOpsi' => self::masterMcuStatus()->values()->map(fn ($m) => [
                'kode' => $m->Kode,
                'nama' => $m->Nama,
                'label' => $m->Label_Panjang ?: $m->Nama,
                'keterangan' => $m->Keterangan,
                'ikon' => $m->Ikon,
                'nada' => $m->Nada ?: 'ok',
                'lolos' => ($m->Flag_Lolos ?? 'T') === 'Y',
                'butuhCatatan' => ($m->Flag_Butuh_Catatan ?? 'T') === 'Y',
            ])->all(),
            // BENTUK PELAKSANAAN JADWAL — dari master, bukan dua tombol yang
            // ditulis mati di layar. Tiap bentuk membawa sendiri field apa yang
            // wajib diisi untuknya, jadi modal tidak perlu tahu nama-namanya.
            'modeJadwal' => self::masterModeJadwal()->values()->map(fn ($m) => [
                'kode' => $m->Kode,
                'nama' => $m->Nama,
                'ikon' => $m->Ikon,
                'warna' => $m->Warna,
                'deskripsi' => $m->Deskripsi,
                'butuhTautan' => ($m->Flag_Butuh_Tautan ?? 'T') === 'Y',
                'butuhLokasi' => ($m->Flag_Butuh_Lokasi ?? 'T') === 'Y',
                'butuhKontak' => ($m->Flag_Butuh_Kontak ?? 'T') === 'Y',
                'labelKontak' => $m->Label_Kontak ?: 'Nomor yang dihubungi',
                'petunjukKontak' => $m->Petunjuk_Kontak,
                'kalimatUndangan' => $m->Kalimat_Undangan,
                'luring' => ($m->Flag_Luring ?? 'T') === 'Y',
            ])->all(),
        ]));
    }

    /** Berapa program per halaman di panel kiri (paginasi muncul bila lebih). */
    private const PROGRAM_PER_HALAMAN = 10;

    /** GET panel kiri — daftar program berjalan (paginasi + cari + filter jenis). */
    public function worklistProgram(Request $request)
    {
        return ResponseHelper::success(
            $this->daftarProgram(
                max(1, (int) $request->query('page', 1)),
                self::PROGRAM_PER_HALAMAN,
                trim((string) $request->query('q', '')),
                (string) $request->query('jenis', '')
            ),
            'Daftar program'
        );
    }

    /** GET panel kanan — kolom (alur program) + kartu pelamar program tsb. */
    public function worklistDetail(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Program tidak valid.', 422);
        }

        return ResponseHelper::success($this->detailProgram((int) $realId), 'Detail program');
    }

    /** Tab filter jenis = Master Talent Acquisition aktif. */
    private function talentTabs(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Master_Talent_Acquisition')
            ->get(['Kode', 'Nama'])
            ->map(fn ($r) => ['kode' => $r->Kode, 'label' => $r->Nama])
            ->all();
    }

    /**
     * PANEL KIRI — program berjalan, paginasi.
     *
     * @return array{data:array, page:int, perPage:int, total:int, totalPage:int}
     */
    private function daftarProgram(int $page, int $perPage, string $q, string $jenis): array
    {
        $base = DB::table('N_WEB_CAREERS_Program as p')
            ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->where('p.Status', 'BERJALAN');

        if ($q !== '') {
            $base->where('p.Nama', 'like', "%{$q}%");
        }
        if ($jenis !== '') {
            $base->where('p.Kategori', $jenis);
        }

        $total = (clone $base)->count();

        $rows = $base->orderBy('p.Nama')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select('p.*', 'a.Nama as AlurNama')
            ->get();

        // Hitung pelamar per program (total, aktif = belum gugur, lolos = LULUS).
        $hitung = DB::table('N_WEB_CAREERS_Lamaran')
            ->select('Program_Id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN Status NOT IN ('GUGUR','TALENT_POOL') THEN 1 ELSE 0 END) as aktif"),
                DB::raw("SUM(CASE WHEN Status = 'LULUS' THEN 1 ELSE 0 END) as lolos"))
            ->groupBy('Program_Id')
            ->get()
            ->keyBy('Program_Id');

        // Jumlah tahap per alur (untuk keterangan kartu).
        $jmlTahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
            ->join('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 't.Master_Alur_Id')
            ->select('a.Kode', DB::raw('COUNT(*) as c'))
            ->groupBy('a.Kode')
            ->pluck('c', 'Kode');

        $data = $rows->map(fn ($p) => [
            'id' => Hashids::encode($p->Id_Program),
            'kode' => $p->Kode,
            'nama' => $p->Nama,
            'kategori' => $p->Kategori,
            'warna' => $p->Warna,
            'penyelenggara' => $p->Penyelenggara ?: 'EVO Group',
            'alur' => $p->AlurNama,
            'jumlahTahap' => (int) ($jmlTahap[$p->Alur_Kode] ?? 0),
            'pelamar' => (int) ($hitung[$p->Id_Program]->total ?? 0),
            'aktif' => (int) ($hitung[$p->Id_Program]->aktif ?? 0),
            'lolos' => (int) ($hitung[$p->Id_Program]->lolos ?? 0),
        ])->all();

        return [
            'data' => $data,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * PANEL KANAN — kolom kanban = TAHAP ALUR program ini (bukan tipe generik),
     * dan kartu pelamar ditempatkan pada tahap posisinya:
     *  - BERJALAN → tahap yang sedang berjalan
     *  - GUGUR    → tahap tempat ia gugur (tetap "di loop" tahap itu)
     *  - LULUS    → tahap terakhir
     */
    private function detailProgram(int $programId): array
    {
        $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first();
        if (! $program) {
            return ['program' => null, 'kolom' => [], 'pelamar' => []];
        }

        $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
        $alurProgramId = $alur ? (int) $alur->Id_Master_Alur : null;

        // ── KOLOM = ALUR YANG BENAR-BENAR DIPAKAI, bukan penunjuk di program ──
        //
        // Dulu kolom disusun dari alur yang SEKARANG menempel di program, lalu
        // kartu ditempatkan memakai nomor urut tahapnya. Dua-duanya rapuh:
        // mengarahkan program ke alur baru membuat kandidat lama tergambar di
        // kolom yang bukan miliknya, dan menyunting alur di tempat (Id sama,
        // arti berbeda) melakukan hal yang sama tanpa satu galat pun.
        //
        // Papannya yang berbohong, bukan datanya — dan itu lebih berbahaya,
        // karena keputusan diambil dari papan. Lihat App\Support\Career\AlurKolom.
        $kolom = AlurKolom::susun(
            AlurKolom::alurDipakai($programId, $alurProgramId),
            $alurProgramId,
        );

        // Pelamar program ini + tahap-tahapnya.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Program_Id', $programId)
            ->orderByDesc('l.Id_Lamaran')
            // Rincian lowongan ikut dibawa: worklist perlu menyaring & menampilkan
            // departemen/lokasi/MPP di kartu, sama seperti di halaman lowongan.
            ->select('l.*', 'u.Nama as Pelamar', 'u.Email as Email', 'u.No_Hp as NoHp',
                'x.Posisi', 'x.Departemen', 'x.Lokasi', 'x.Level', 'x.Mpp_Ref')
            ->get();

        $tahapPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Id');

        // Rapor sub-tes per tahap (baterai multi-tes) — dasar admin memutuskan.
        $subPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Lamaran_Tahap_Id', $tahapPer->flatten(1)->pluck('Id_Lamaran_Tahap')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Tahap_Id');

        // Kuota MPP per posisi + kursi TERISI (LULUS) — untuk tombol sadar-kuota.
        $posisiIds = $lamaran->pluck('Program_Posisi_Id')->filter()->unique()->all();
        $kuotaPosisi = $posisiIds ? DB::table('N_WEB_CAREERS_Program_Posisi')->whereIn('Id_Program_Posisi', $posisiIds)->pluck('Kuota', 'Id_Program_Posisi') : collect();
        $terisiKuota = $posisiIds
            ? DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Program_Posisi_Id', $posisiIds)->where('Status', 'LULUS')
                ->select('Program_Posisi_Id', DB::raw('COUNT(*) as J'))->groupBy('Program_Posisi_Id')->pluck('J', 'Program_Posisi_Id')
            : collect();

        // Jumlah berkas hasil (MCU/Interview) per tahap — untuk gate wajib-upload.
        $tahapIdsAll = $tahapPer->flatten(1)->pluck('Id_Lamaran_Tahap')->all();
        $berkasCount = $tahapIdsAll
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->whereIn('Lamaran_Tahap_Id', $tahapIdsAll)
                ->select('Lamaran_Tahap_Id', DB::raw('COUNT(*) as J'))->groupBy('Lamaran_Tahap_Id')->pluck('J', 'Lamaran_Tahap_Id')
            : collect();

        // Berkas yang melekat pada SATU AKTIVITAS (form wawancara terpindai,
        // lembar jawaban tes offline). Diambil sekali untuk seluruh program —
        // memuatnya per aktivitas saat drawer dibuka berarti satu kueri per
        // baris rapor, dan drawer terasa tersendat justru saat paling dipakai.
        $subIdsAll = $subPer->flatten(1)->pluck('Id_Lamaran_Tahap_Tes')->all();
        $berkasSub = $subIdsAll
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                ->whereIn('Lamaran_Tahap_Tes_Id', $subIdsAll)
                ->orderByDesc('Id_Lamaran_Tahap_Berkas')
                ->get()
                ->groupBy('Lamaran_Tahap_Tes_Id')
            : collect();

        // Berkas yang DIUNGGAH KANDIDAT (lembar jawaban terpindai, sertifikat).
        //
        // Sampai sekarang berkas ini hanya pernah terlihat di portal kandidat.
        // Akibatnya seluruh setelan "kandidat wajib mengunggah" di Master Alur
        // tidak berguna: berkasnya masuk, tetapi tim yang harus menilainya tidak
        // punya satu pun layar yang menampilkannya.
        $berkasKandidat = $subIdsAll
            ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')
                ->whereIn('Lamaran_Tahap_Tes_Id', $subIdsAll)
                ->orderBy('Id_Lamaran_Tes_Berkas')
                ->get()
                ->groupBy('Lamaran_Tahap_Tes_Id')
            : collect();

        // Master alasan HOLD — dimuat SEKALI untuk seluruh daftar, bukan per
        // kandidat. PipelineProgress sengaja tidak menyentuh database sendiri.
        $alasanHold = self::masterAlasanHold()->map(fn ($a) => $a->Nama)->all();

        // Nama resmi dari formulir — satu kueri untuk seluruh daftar.
        $namaResmi = self::namaResmiPerLamaran($lamaran->pluck('Id_Lamaran')->all());

        $pelamar = $lamaran->map(function ($l) use ($tahapPer, $subPer, $kuotaPosisi, $terisiKuota, $berkasCount, $berkasSub, $berkasKandidat, $alasanHold, $kolom, $namaResmi) { // NOSONAR
            $tahapList = collect($tahapPer->get($l->Id_Lamaran, []));

            // Aturan penempatan + badge + kuota dipusatkan di PipelineProgress
            // (dipakai juga oleh halaman Monitoring Rekrutmen).
            $tk = PipelineProgress::tahapKini($l, $tahapList);
            $tAktif = PipelineProgress::tahapAktif($l, $tahapList);
            // Sub-tes tahap aktif ikut dikirim: mode keputusan & kesiapan tahap
            // dinilai dari sana (lihat PipelineProgress::state).
            $st = PipelineProgress::state($l, $tAktif, $tk, $subPer->get($tAktif->Id_Lamaran_Tahap ?? 0, []), $alasanHold);
            $skor = $st['skor'];
            $siap = $st['siap'];
            $nungguSistem = $st['nungguSistem'];
            $butuhKeputusan = $st['butuhKeputusan'];

            $ku = PipelineProgress::infoKuota(
                (int) ($kuotaPosisi[$l->Program_Posisi_Id] ?? 0),
                (int) ($terisiKuota[$l->Program_Posisi_Id] ?? 0),
                (int) ($tAktif->Urutan ?? 0),
                (int) $l->Total_Tahap
            );

            $badge = PipelineProgress::badge($l, $st, $tAktif);

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'tahapId' => $tAktif ? Hashids::encode($tAktif->Id_Lamaran_Tahap) : null,
                'lamaranKode' => $l->Kode,
                // NAMA RESMI dari formulir lebih dulu; nama akun jadi cadangan.
                // Nama akun diketik saat mendaftar dan kerap seadanya — kartu,
                // undangan, dan berkas lalu menyebut orang yang sama dengan tiga
                // nama berbeda, dan rekruter mencarinya dengan nama yang tidak
                // pernah ia tulis sendiri.
                'pelamar' => $namaResmi->get($l->Id_Lamaran) ?: ($l->Pelamar ?: $l->Created_By),
                // Nama akun tetap dibawa: saat keduanya berbeda, itu fakta yang
                // perlu terlihat — bukan disembunyikan.
                'pelamarAkun' => $l->Pelamar ?: null,
                'email' => $l->Email ?? null,
                'hp' => $l->NoHp ?? null,
                'posisi' => $l->Posisi ?: $l->Kategori,
                // Identitas + rincian lowongan — dasar penyaring & isi kartu.
                'posisiId' => $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null,
                'departemen' => $l->Departemen ?? null,
                'lokasi' => $l->Lokasi ?? null,
                'level' => $l->Level ?? null,
                'mppRef' => $l->Mpp_Ref ?? null,
                'waktuLamar' => $l->Waktu_Lamar,
                'kategori' => $l->Kategori,
                'statusLamaran' => $l->Status,
                // PENEMPATAN KARTU — memakai KODE tahap, bukan nomor urutnya.
                //
                // Nomor urut hanya benar selama alur tak pernah berubah. Begitu
                // alur disunting (baris dipakai ulang per urutan) atau program
                // diarahkan ke alur lain, "tahap ke-3" milik kandidat dan
                // "kolom ke-3" di papan bisa dua hal yang sama sekali berbeda.
                'kolomKode' => AlurKolom::cocok($kolom, $tk),
                // Nomor tetap dikirim untuk urutan & indikator progres — tapi
                // bukan lagi dasar penempatan.
                'kolomUrutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                'tahap' => $tk->Label ?? '—',
                'urutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                // Info kuota (untuk tombol adaptif & indikator "sisa kursi").
                'kuota' => $ku['kuota'],
                'terisiKuota' => $ku['terisiKuota'],
                'sisaKuota' => $ku['sisaKuota'],
                'kuotaPenuh' => $ku['kuotaPenuh'],
                'diTahapAkhir' => $ku['diTahapAkhir'],
                // Berkas hasil pada tahap aktif (untuk unggah/preview + gate wajib).
                'jmlBerkas' => (int) ($tAktif ? ($berkasCount[$tAktif->Id_Lamaran_Tahap] ?? 0) : 0),
                'totalTahap' => (int) $l->Total_Tahap,
                'badge' => $badge,
                'butuhKeputusan' => (bool) $butuhKeputusan,
                'nungguSistem' => (bool) $nungguSistem,
                'siapDiputus' => (bool) $siap,
                // Mode keputusan tahap aktif + kenapa tombolnya dikunci —
                // dipakai worklist menyembunyikan / menonaktifkan tombol.
                'modeKeputusan' => $st['modeKeputusan'],
                'otomatis' => (bool) $st['otomatis'],
                'aktivitasBelumTercatat' => (int) $st['aktivitasBelumTercatat'],
                'alasanKunci' => $st['alasanKunci'],
                // TAHAP INI TERMASUK CUT-OFF TALENT POOL?
                //
                // Menentukan apakah pilihan "simpan ke Talent Pool" ditawarkan
                // saat kandidat mundur. Di tahap awal — sebelum ada satu pun
                // penilaian — menawarkannya berarti mengisi Talent Pool dengan
                // orang yang belum pernah dinilai siapa pun, dan daftar seperti
                // itu berhenti dipercaya lalu berhenti dipakai.
                'bolehTalentPool' => ($tAktif->Flag_Talent_Pool ?? 'T') === 'Y',
                // ── PERILAKU TAHAP MILIK KANDIDAT INI SENDIRI ──
                //
                // Dulu layar menyimpulkannya dengan mencari kolom master yang
                // nomor urutnya sama, lalu membaca flag di sana. Artinya
                // menyunting alur langsung mengubah syarat kelulusan orang yang
                // sedang menjalaninya — yang paling merugikan: menyalakan
                // "wajib unggah berkas" mengunci kandidat yang tahapnya sudah
                // selesai dinilai, menuntut dokumen yang dulu tidak diminta.
                //
                // Sekarang jawabannya dibawa kandidat, dari salinan tahapnya.
                //
                // Dari $tk (tahap yang DITAMPILKAN), bukan $tAktif: kandidat
                // yang sudah LULUS/GUGUR tidak punya tahap aktif — $tAktif null
                // — dan perilakunya akan diam-diam jatuh kembali ke master,
                // persis kebocoran yang sedang ditutup. $tk selalu ada, dan ia
                // memang tahap yang kartunya sedang berdiri di situ.
                'perilaku' => AlurKolom::perilaku(
                    $tk ?? $tAktif,
                    collect($kolom)->firstWhere('kode', AlurKolom::cocok($kolom, $tk)),
                ),
                // DITAHAN (hold) — berikut alasannya, siapa yang menahan, dan
                // sejak kapan. Kandidat tetap di bucket tahapnya; yang berubah
                // hanya bahwa keputusannya sengaja ditunda.
                'hold' => ($tAktif && ($tAktif->Hold_Flag ?? 'T') === 'Y') ? [
                    'alasanKode' => $tAktif->Hold_Alasan_Kode,
                    'alasanNama' => $alasanHold[$tAktif->Hold_Alasan_Kode ?? ''] ?? null,
                    'catatan' => $tAktif->Hold_Alasan,
                    'catatanHtml' => $tAktif->Hold_Alasan_Html,
                    'sejak' => (string) ($tAktif->Hold_At ?: ''),
                    'olehSiapa' => $tAktif->Hold_By,
                ] : null,
                // Kesimpulan MESIN atas hasil tes tahap ini (LULUS/GAGAL/SEBAGIAN).
                // Admin melihatnya sebelum mengetuk palu — tanpa ini layar hanya
                // bilang "Siap Diputus" dan hasil tesnya harus ditebak sendiri.
                'hasilData' => $st['hasilData'],
                'ringkasHasil' => $st['ringkasHasil'],
                'skor' => $skor,
                // PENAWARAN SUDAH BENAR-BENAR DIAJUKAN?
                //
                // Bukan sekadar "tahapnya bernama Offering". Selama aktivitas
                // penawarannya belum dicatat, belum ada apa pun yang bisa ditolak
                // atau diundurkan — menawarkan tombol "Kandidat Menolak" di situ
                // sama saja mempersilakan mencatat jawaban atas surat yang belum
                // pernah dikirim.
                'penawaranDiajukan' => self::penawaranDiajukan(
                    $subPer->get($tk->Id_Lamaran_Tahap ?? 0, []),
                    (int) ($tAktif ? ($berkasCount[$tAktif->Id_Lamaran_Tahap] ?? 0) : 0),
                ),
                // JAWABAN KANDIDAT atas penawaran. Admin tidak boleh menebak
                // dari diamnya kandidat: "belum menjawab" dan "sudah menerima"
                // menuntut tindakan yang sama sekali berbeda.
                'tanggapan' => ($tAktif->Tanggapan_Kandidat ?? null) ? [
                    'jawab' => $tAktif->Tanggapan_Kandidat,
                    'catatan' => $tAktif->Tanggapan_Catatan,
                    'waktu' => (string) ($tAktif->Tanggapan_At ?: ''),
                ] : null,
                'rekomendasi' => $tAktif->Rekomendasi ?? null,
                'alasan' => $tAktif->Rekomendasi_Alasan ?? $l->Alasan_Gugur,
                'pengisianId' => ($tAktif && $tAktif->Formulir_Pengisian_Id) ? Hashids::encode($tAktif->Formulir_Pengisian_Id) : null,
                // RAPOR sub-tes tahap yang ditampilkan (baterai multi-tes): admin
                // melihat semua skor — termasuk tes informatif — sebelum memutus.
                'tests' => self::rapotTahap(
                    $subPer->get($tk->Id_Lamaran_Tahap ?? 0, []),
                    $tk->Urutan_Aktivitas ?? 'PARALEL',
                    $berkasSub,
                    $berkasKandidat,
                ),
                // Aturan urutan tahap yang ditampilkan — dipakai layar untuk
                // menjelaskan KENAPA sebagian tombol tidak ada.
                'urutanAktivitas' => $tk->Urutan_Aktivitas ?? 'PARALEL',
                // ── APA YANG MASIH DITUNGGU DARI TAHAP AKTIF ────────────────
                // Daftar "aktivitas — sebabnya", dipakai layar untuk mengunci
                // tombol yang memajukan kandidat DAN menyebutkan apa yang
                // kurang. Dihitung dari tahap AKTIF, bukan tahap yang sedang
                // dilihat: keputusan selalu menyangkut tahap yang berjalan.
                'belumTuntas' => self::belumTuntasTahap(
                    $subPer->get($tAktif->Id_Lamaran_Tahap ?? 0, []),
                    $tAktif->Urutan_Aktivitas ?? 'PARALEL',
                ),
            ];
        })->all();

        // ── JARING PENGAMAN: tidak ada kartu yang boleh hilang dari papan ──
        //
        // Kandidat yang tahapnya tak cocok kolom mana pun — alur lama yang
        // tahapnya sudah dihapus, atau data pra-mesin tanpa Kode — akan lenyap
        // dari layar kalau dibiarkan. Dan kandidat yang tak terlihat tidak akan
        // pernah dikerjakan siapa pun; itu kegagalan yang jauh lebih mahal
        // daripada satu kolom tambahan yang terlihat asing.
        if (collect($pelamar)->contains('kolomKode', AlurKolom::KODE_LAINNYA)) {
            $tersesat = $tahapPer->flatten(1)->filter(
                fn ($t) => AlurKolom::cocok($kolom, $t) === AlurKolom::KODE_LAINNYA,
            );

            if ($cadangan = AlurKolom::kolomCadangan($tersesat)) {
                $kolom[] = $cadangan;
            }
        }

        // Lowongan/posisi program ini + jumlah pelamarnya — dipakai penyaring
        // worklist dan kartu ringkas, sepola dengan tampilan di landing page.
        $rekap = collect($pelamar)->groupBy('posisiId');
        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')
            ->where('Program_Id', $programId)
            ->orderBy('Id_Program_Posisi')
            ->get()
            ->map(function ($x) use ($rekap) {
                $isi = $rekap->get(Hashids::encode($x->Id_Program_Posisi), collect());

                return [
                    'id' => Hashids::encode($x->Id_Program_Posisi),
                    'posisi' => $x->Posisi,
                    'level' => $x->Level ?? null,
                    'departemen' => $x->Departemen ?? null,
                    'lokasi' => $x->Lokasi ?? null,
                    'mppRef' => $x->Mpp_Ref ?? null,
                    'kuota' => (int) ($x->Kuota ?? 0),
                    'status' => $x->Status ?? null,
                    // Angka yang paling sering ditanya HR: berapa yang masih
                    // jalan, berapa diterima, berapa gugur — per lowongan.
                    'pelamar' => $isi->count(),
                    'berjalan' => $isi->where('statusLamaran', 'BERJALAN')->count(),
                    'lolos' => $isi->where('statusLamaran', 'LULUS')->count(),
                    'gugur' => $isi->where('statusLamaran', 'GUGUR')->count(),
                ];
            })->values();

        return [
            'program' => [
                'id' => Hashids::encode($program->Id_Program),
                'nama' => $program->Nama,
                'kategori' => $program->Kategori,
                'alur' => $alur->Nama ?? null,
            ],
            'posisi' => $posisi,
            'kolom' => $kolom,
            'pelamar' => $pelamar,
        ];
    }

    /**
     * Penawaran pada tahap ini SUDAH sampai ke kandidat?
     *
     * Inilah gerbang yang menentukan kapan kandidat boleh menjawab (terima /
     * mundur) — di portalnya maupun sebagai catatan admin. Sebelum gerbang ini
     * terbuka, kandidat belum memegang apa pun: memintanya menjawab sama saja
     * menanyakan pendapat atas surat yang belum pernah dikirim.
     *
     * TIGA tanda yang dihitung, semuanya berarti "kandidat sudah tahu":
     *
     *   1. Aktivitas penawaran berjadwal SUDAH DIJADWALKAN. Menjadwalkan
     *      negosiasi otomatis mengirim undangan ke kandidat — sejak email itu
     *      terkirim ia memang sudah ditawari pembicaraan.
     *   2. Aktivitas penawaran sudah final (hadir/tidak hadir tercatat).
     *   3. Berkas hasil tahap sudah ada — surat penawarannya sendiri terunggah.
     *      Ini yang berlaku pada tahap yang LANGSUNG surat penawaran, tanpa
     *      negosiasi: tak ada jadwal yang bisa jadi penanda.
     */
    private static function penawaranDiajukan(iterable $subTes, int $jmlBerkasTahap = 0): bool
    {
        $tipe = self::masterTipeTahap();
        $adaPenawaran = false;

        foreach ($subTes as $s) {
            if (($tipe[$s->Tipe_Tahap_Kode ?? '']->Flag_Penawaran ?? 'T') !== 'Y') {
                continue;
            }

            $adaPenawaran = true;
            if (! empty($s->Jadwal_Mulai) || ($s->Flag_Selesai ?? 'N') === 'Y') {
                return true;
            }
        }

        return $adaPenawaran && $jmlBerkasTahap > 0;
    }

    /**
     * Satu baris RAPOR aktivitas tahap.
     *
     * `dapatDicatat` menentukan munculnya tombol "Catat Hasil" / "Tidak hadir".
     * SIAPA YANG MENCATAT HASIL — mengikuti cara aktivitas itu dijalankan:
     *
     *  UJIAN ONLINE (CAT).  Hasilnya datang sendiri dari HCLearn. "Catat Hasil"
     *      TIDAK ditawarkan: admin tak punya angka untuk diisi, dan mengisinya
     *      manual justru menimpa nilai resmi yang sebentar lagi masuk. Yang
     *      masuk akal hanya "Tidak hadir" — itu fakta yang cuma diketahui tim,
     *      dan sekaligus jalan keluar bila kandidat tak pernah mengerjakan.
     *
     *  DIKERJAKAN TIM (wawancara, tes offline, FGD).  Tak ada sistem lain yang
     *      mengirim hasilnya, jadi admin yang mencatat.
     *
     * Kecuali: tahap ber-aktivitas TUNGGAL yang ditangani tim (mis. Seleksi
     * Administrasi) hasilnya ADALAH keputusan Loloskan/Tidak Lolos itu sendiri —
     * menyediakan "Catat Hasil" di situ hanya menduplikasi keputusan yang sama.
     */
    /**
     * Rapor SELURUH aktivitas satu tahap — termasuk menghitung mana yang masih
     * terkunci bila tahapnya dikerjakan BERURUTAN.
     *
     * Dihitung di sini, sekali per tahap, bukan di dalam rapotTes(): tiap baris
     * perlu tahu keadaan baris SEBELUMNYA, dan menanyakannya ke database per
     * aktivitas berarti satu kueri per baris rapor hanya untuk menjawab
     * pertanyaan yang jawabannya sudah ada di tangan.
     */
    /**
     * Aktivitas PENENTU tahap ini yang belum tuntas — [{label, sebab}, ...].
     *
     * Dihitung ULANG lewat rapotTahap() supaya aturannya tak pernah bercabang:
     * apa yang membuat tombol mati di layar dan apa yang membuat server menolak
     * harus satu hal yang sama. Menghitungnya terpisah berarti cepat atau lambat
     * tombolnya mati padahal server mengizinkan — atau sebaliknya, dan yang
     * kedua itu celah, bukan sekadar layar yang aneh.
     *
     * INFORMATIF IKUT MENGUNCI — dan ini SENGAJA BERBEDA dari
     * PipelineProgress::state, yang hanya menghitung aktivitas PENENTU.
     *
     * Keduanya menjawab pertanyaan yang berlainan:
     *
     *   state()   "sudah cukup bahan untuk MENYIMPULKAN?" — hanya hasil
     *             aktivitas penentu yang bisa menyimpulkan lulus/gagal.
     *   di sini   "pekerjaan tahap ini sudah SELESAI DIKERJAKAN?"
     *
     * `Peran` menyatakan apakah HASILNYA menentukan kelulusan. Ia tidak pernah
     * berarti aktivitasnya boleh dilewati. Wawancara Manajemen yang berperan
     * informatif tetap wawancara yang harus benar-benar terjadi — dan kalau
     * INFORMATIF dikecualikan di sini, tahap yang seluruh aktivitasnya
     * informatif tidak akan pernah terkunci sama sekali. Justru bentuk tahap
     * itulah yang muncul di laporan: satu wawancara, belum dijadwalkan, tombol
     * "Loloskan" tetap hidup.
     */
    private static function belumTuntasTahap(iterable $subs, ?string $urutanAktivitas): array
    {
        return collect(self::rapotTahap($subs, $urutanAktivitas, collect(), collect()))
            ->filter(fn ($t) => ! ($t['tuntas'] ?? true))
            ->map(fn ($t) => ['label' => $t['label'], 'sebab' => $t['alasanBelumTuntas']])
            ->values()
            ->all();
    }

    private static function rapotTahap(
        iterable $subs,
        ?string $urutanAktivitas,
        $berkasSub,
        $berkasKandidat,
    ): array {
        $berurutan = self::modeUrutanMengunci($urutanAktivitas);
        // Aktivitas terdepan yang belum final — pemegang giliran. Semua yang
        // urutannya di belakangnya ikut terkunci.
        $penghalang = null;

        if ($berurutan) {
            foreach (collect($subs)->sortBy('Urutan') as $s) {
                if (($s->Flag_Selesai ?? 'N') !== 'Y') {
                    $penghalang = $s;
                    break;
                }
            }
        }

        $jumlah = collect($subs)->count();

        return collect($subs)
            ->map(fn ($x) => self::rapotTes(
                $x,
                $jumlah,
                self::bentukBerkasAktivitas($berkasSub->get($x->Id_Lamaran_Tahap_Tes, [])),
                self::bentukBerkasKandidat($berkasKandidat->get($x->Id_Lamaran_Tahap_Tes, [])),
                // Yang MEMEGANG giliran tidak terkunci oleh dirinya sendiri.
                $penghalang && $penghalang->Id_Lamaran_Tahap_Tes !== $x->Id_Lamaran_Tahap_Tes
                    ? $penghalang->Label
                    : null,
            ))
            ->values()
            ->all();
    }

    /** Master mode penilaian, di-cache per permintaan. */
    private static ?\Illuminate\Support\Collection $modePenilaianCache = null;

    private static function masterModePenilaian(): \Illuminate\Support\Collection
    {
        return self::$modePenilaianCache ??= DB::table('N_WEB_CAREERS_Master_Mode_Penilaian')
            ->get()
            ->keyBy('Kode');
    }

    /**
     * Setelan penilaian satu aktivitas → bentuk siap pakai untuk layar.
     *
     * `tipe` yang menentukan bidang apa yang dirender — BUKAN kodenya. Menambah
     * mode baru lewat master karena itu tidak menuntut layar ikut diubah,
     * selama perilakunya salah satu dari NONE/ANGKA/TEKS.
     */
    private static function bentukPenilaian(object $x): ?array
    {
        $def = self::masterModePenilaian()->get((string) ($x->Penilaian_Mode ?? ''));
        if (! $def) {
            return null;
        }

        return [
            'mode' => $def->Kode,
            'nama' => $def->Nama,
            'tipe' => $def->Tipe_Nilai,
            'maks' => $x->Nilai_Maks !== null ? (float) $x->Nilai_Maks : null,
            'opsi' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($x->Penilaian_Opsi ?? '')),
            ))),
        ];
    }

    private static function rapotTes(
        object $x,
        int $jumlahAktivitasTahap,
        array $berkas = [],
        array $berkasKandidat = [],
        ?string $menungguAktivitas = null,
    ): array {
        $final = in_array($x->Status, ['SELESAI', 'TIDAK_HADIR'], true);
        // Tahap BERURUTAN: aktivitas ini masih menunggu gilirannya. Seluruh
        // tombolnya ditutup — menawarkan "Atur Jadwal" untuk wawancara yang
        // belum boleh dijalankan hanya mengundang undangan yang salah terkirim.
        $terkunci = ! $final && $menungguAktivitas !== null;
        $tipe = self::masterTipeTahap()[$x->Tipe_Tahap_Kode ?? ''] ?? null;
        $online = ($tipe->Perilaku_Kode ?? null) === 'CAT' || ($x->Provider ?? '') === 'THIRD_PARTY';
        $isMcu = ($x->Tipe_Tahap_Kode ?? '') === 'MCU';
        // ── AKTIVITAS PENAWARAN ────────────────────────────────────────────
        // Dua bentuk, dibedakan oleh flag tipenya sendiri — bukan oleh kodenya:
        //
        //   BERJADWAL (Flag_Jadwal='Y', mis. Negosiasi, Tanda Tangan Kontrak)
        //       Yang dikerjakan tim adalah MENGATUR PERTEMUANNYA lalu menandai
        //       kandidat datang atau tidak. Tak ada "hasil" terpisah untuk
        //       dicatat: nilai negosiasi bukan angka, dan kesimpulannya ada
        //       pada keputusan tahap.
        //
        //   BERDOKUMEN (Flag_Upload_Hasil='Y', mis. Surat Penawaran)
        //       Tak menuntut tindakan apa pun di rapor. Suratnya diunggah di
        //       jendela keputusan, bersama keputusannya sendiri.
        $isPenawaran = ($tipe->Flag_Penawaran ?? 'T') === 'Y';
        $tipeBerjadwal = ($tipe->Flag_Jadwal ?? 'T') === 'Y';
        // Punya hasil sendiri yang harus dicatat tim: aktivitas manual pada tahap
        // multi-aktivitas (di tahap tunggal, keputusan tahap sudah mewakilinya).
        //
        // MCU DIKECUALIKAN — tidak pernah punya "Catat Hasil" sendiri. Hasil
        // pemeriksaan kesehatan tidak berdiri terpisah dari nasib tahapnya:
        // begitu hasilnya keluar, admin meloloskan atau tidak. Karena itu status
        // kesehatan, penyedia, dan tanggalnya dicatat di JENDELA KEPUTUSAN
        // (lihat putus()) bersama berkas dari kliniknya — satu peristiwa, satu
        // jendela, bukan dua yang salah satunya kerap terlewat.
        // MCU SELALU punya hasilnya sendiri untuk dicatat, berapa pun jumlah
        // aktivitas tahapnya. Dulu ia dikecualikan karena hasilnya diisi di
        // jendela keputusan; sejak status kesehatan pindah ke langkah kehadiran
        // (satu peristiwa: kandidat diperiksa, inilah hasilnya), pengecualian
        // itu justru membuat modal kehadiran tidak menampilkan bidang apa pun.
        $dicatatTim = ! $online && ($jumlahAktivitasTahap > 1 || $isMcu) && ! $isPenawaran;

        // Apa yang masih ditunggu dari aktivitas ini — null = tuntas. Dihitung
        // SEKALI, dipakai dua kali di bawah.
        $belumTuntas = self::aktivitasTuntas($x, $final, $terkunci, $tipe, $online, $dicatatTim, $isPenawaran, $tipeBerjadwal);

        // ── UJIAN ONLINE YANG KEPUTUSANNYA MILIK ADMIN ──────────────────────
        //
        // Nilainya sudah masuk dari HCLearn, tapi alat tesnya memang tidak
        // berbunyi lulus/gagal (PAPI Kostick, DISC, Kraeplin). Yang tersisa
        // hanya satu hal: penilai menyatakan lulus atau tidak.
        //
        // Dihitung SEKALI di sini karena tiga tombol bergantung padanya —
        // dan ketiganya harus sepakat. Sebelumnya keadaan ini menyalakan
        // "Catat Hasil" (jendela berisi bidang nilai & catatan yang tak satu
        // pun perlu diisi) dan membiarkan "Sinkronkan" ikut tampil, padahal
        // yang ditunggu bukan HCLearn melainkan orang di kantor ini.
        $butuhKeputusan = $online
            && ($x->Peran ?? '') === 'INFORMATIF'
            && ($x->Status ?? '') === 'MENUNGGU_KEPUTUSAN';

        return [
            'id' => Hashids::encode($x->Id_Lamaran_Tahap_Tes),
            'label' => $x->Label,
            // Tipe MILIK AKTIVITAS INI — dalam satu tahap bisa bercampur ujian
            // online, tes manual, dan wawancara; admin harus bisa membedakannya.
            'tipe' => $x->Tipe_Tahap_Kode ?? null,
            'tipeNama' => $tipe->Nama ?? null,
            'tipeIkon' => $tipe->Ikon ?? null,
            'provider' => $x->Provider,
            'peran' => $x->Peran,
            'wajib' => $x->Wajib === 'Y',
            'status' => $x->Status,
            'hasil' => $x->Hasil,
            'nilai' => $x->Nilai !== null ? (float) $x->Nilai : null,
            'catatan' => $x->Catatan ?? null,
            // Catatan penilaian LENGKAP (berformat). Hanya untuk mata admin —
            // portal kandidat tidak pernah menerimanya; lihat portalDetail().
            'catatanHtml' => $x->Catatan_Html ?? null,
            // Berkas hasil yang melekat pada aktivitas INI (form wawancara yang
            // dipindai, lembar jawaban tes offline) — diunggah TIM.
            'berkas' => $berkas,
            // Berkas yang diserahkan KANDIDAT untuk aktivitas ini, berikut
            // aturan yang berlaku baginya. Aturannya ikut dikirim supaya layar
            // bisa membedakan "belum mengunggah padahal wajib" dari "aktivitas
            // ini memang tidak meminta apa-apa" — dua keadaan yang menuntut
            // tindakan berbeda dari tim.
            'unggahKandidat' => ($x->Unggah_Kandidat ?? 'T') === 'Y' ? [
                'wajib' => ($x->Unggah_Wajib ?? 'T') === 'Y',
                'petunjuk' => $x->Unggah_Petunjuk ?? null,
                'format' => array_values(array_filter(array_map('trim', explode(',', (string) ($x->Unggah_Format ?: 'pdf'))))),
                'maksMb' => (int) ($x->Unggah_Maks_Mb ?: 0),
                // KANDIDAT SUDAH MENYATAKAN LENGKAP? Pembeda antara "masih
                // mengunggah" dan "menunggu dinilai". Menilai pekerjaan yang
                // belum dinyatakan selesai adalah keputusan yang tak bisa
                // ditarik — tim perlu melihat bedanya sebelum menekan apa pun.
                'terkirim' => $x->Unggah_Kirim_At ? (string) $x->Unggah_Kirim_At : null,
                'terkirimOleh' => $x->Unggah_Kirim_By ?? null,
            ] : null,
            'berkasKandidat' => $berkasKandidat,
            // Tipe yang menuntut waktu & tempat (wawancara, MCU, tes offline).
            // Dibaca dari Master Tipe Tahap — BUKAN daftar kode di dalam kode
            // program, supaya tipe baru cukup ditambahkan lewat master.
            // JADWAL ULANG BERHENTI setelah kehadiran ditetapkan. Menggeser
            // jadwal untuk orang yang sudah datang (atau sudah dinyatakan tidak
            // datang) tidak berarti apa-apa — yang tersisa hanyalah tombol yang
            // mengundang salah tekan, dan sekali ditekan undangannya terkirim.
            'butuhJadwal' => ($tipe->Flag_Jadwal ?? 'T') === 'Y' && ! $final && ! $terkunci && empty($x->Jadwal_Hadir),
            // ── URUTAN & VISIBILITAS ────────────────────────────────────────
            // Terkunci = tahapnya BERURUTAN dan giliran aktivitas ini belum
            // tiba. `menunggu` menyebut aktivitas mana yang ditunggu, supaya
            // admin tidak perlu menebak dari nomor urut.
            'terkunci' => $terkunci,
            'menunggu' => $terkunci ? $menungguAktivitas : null,
            // Aktivitas internal — dicatat tim, tidak pernah tampil di portal
            // kandidat. Ditandai di sini agar admin tahu bahwa yang ia lihat
            // memang tidak dilihat kandidat, dan tidak menunggu kandidat
            // melakukan apa pun.
            'internal' => ($x->Tampil_Kandidat ?? 'Y') !== 'Y',
            // Tipe yang MUSTAHIL daring (MCU, tes offline, tanda tangan kontrak).
            // Aturannya melekat di master, bukan ditebak dari nama tipe di layar.
            'wajibLuring' => ($tipe->Flag_Wajib_Luring ?? 'T') === 'Y',
            // Bentuk jadwal yang PALING MASUK AKAL untuk tipe ini — dari master.
            // Negosiasi gaji hampir selalu lewat telepon; tanpa bawaan, modal
            // membuka pada "Daring" dan admin harus ingat memindahkannya tiap
            // kali. Yang lupa akan mengirim undangan bertautan Meet untuk
            // percakapan yang sebenarnya cuma panggilan telepon.
            'modeJadwalBawaan' => $tipe->Mode_Jadwal_Bawaan ?? null,
            // TIAP TIPE MENYEBUT BERKAS & CATATANNYA SENDIRI.
            //
            // Dulu kotak lampiran selalu berbunyi "Form hasil wawancara /
            // berkas penilaian" untuk semua tipe — sehingga petugas MCU diminta
            // melampirkan form wawancara untuk hasil dari klinik. Yang paling
            // merugikan: orang yang membaca teliti justru ragu, mengira ia
            // membuka jendela yang salah, lalu menutupnya tanpa melampirkan
            // apa pun. Kosong → kalimat umum yang netral, bukan milik tipe lain.
            // Aktivitas ini punya kotak lampiran atau tidak — dari master.
            // Negosiasi gaji berlangsung lewat telepon dan tidak menghasilkan
            // dokumen; kotak kosong bertuliskan "Belum ada berkas dilampirkan"
            // membuat penilai yang teliti berhenti, mengira ada berkas yang
            // seharusnya ia punya.
            'berkasAktivitas' => ($tipe->Flag_Berkas_Aktivitas ?? 'Y') === 'Y',
            'labelBerkas' => $tipe->Label_Berkas ?? null,
            'petunjukBerkas' => $tipe->Petunjuk_Berkas ?? null,
            'labelCatatan' => $tipe->Label_Catatan ?? null,
            // Penanda agar modal "Catat Hasil" menampilkan bidang khusus MCU.
            'isMcu' => $isMcu,
            // Aktivitas ini MEMBAWA PENAWARAN (negosiasi, surat penawaran, kontrak).
            'penawaran' => $isPenawaran,
            // Penawaran BERDOKUMEN: tak ada yang perlu dikerjakan di rapor.
            // Barisnya keterangan belaka — memberinya lencana "Menunggu" membuat
            // admin mencari tombol yang memang tidak ada, dan surat penawaran
            // memang tidak menunggu apa-apa: ia diunggah saat keputusan diambil.
            'infoSaja' => $isPenawaran && ! $tipeBerjadwal,
            'selesai' => $final,
            // Kehadiran: NULL belum dicek, Y hadir, T tidak hadir.
            'hadir' => $x->Jadwal_Hadir ?? null,
            // Sudah dijadwalkan tapi kehadirannya belum dicatat -> tim harus
            // menetapkan itu dulu sebelum boleh mencatat hasil.
            'butuhKehadiran' => ! $final && ! empty($x->Jadwal_Mulai) && empty($x->Jadwal_Hadir),
            'mcu' => ($x->Mcu_Status ?? null) ? [
                'status' => $x->Mcu_Status,
                'penyedia' => $x->Mcu_Penyedia,
                'tanggal' => (string) ($x->Mcu_Tanggal ?: ''),
                'catatan' => $x->Mcu_Catatan,
            ] : null,
            'jadwal' => ($x->Jadwal_Mulai ?? null) ? [
                'mode' => $x->Jadwal_Mode,
                'daring' => strtoupper((string) $x->Jadwal_Mode) === 'DARING',
                'mulai' => (string) $x->Jadwal_Mulai,
                'selesai' => (string) ($x->Jadwal_Selesai ?: ''),
                'link' => $x->Jadwal_Link,
                'lokasi' => $x->Jadwal_Lokasi,
                // Tempat di luar master memakai penanda yang sama seperti di
                // layar, sehingga membuka kembali jendela jadwal langsung
                // menemukan pilihan "Lainnya" beserta isiannya.
                'lokasiId' => $x->Jadwal_Lokasi_Id
                    ? Hashids::encode($x->Jadwal_Lokasi_Id)
                    : (($x->Jadwal_Lokasi_Nama ?? null) ? MasterLokasiController::LAINNYA : null),
                'lokasiNama' => $x->Jadwal_Lokasi_Nama ?? null,
                'lokasiAlamat' => $x->Jadwal_Lokasi_Alamat ?? null,
                // Tempat LENGKAP berikut petanya — dipakai kartu lokasi di
                // rapor. Bentuknya sama untuk lokasi master maupun yang diketik.
                'tempat' => self::tempatJadwal($x),
                'catatan' => $x->Jadwal_Catatan,
                'olehSiapa' => $x->Jadwal_By,
                'kontak' => $x->Jadwal_Kontak ?? null,
            ] : null,
            // Peruntukan lokasi yang boleh dipilih untuk tipe aktivitas ini —
            // null = tidak dibatasi. Jendela jadwal menyaring dropdown-nya dari
            // sini, dan server memeriksa hal yang sama persis.
            'lokasiPeruntukan' => $tipe->Lokasi_Peruntukan_Kode ?? null,
            // ── "CATAT HASIL" HANYA UNTUK YANG TIDAK BISA DIJADWALKAN ────────
            //
            // Aktivitas yang PUNYA jadwal punya alurnya sendiri yang utuh:
            // Atur Jadwal → Hadir (berikut hasilnya) / Tidak Hadir. Menyisakan
            // "Catat Hasil" di sampingnya berarti dua jalan menuju keadaan yang
            // sama — sebelum dijadwalkan ia menawarkan mencatat hasil sesi yang
            // belum tentu terjadi, dan sesudah Hadir ia menawarkan mencatat
            // ulang sesuatu yang barusan dicatat.
            //
            // Yang tersisa memakainya: tipe yang memang TIDAK berjadwal
            // (Flag_Jadwal='T') — tanpa tombol ini, aktivitas itu tak punya
            // satu pun cara diselesaikan.
            // Ujian online ber-peran INFORMATIF TIDAK ikut di sini. Ia memang
            // menunggu verdict penilai, tapi jendela ini menawarkan nilai,
            // catatan, dan lampiran — tak satu pun yang perlu diisi, sebab
            // nilainya sudah datang dari HCLearn. Yang tersisa cuma "lulus atau
            // tidak", dan itu dua tombol, bukan sebuah formulir: lihat
            // `butuhKeputusan` di bawah.
            'dapatDicatat' => ($dicatatTim && ! $tipeBerjadwal) && ! $final && ! $terkunci,
            // HASILNYA DINILAI TIM — lepas dari tombol mana yang tampil.
            // Dipakai jendela "Hadir" untuk tahu perlu-tidaknya menampilkan
            // bidang hasil. `dapatDicatat` di atas hanya mengatur tombol
            // terpisahnya; menyatukan keduanya membuat bidang hasil ikut hilang
            // begitu tombolnya disembunyikan.
            'dinilaiTim' => $dicatatTim && ! $final,
            // CARA AKTIVITAS INI DINILAI — dari Master Alur, dibekukan saat
            // lamaran dibuat. Layar memakainya untuk menampilkan bidang yang
            // BENAR: kotak angka untuk tes tertulis, daftar predikat untuk
            // DISC/FGD, dan tak ada bidang nilai sama sekali untuk wawancara.
            // Memaksakan angka pada tes berpredikat membuat penilai mengarang
            // angka, dan angka karangan itu terbaca seolah hasil ukur.
            'penilaian' => self::bentukPenilaian($x),
            'nilaiTeks' => $x->Nilai_Teks ?? null,
            // AKTIVITAS INI MENAHAN YANG BERIKUTNYA?
            //
            // Sudah selesai, ber-mode MANUAL, dan belum ditekan "Lanjutkan".
            // Ditandai supaya tombolnya muncul tepat di aktivitas yang menahan
            // — bukan di aktivitas yang tertahan, tempat admin tak bisa
            // berbuat apa-apa.
            'perluLanjut' => $final
                && self::lanjutButuhTrigger($x->Lanjut_Mode ?? null)
                && empty($x->Lanjut_At),
            'sudahLanjut' => ! empty($x->Lanjut_At),
            // Ujian online yang sudah dijadwalkan boleh ditarik hasilnya kapan
            // pun — jaring pengaman saat webhook CAT tidak sampai. TIDAK ikut
            // dikunci urutan: bila hasilnya sudah ada di HCLearn, menahannya di
            // sini hanya membuat data yang sudah sah tidak bisa masuk.
            //
            // TIDAK saat keputusan yang ditunggu: hasilnya sudah sampai, dan
            // "Sinkronkan" di sebelah "Lulus / Tidak Lulus" membuat penilai
            // mengira masih ada yang harus ditarik dulu sebelum boleh memutus.
            'dapatSinkron' => $online && ! $final && ! $butuhKeputusan && ! empty($x->Penjadwalan_Tahap_Id),
            // "Tidak hadir" berlaku untuk keduanya — hanya tim yang tahu, dan
            // untuk ujian online inilah jalan keluar bila kandidat tak mengerjakan.
            // Penawaran BERJADWAL ikut: ia tak punya "Catat Hasil", jadi tanpa ini
            // kandidat yang tidak datang negosiasi tak bisa ditandai sama sekali.
            // "Tidak hadir" adalah ESCAPE HATCH untuk aktivitas yang kehadirannya
            // BELUM ditetapkan. Setelah ditetapkan — apa pun jawabannya — tombol
            // ini hanya menggandakan keputusan yang sudah diambil lewat pasangan
            // Hadir/Tidak Hadir, dan dua jalan menuju keadaan yang sama membuat
            // admin menebak mana yang benar.
            'dapatTidakHadir' => ($online || $dicatatTim || ($isPenawaran && $tipeBerjadwal))
                && ! $final && ! $terkunci && empty($x->Jadwal_Hadir),
            // Penanda UI: aktivitas ini menunggu hasil dari sistem lain.
            'online' => $online,
            // Layar memakainya untuk MENAMPILKAN sepasang tombol Lulus/Tidak
            // Lulus.
            'butuhKeputusan' => $butuhKeputusan,
            // ── ANGKANYA BERARTI, ATAU TIDAK? ────────────────────────────────
            //
            // Alat tes online ber-peran INFORMATIF (PAPI Kostick, DISC,
            // Kraeplin) mengeluarkan PROFIL, bukan nilai kelulusan. "78" di
            // sebelah lencana Selesai terbaca sebagai skor — padahal ia tidak
            // punya ambang batas, tidak bisa dibandingkan antar-alat, dan sama
            // sekali bukan dasar keputusan yang barusan diambil penilai.
            //
            // Berlaku SEBELUM maupun SESUDAH diputuskan: angka yang tak berarti
            // tidak berubah jadi berarti hanya karena verdict-nya sudah ada.
            //
            // Yang TETAP tampil: nilai ujian PENENTU (objektif, berambang
            // batas) dan nilai yang diketik tim sendiri pada aktivitas manual —
            // di sana angkanya memang sengaja ditulis seseorang.
            'skorBermakna' => ! ($online && ($x->Peran ?? '') === 'INFORMATIF'),
            // Sepasang tombolnya boleh ditekan — dipisah dari keadaan di atas
            // supaya aktivitas yang masih TERKUNCI URUTAN tetap terbaca "sudah
            // dites, tunggu keputusan" tanpa menawarkan tombol yang akan
            // ditolak server.
            'dapatPutusTes' => $butuhKeputusan && ! $final && ! $terkunci,
            // ── MASIH ADA YANG DITUNGGU DARI AKTIVITAS INI? ──────────────────
            //
            // Dipakai gerbang keputusan: "Lolos" dan "Talent Pool" tidak boleh
            // ditekan selama masih ada aktivitas penentu yang belum tuntas.
            //
            // Dulu gerbangnya cuma `butuhKehadiran`, dan itu MENSYARATKAN
            // jadwalnya sudah ada. Aktivitas yang belum dijadwalkan sama sekali
            // menghasilkan butuhKehadiran=false — bukan karena tidak ada yang
            // kurang, melainkan karena kehadiran memang belum mungkin
            // ditetapkan. Gerbangnya diam, dan kandidat yang wawancaranya belum
            // pernah dijadwalkan bisa diloloskan.
            'tuntas' => $belumTuntas === null,
            'alasanBelumTuntas' => $belumTuntas,
        ];
    }

    /**
     * Apa yang MASIH DITUNGGU dari satu aktivitas — null bila sudah tuntas.
     *
     * Satu tempat, satu aturan. Layar memakainya untuk menyebut apa yang kurang,
     * dan putus() memakainya untuk menolak keputusan yang tak berdasar; kalau
     * keduanya menghitung sendiri-sendiri, cepat atau lambat tombolnya mati
     * padahal server mengizinkan — atau sebaliknya, dan yang kedua itu celah.
     *
     * "TIDAK HADIR" DIHITUNG TUNTAS. Kandidat yang tidak datang sudah
     * menyelesaikan pertanyaannya: tak ada lagi yang perlu ditunggu darinya.
     */
    private static function aktivitasTuntas(
        object $x,
        bool $final,
        bool $terkunci,
        ?object $tipe,
        bool $online,
        bool $dicatatTim,
        bool $isPenawaran,
        bool $tipeBerjadwal
    ): ?string {
        // Sudah final, atau sudah dinyatakan tidak hadir → tak ada yang ditunggu.
        if ($final || ($x->Jadwal_Hadir ?? null) === 'T') {
            return null;
        }

        // Penawaran BERDOKUMEN tidak menuntut apa pun di rapor — suratnya
        // diunggah bersama keputusannya. Menghitungnya "belum tuntas" berarti
        // mengunci keputusan pada berkas yang justru baru bisa diunggah DI
        // DALAM jendela keputusan itu: kunci yang anak kuncinya ada di dalam.
        if ($isPenawaran && ! $tipeBerjadwal) {
            return null;
        }

        if ($terkunci) {
            return 'menunggu giliran aktivitas sebelumnya';
        }

        // Belum dijadwalkan padahal tipenya menuntut waktu & tempat — inilah
        // lubang yang dulu tak terlihat.
        if (($tipe->Flag_Jadwal ?? 'T') === 'Y' && empty($x->Jadwal_Mulai)) {
            return 'belum dijadwalkan';
        }

        if (! empty($x->Jadwal_Mulai) && empty($x->Jadwal_Hadir)) {
            return 'kehadiran belum ditetapkan';
        }

        if ($online) {
            // Nilainya SUDAH masuk, yang ditunggu keputusan penilainya.
            // Dibedakan karena tindakannya berbeda: yang pertama menunggu
            // kandidat/HCLearn, yang kedua menunggu ORANG DI KANTOR INI.
            return ($x->Status ?? '') === 'MENUNGGU_KEPUTUSAN'
                ? 'sudah dites — menunggu keputusan penilai'
                : 'hasil ujian belum masuk';
        }

        // MCU: yang ditunggu adalah STATUS KESEHATANNYA, bukan verdict lulus/gagal
        // — verdict-nya diturunkan dari status itu (lihat Flag_Lolos di master).
        if (($x->Tipe_Tahap_Kode ?? '') === 'MCU') {
            return empty($x->Mcu_Status) ? 'hasil MCU belum dicatat' : null;
        }

        if ($dicatatTim) {
            return 'hasil belum dicatat';
        }

        return null;
    }

    /**
     * PATCH /api/v1/lamaran/tahap/{id}/putus — admin ketuk palu.
     *
     * Boleh membawa HASIL MCU sekaligus. Pemeriksaan kesehatan tidak punya
     * keputusan sendiri yang lepas dari nasib tahapnya, jadi keduanya dikirim
     * dalam satu permintaan — mustahil ada keputusan tanpa hasil kesehatannya.
     */
    public function putus(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            // Daftar hasil dibaca dari MASTER, bukan ditulis mati — menambah
            // hasil baru cukup satu baris di Master Hasil Keputusan.
            'hasil' => ['required', Rule::in(\App\Support\Career\LamaranService::masterHasilKeputusan()->keys()->all())],
            // ── TANPA BATAS PANJANG, DAN ITU DISENGAJA ───────────────────────
            //
            // Kolomnya VARCHAR(MAX). Batas di sini dulu 500 dan 200.000; yang
            // pertama tercapai oleh catatan wawancara biasa, yang kedua oleh
            // catatan berisi pindaian lembar penilaian. Keduanya menolak SETELAH
            // penilai selesai menulis — isinya hilang, dan tak ada cara
            // memperpendeknya tanpa membuang penilaian yang memang perlu ada.
            //
            // Menggeser angkanya hanya memindahkan tanggal kejadiannya. Yang
            // membatasi sekarang adalah ukuran badan permintaan (post_max_size),
            // satu tempat, dan itu memang batas yang benar.
            'catatan' => 'nullable|string',
            // Alasan berformat — keputusan yang menutup lamaran orang layak
            // ditulis selengkap catatan wawancara, bukan satu baris.
            'catatanHtml' => 'nullable|string',
            // Kandidat ini disimpan di Talent Pool atau tidak. HANYA dipakai
            // untuk hasil ber-Flag_Pilih_Talent_Pool='Y' (pengunduran diri &
            // penolakan penawaran); hasil lain tetap mengikuti masternya.
            'talentPool' => 'nullable|boolean',
            // Tanggal kandidat MENYATAKAN mundur/menolak — beda dari kapan admin
            // mencatatnya. Kandidat kerap mengabari lewat telepon beberapa hari
            // sebelum tercatat, dan selisih itu menentukan sejak kapan kursinya
            // sebenarnya kosong.
            'tanggalKonfirmasi' => 'nullable|date',
        ]);

        try {
                // Keputusan yang datang DARI KANDIDAT menuntut alasan yang benar-benar
            // ditulis. Batas 10 karakter menyaring isian asal seperti "-" atau
            // "ok" yang tak berguna saat ditinjau berbulan-bulan kemudian.
            $defHasil = \App\Support\Career\LamaranService::masterHasilKeputusan()->get($data['hasil']);

            // Alasannya dihitung dari catatan berformat bila itu yang diisi —
            // penilai yang menulis di editor tidak menyentuh field polos sama
            // sekali, dan tanpa ini ia ditolak "alasan wajib diisi" padahal
            // baru saja menulis tiga paragraf.
            [$htmlPutus, $catatanPutus] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

            if (($defHasil->Flag_Oleh_Kandidat ?? 'T') === 'Y'
                && mb_strlen(trim((string) $catatanPutus)) < 10) {
                return ResponseHelper::error('Alasan wajib diisi, minimal 10 karakter.', 422);
            }

            // ── GERBANG KETUNTASAN ──────────────────────────────────────────
            //
            // Hasil yang MEMAJUKAN kandidat (Lolos, Talent Pool) menyatakan
            // "orang ini cukup baik". Pernyataan itu tidak boleh dibuat selama
            // masih ada aktivitas penentu yang belum dijadwalkan, belum
            // ditetapkan kehadirannya, atau belum dicatat hasilnya.
            //
            // DITEGAKKAN DI SINI, bukan cukup dengan mematikan tombolnya. Layar
            // bisa basi (jadwal baru saja dihapus di tab lain), dan pintu ini
            // tetap bisa diketuk langsung tanpa lewat layar sama sekali.
            //
            // Sebabnya DISEBUTKAN satu per satu. "Tidak bisa diloloskan" tanpa
            // keterangan cuma memindahkan tebakan ke orang berikutnya.
            if (($defHasil->Flag_Butuh_Tuntas ?? 'T') === 'Y') {
                $sisa = self::belumTuntasTahap(
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                        ->where('Lamaran_Tahap_Id', $realId)->orderBy('Urutan')->get(),
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)->value('Urutan_Aktivitas'),
                );

                if ($sisa) {
                    $rinci = collect($sisa)->map(fn ($s) => "{$s['label']} ({$s['sebab']})")->implode(', ');

                    return ResponseHelper::error(
                        "\"{$defHasil->Nama}\" belum bisa diambil — masih ada aktivitas yang belum tuntas: {$rinci}. "
                        . 'Selesaikan dulu, atau gunakan Tidak Lolos / Tahan Dulu bila memang harus ditutup sekarang.',
                        422,
                    );
                }
            }

            // Pilihan Talent Pool hanya berlaku bila masternya memang menyerahkan
            // keputusan itu ke admin. Untuk hasil lain, apa pun yang dikirim
            // layar diabaikan — arti LULUS/GUGUR tidak boleh bisa digeser dari
            // sisi klien.
            $talentPool = ($defHasil->Flag_Pilih_Talent_Pool ?? 'T') === 'Y'
                ? (bool) ($data['talentPool'] ?? (($defHasil->Flag_Talent_Pool ?? 'T') === 'Y'))
                : null;

            // GERBANG CUT-OFF. Tahap yang berada SEBELUM titik cut-off Talent
            // Pool tidak boleh menyimpan siapa pun — apa pun yang dikirim layar.
            //
            // Ditegakkan di server, bukan cukup dengan menyembunyikan pilihannya:
            // layar bisa basi (cut-off alur baru saja diubah) atau dilewati lewat
            // DevTools, dan akibatnya kandidat yang belum pernah dinilai masuk
            // Talent Pool tanpa ada yang menyadarinya.
            $tahapCutoff = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $realId)
                ->value('Flag_Talent_Pool');

            if ($talentPool && ($tahapCutoff ?? 'T') !== 'Y') {
                $talentPool = false;
            }

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first(['Lamaran_Id']);

            // ── SATU TRANSAKSI: CATATANNYA IKUT BATAL BILA PALUNYA DITOLAK ───
            //
            // Dua pembaruan di bawah dulu berdiri sendiri, dijalankan SEBELUM
            // gerbang-gerbang di ketukPalu() sempat berkata "tidak". Akibatnya
            // keputusan yang DITOLAK tetap meninggalkan jejaknya: tanggal
            // konfirmasi kandidat dan catatan keputusan sudah tertulis di tahap
            // yang sebenarnya tidak jadi diputus. Orang berikutnya membaca
            // tahap berstatus BERJALAN yang memuat alasan penolakan lengkap —
            // dan tidak punya cara tahu bahwa keputusan itu tak pernah terjadi.
            //
            // Sekarang keduanya sehidup-semati dengan palunya.
            $hasil = DB::transaction(function () use ($realId, $data, $htmlPutus, $catatanPutus, $talentPool) {
                if (! empty($data['tanggalKonfirmasi'])) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)
                        ->update(['Tanggal_Konfirmasi_Kandidat' => $data['tanggalKonfirmasi']]);
                }

                // Versi berformatnya disimpan terpisah: ketukPalu() hanya menerima
                // teks polos karena ia juga dipanggil mesin (auto-gugur) yang tak
                // pernah punya HTML.
                if ($htmlPutus !== null) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $realId)
                        ->update(['Catatan_Html' => $htmlPutus]);
                }

                // HASIL MCU TIDAK LAGI DITULIS DI SINI.
                //
                // Status kesehatan dicatat saat KEHADIRAN ditetapkan — satu
                // peristiwa: kandidat datang ke klinik, diperiksa, inilah hasilnya.
                // Membiarkan pintu kedua di jendela keputusan berarti dua tempat
                // bisa menulis kolom yang sama dan berselisih diam-diam: "Unfit"
                // dari klinik lalu ditimpa "Fit" oleh orang yang mengetuk palu.
                // Lihat subTesKehadiran().

                $r = $this->svc->ketukPalu((int) $realId, $data['hasil'], $catatanPutus, (int) session('career_auth.id'), $talentPool);

                // ketukPalu() MENOLAK lewat nilai balik, bukan lemparan — dan
                // nilai balik tidak membatalkan transaksi. Ditinggikan jadi
                // lemparan supaya penolakannya benar-benar menggulung balik dua
                // pembaruan di atas. DomainException dipilih karena artinya
                // memang itu: aturan bisnis menolak, bukan sistem yang rusak —
                // dan penangkapnya di bawah menerjemahkannya jadi 422, bukan 500.
                if (! $r['ok']) {
                    throw new \DomainException($r['pesan']);
                }

                return $r;
            });

            // KABARI KANDIDAT. Keputusan admin sebelumnya tidak mengirim email
            // sama sekali — kandidat baru tahu kalau kebetulan membuka portal.
            //
            // TALENT_POOL sengaja TIDAK dikabari: ia bukan hasil seleksi yang
            // perlu diumumkan, melainkan catatan internal bahwa kandidat
            // disimpan untuk kesempatan lain. Mengirim email untuk itu justru
            // membingungkan — kandidat merasa diterima padahal tidak.
            // SIAPA YANG DIKABARI DIBACA DARI MASTER, bukan dari daftar mati.
            //
            // Master_Hasil_Keputusan sudah lama punya Flag_Kirim_Email —
            // dan kode ini tidak pernah membacanya. Yang berlaku adalah daftar
            // ['LULUS','GUGUR'] yang ditulis di sini. Akibatnya dua hal yang
            // sama-sama sunyi: admin yang mematikan flag itu di master tidak
            // mengubah apa pun, dan hasil keputusan BARU — mis. "Ditangguhkan" —
            // lahir tanpa email meski masternya menyalakannya.
            //
            // TALENT_POOL, DITOLAK_KANDIDAT, dan MENGUNDURKAN_DIRI tetap diam,
            // persis seperti sebelumnya: ketiganya sudah ber-Flag_Kirim_Email='T'
            // di master. Kandidat yang disimpan di Talent Pool memang tidak layak
            // dikirimi surat "belum dapat kami lanjutkan" — itu bukan penolakan
            // dan bukan kelulusan; dan yang menutup lamarannya sendiri tidak
            // perlu diberi tahu keputusan yang ia buat sendiri.
            //
            // Lulus-atau-tidaknya pun dari Flag_Lolos, sebab itulah yang
            // menentukan surat mana yang dikirim.
            if ($tahap && ($defHasil->Flag_Kirim_Email ?? 'T') === 'Y') {
                $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, ($defHasil->Flag_Lolos ?? 'T') === 'Y');
            }

            return ResponseHelper::success(null, $hasil['pesan']);
        } catch (\DomainException $e) {
            // Penolakan aturan bisnis dari ketukPalu() — transaksinya sudah
            // digulung balik, jadi tidak ada satu pun kolom yang berubah.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
        }
    }

    /**
     * PATCH /api/v1/karir/lamaran/tahap/{id}/hold — TAHAN atau LEPASKAN kandidat.
     *
     * Keadaan ketiga yang selama ini tidak ada. Worklist hanya punya dua jalan:
     * putuskan sekarang, atau biarkan menggantung tanpa keterangan. Yang kedua
     * yang selalu dipakai — dan akibatnya tak ada yang bisa membedakan kandidat
     * yang sedang ditunggu dari kandidat yang terlupakan.
     *
     * Kandidat TETAP di bucket tahapnya: tidak dipindah, tidak digugurkan,
     * tidak kehilangan apa pun. Yang bertambah hanya penanda + alasannya.
     *
     * TIDAK MENGIRIM EMAIL / WA — disengaja, dan bukan karena belum sempat.
     * Hold adalah keadaan internal; mengabari kandidat "lamaran Anda ditahan"
     * tidak menjawab apa pun baginya dan hanya menimbulkan kecemasan atas
     * sesuatu yang tak bisa ia pengaruhi. Kabar yang berarti adalah keputusan,
     * dan itu tetap dikirim seperti biasa saat hold dilepas lalu diputus.
     */
    public function hold(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'hold' => 'required|boolean',
            // Alasan DARI MASTER, bukan ketikan bebas: inilah yang dihitung
            // saat menjelaskan kenapa satu lowongan lama terisi, dan tiga
            // ketikan berbeda untuk sebab yang sama tak akan pernah
            // terkelompokkan.
            'alasanKode' => ['nullable', 'string', 'max:40'],
            'catatan' => 'nullable|string',
            'catatanHtml' => 'nullable|string',
        ]);

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first();
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }
        if ($tahap->Status === 'SELESAI') {
            return ResponseHelper::error('Tahap ini sudah diputus — tidak bisa ditahan lagi.', 409);
        }

        $menahan = (bool) $data['hold'];
        [$html, $ringkas] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);
        $now = now();
        $nama = session('career_auth.nama', 'ADMIN');
        $adminId = session('career_auth.id');

        if ($menahan) {
            if (($tahap->Hold_Flag ?? 'T') === 'Y') {
                return ResponseHelper::error('Kandidat ini sudah ditahan.', 409);
            }

            $alasan = self::masterAlasanHold()->get((string) ($data['alasanKode'] ?? ''));
            if (! $alasan) {
                return ResponseHelper::error('Pilih alasan penahanan.', 422);
            }
            // Alasan ber-Butuh_Catatan wajib dijelaskan. "Lainnya" tanpa
            // keterangan tidak menjelaskan apa pun saat ditinjau berbulan-bulan
            // kemudian — sama saja tidak memilih alasan.
            if (($alasan->Butuh_Catatan ?? 'T') === 'Y' && trim((string) $ringkas) === '') {
                return ResponseHelper::error("Alasan \"{$alasan->Nama}\" menuntut keterangan tambahan.", 422);
            }
        } elseif (($tahap->Hold_Flag ?? 'T') !== 'Y') {
            return ResponseHelper::error('Kandidat ini tidak sedang ditahan.', 409);
        }

        DB::transaction(function () use ($realId, $tahap, $menahan, $data, $html, $ringkas, $now, $nama, $adminId) {
            DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->update($menahan ? [
                'Hold_Flag' => 'Y',
                'Hold_Alasan_Kode' => $data['alasanKode'],
                'Hold_Alasan' => $ringkas,
                'Hold_Alasan_Html' => $html,
                'Hold_At' => $now,
                'Hold_By' => $nama,
                'Hold_By_Id' => $adminId,
                // Jejak pelepasan sebelumnya dibersihkan supaya tidak terbaca
                // sebagai "sudah dilepas" pada penahanan yang baru ini.
                'Hold_Lepas_At' => null,
                'Hold_Lepas_By' => null,
                'Hold_Lepas_By_Id' => null,
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ] : [
                'Hold_Flag' => 'T',
                'Hold_Lepas_At' => $now,
                'Hold_Lepas_By' => $nama,
                'Hold_Lepas_By_Id' => $adminId,
                // Alasan penahanannya TIDAK dihapus: itu bagian dari riwayat
                // tahap ini, dan menghapusnya membuat "kenapa dulu tertahan"
                // hilang begitu saja.
                'Updated_At' => $now, 'Updated_By' => $nama, 'Updated_By_Id' => $adminId,
            ]);

            // Riwayat terpisah: satu kandidat bisa ditahan-dilepas berkali-kali,
            // dan kolom di atas hanya menyimpan yang terakhir.
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Hold')->insert([
                'Lamaran_Tahap_Id' => $realId,
                'Lamaran_Id' => $tahap->Lamaran_Id,
                'Aksi' => $menahan ? 'TAHAN' : 'LEPAS',
                'Alasan_Kode' => $menahan ? $data['alasanKode'] : null,
                'Alasan' => $ringkas,
                'Alasan_Html' => $html,
                'Created_At' => $now,
                'Created_By' => $nama,
                'Created_By_Id' => $adminId,
            ]);
        });

        Log::channel('web_career')->info(
            'Tahap #' . $realId . ' ' . ($menahan ? 'DITAHAN' : 'DILEPAS dari tahan') . " oleh {$nama}."
        );

        // MENYUSUL KETINGGALAN. Selama ditahan, mesin keputusan sengaja tidak
        // menyimpulkan apa pun (lihat evaluasiTahap). Hasil tes yang masuk di
        // tengah penahanan karena itu belum pernah dinilai — dan tanpa evaluasi
        // ulang di sini, tahap yang seharusnya sudah otomatis maju akan diam
        // selamanya menunggu peristiwa yang tidak akan datang lagi.
        if (! $menahan) {
            $eval = $this->svc->evaluasiTahap((int) $realId, (int) $adminId);
            $outcome = $eval['outcome'] ?? null;

            // Kesimpulan otomatis yang menyusul TETAP dikabarkan — kandidat
            // tidak boleh dirugikan hanya karena keputusannya sempat tertunda
            // oleh urusan internal.
            if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
                $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, $outcome === 'LANJUT');
            }

            return ResponseHelper::success(
                ['outcome' => $outcome],
                'Penahanan dilepas — proses bisa dilanjutkan.'
            );
        }

        return ResponseHelper::success(null, 'Kandidat ditahan. Tidak ada pemberitahuan yang dikirim.');
    }

    /**
     * Pesan "berkas kelewat besar" yang benar-benar menolong.
     *
     * Menyebut DUA angka: batasnya, dan ukuran berkas yang barusan dipilih.
     * Tanpa yang kedua, orang yang berkasnya 2,1 MB dan yang berkasnya 40 MB
     * membaca kalimat yang sama persis — padahal yang satu cukup dikompres
     * sedikit, yang lain harus mengganti berkasnya sama sekali.
     */
    private static function pesanUkuran(Request $request, int $maksKb): string
    {
        $mb = fn (float $kb) => rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',');
        $file = $request->file('file');
        $batas = 'Ukuran berkas melebihi batas ' . $mb($maksKb) . ' MB.';

        if (! $file || ! $file->isValid()) {
            // Berkas yang GAGAL diunggah (mis. melebihi upload_max_filesize PHP)
            // tidak punya ukuran yang bisa dibaca — menyebut "0 MB" di situ
            // justru menyesatkan.
            return $batas . ' Perkecil dulu berkasnya, lalu unggah ulang.';
        }

        return $batas . ' Berkas yang dipilih berukuran ' . $mb($file->getSize() / 1024) . ' MB — perkecil dulu, lalu unggah ulang.';
    }

    /** Master alasan HOLD yang aktif, di-cache per permintaan. */
    private static ?\Illuminate\Support\Collection $alasanHoldCache = null;

    private static function masterAlasanHold(): \Illuminate\Support\Collection
    {
        return self::$alasanHoldCache ??= DB::table('N_WEB_CAREERS_Master_Alasan_Hold')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get()
            ->keyBy('Kode');
    }


    /** Upload berkas hasil tahap (MCU/Interview) — PDF/JPG, oleh admin/requester. */
    public function unggahBerkasTahap(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }
        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first();
        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg|max:2048']);

        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $tahap->Lamaran_Id)->select('l.Id_Lamaran', 'u.Nama')->first();

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasi($file->getClientOriginalName(), $ext, strlen($konten));
            // Akar `hasil-tahap/` + ruang per tipe tahap — lihat penjelasan
            // konsepnya di GcsBerkas::folderHasilTahap().
            $folder = $gcs->folderHasilTahap(
                $now->format('Y'),
                $now->format('m'),
                $now->format('d'),
                $lam->Nama ?? 'kandidat',
                $tahap->Tipe_Tahap_Kode,
            );
            $label = 'hasil-' . strtolower($tahap->Tipe_Tahap_Kode ?? 'tahap') . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
            $path = $gcs->unggah($folder, $label, $ext, $konten);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->insert([
            'Lamaran_Tahap_Id' => $realId,
            'Lamaran_Id' => $tahap->Lamaran_Id,
            'Jenis' => $tahap->Tipe_Tahap_Kode,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ukuran' => strlen($konten),
            'Ext' => $gcs->normalkanExt($ext),
            'Created_At' => $now, 'Created_By' => session('career_auth.nama', 'ADMIN'), 'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
        ]);

        Log::channel('web_career')->info("Berkas hasil tahap #{$realId} diunggah ({$file->getClientOriginalName()}).");

        return ResponseHelper::success(null, 'Berkas terunggah.');
    }

    /** Daftar berkas hasil sebuah tahap. */
    public function berkasTahap(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $rows = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Lamaran_Tahap_Id', $realId)->orderByDesc('Id_Lamaran_Tahap_Berkas')->get();

        return ResponseHelper::success($rows->map(fn ($b) => [
            'id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas),
            'nama' => $b->Nama_File,
            'ext' => $b->Ext,
            'ukuran' => (int) $b->Ukuran,
            'url' => route('career.api.lamaran.tahap.berkas.file', Hashids::encode($b->Id_Lamaran_Tahap_Berkas)),
            'createdAt' => $b->Created_At,
        ])->values(), 'Berkas tahap');
    }

    /** Serve berkas hasil tahap (signed URL GCS 15 menit). */
    public function berkasTahapFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->first();
        if (! $b) {
            abort(404);
        }
        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Signed URL berkas tahap gagal: ' . $e->getMessage());
        }
        abort(404, 'File tidak ditemukan.');
    }

    /** Hapus berkas hasil tahap. */
    public function hapusBerkasTahap(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->first();
        if (! $b) {
            return ResponseHelper::error('Tidak ditemukan', 404);
        }
        // URUTANNYA: BASIS DATA DULU, GCS BELAKANGAN.
        //
        // Dulu terbalik. Bila objeknya sudah dihapus dari GCS lalu penghapusan
        // barisnya gagal, yang tersisa adalah lampiran yang tetap terdaftar di
        // layar tetapi tidak bisa dibuka siapa pun — dan tak ada cara
        // menghapusnya lagi selain lewat basis data langsung. Terbalik seperti
        // sekarang, kegagalan paling buruk hanya menyisakan objek yatim di GCS:
        // tidak terlihat, tidak mengganggu, dan bisa disapu belakangan.
        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->delete();

        try {
            app(GcsBerkas::class)->hapus([$b->Path_File]);
        } catch (\Throwable $e) {
            // Dicatat, tidak ditelan diam-diam: objek yatim yang tak pernah
            // dilaporkan adalah tagihan penyimpanan yang tak pernah dijelaskan.
            Log::channel('web_career')->warning('[BERKAS-TAHAP] sisa GCS gagal dihapus: ' . $e->getMessage());
        }

        return ResponseHelper::success(null, 'Berkas dihapus.');
    }

    /**
     * GET /api/v1/karir/lamaran/{id}/laporan/opsi — formulir apa saja yang bisa
     * dicetak untuk kandidat ini.
     *
     * Satu lamaran bisa punya beberapa pengisian: MT mengumpulkan data DUA KALI
     * (pendaftaran, lalu kelengkapan data diri di tahap berikutnya). Karena itu
     * admin diberi PILIHAN, bukan ditebakkan: yang terbaru ditandai `utama` dan
     * tercentang lebih dulu, tapi laporan gabungan tetap mungkin bila memang
     * perlu membandingkan jawaban lama dan baru.
     */
    public function laporanOpsi(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $formulir = \App\Support\Career\LaporanKandidat::daftarFormulir((int) $realId);

        return ResponseHelper::success([
            'formulir' => $formulir,
            // Layar memakai ini untuk memutuskan perlu-tidaknya menampilkan
            // pilihan sama sekali: satu formulir = tak ada yang perlu dipilih.
            'perluPilih' => count($formulir) > 1,
        ], 'Opsi laporan');
    }

    /**
     * POST /api/v1/karir/lamaran/{id}/laporan — minta cetak laporan kandidat.
     *
     * Selalu lewat ANTREAN. Merender PDF berisi foto tertanam + seluruh
     * perjalanan tahap memakan beberapa detik; dikerjakan di dalam permintaan
     * ini, admin menatap layar membeku lalu menekan tombolnya lagi dan lahir
     * dua berkas untuk satu permintaan.
     */
    public function laporanBuat(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lamaran tidak valid.', 422);
        }

        $data = $request->validate([
            'format' => 'required|in:PDF,XLSX',
            'formulir' => 'nullable|array|max:20',
            'formulir.*' => 'string|max:64',
        ]);

        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->where('l.Id_Lamaran', $realId)
            ->select('l.Kode', 'u.Nama', 'p.Nama as ProgramNama')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        // Id formulir diterjemahkan DI SINI, bukan di dalam job: job berjalan
        // tanpa konteks permintaan, dan hashid yang tidak sah lebih baik
        // ditolak sekarang selagi ada yang menunggu jawabannya.
        $pengisianIds = collect($data['formulir'] ?? [])
            ->map(fn ($h) => Hashids::decode($h)[0] ?? null)
            ->filter()
            ->values()
            ->all();

        $exportId = DB::table('N_WEB_CAREERS_Export_Log')->insertGetId([
            'Export_Type' => 'LAPORAN_KANDIDAT',
            'Id_Users' => session('career_auth.id'),
            'Keterangan' => trim(($lamaran->Nama ?: 'Kandidat') . ' — ' . ($lamaran->ProgramNama ?: '')),
            'Filters_Json' => json_encode([
                'lamaranId' => (int) $realId,
                'kodeLamaran' => $lamaran->Kode,
                'format' => $data['format'],
                'formulir' => $pengisianIds,
            ]),
            'Status_Export' => 'DIPROSES',
            'Progress_Chunk' => 0,
            'Progress_Total' => 1,
            'Flag_Cancellation' => 'T',
            'Created_At' => now(),
        ], 'Id_Export');

        WcLaporanKandidatJob::dispatch((int) $exportId, (int) $realId, $pengisianIds, $data['format']);

        return ResponseHelper::success(
            ['id' => Hashids::encode($exportId)],
            'Laporan sedang disiapkan. Tautan unduhnya muncul begitu selesai.',
        );
    }

    /**
     * GET /api/v1/karir/lamaran/laporan/{id} — status satu permintaan cetak.
     *
     * Dipakai layar untuk menanyakan "sudah jadi belum" tanpa menahan admin
     * menunggu di depan tombol yang membeku.
     */
    public function laporanStatus(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Export_Log')->where('Id_Export', $realId)->first() : null;

        if (! $row) {
            return ResponseHelper::error('Permintaan cetak tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'id' => Hashids::encode($row->Id_Export),
            'status' => $row->Status_Export,
            'selesai' => $row->Status_Export === 'SELESAI',
            'gagal' => $row->Status_Export === 'GAGAL',
            'pesan' => $row->Error_Message,
            // URL unduh lewat rute KITA, bukan URL bucket: berkasnya berisi data
            // pribadi kandidat, dan tautan bucket yang bocor bisa dibuka siapa pun.
            'url' => $row->Status_Export === 'SELESAI'
                ? route('career.api.lamaran.laporan.unduh', Hashids::encode($row->Id_Export))
                : null,
        ], 'Status laporan');
    }

    /** GET unduh berkas laporan (URL bertanda tangan 15 menit dari GCS). */
    public function laporanUnduh(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Export_Log')->where('Id_Export', $realId)->first() : null;

        if (! $row || $row->Status_Export !== 'SELESAI' || ! $row->File_Path) {
            abort(404);
        }

        // DIALIRKAN LEWAT ORIGIN KITA, BUKAN DIALIHKAN KE URL BERTANDA TANGAN.
        //
        // Pengalihan ke bucket memang lebih hemat, tetapi menutup satu-satunya
        // cara peramban mengetahui KEMAJUAN unduhan: permintaan lintas-origin ke
        // GCS ditolak sebelum satu byte pun terbaca, sehingga indikator progres
        // tak pernah bergerak dan berkasnya tak bisa disimpan otomatis.
        //
        // Berkas laporan satu kandidat berukuran ~1 MB dan dialirkan, bukan
        // dibaca utuh ke memori — biayanya sepadan dengan unduhan yang bisa
        // dipantau dan tersimpan sendiri begitu selesai.
        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if (! $disk->exists($row->File_Path)) {
                abort(404, 'Berkas laporan tidak ditemukan.');
            }

            $namaFile = basename($row->File_Path);
            $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

            return response()->streamDownload(
                function () use ($disk, $row) {
                    $aliran = $disk->readStream($row->File_Path);
                    if ($aliran) {
                        fpassthru($aliran);
                        fclose($aliran);
                    }
                },
                $namaFile,
                [
                    'Content-Type' => $ext === 'xlsx'
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : 'application/pdf',
                    // Panjangnya disebut supaya bilah kemajuan tahu totalnya;
                    // tanpa ini peramban hanya bisa melaporkan byte terunduh,
                    // dan persentasenya mustahil dihitung.
                    'Content-Length' => (string) $disk->size($row->File_Path),
                    'Cache-Control' => 'private, no-store',
                ],
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN] aliran unduhan gagal: ' . $e->getMessage());
        }

        abort(404, 'Berkas laporan tidak ditemukan.');
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/{id}/berkas — berkas milik SATU aktivitas.
     *
     * Dipisah dari berkasTahap() yang mengembalikan berkas seluruh tahap. Pada
     * tahap campuran (Psikotes 2 + DISC + Wawancara HR), daftar setingkat tahap
     * mencampur lembar jawaban DISC dengan form wawancara tanpa penanda mana
     * milik mana — dan penilai wawancara jadi tidak punya cara memastikan
     * berkas yang ia unggah barusan sudah masuk.
     */
    public function subTesBerkas(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        return ResponseHelper::success(self::daftarBerkasAktivitas((int) $realId), 'Berkas aktivitas');
    }

    /**
     * Berkas satu aktivitas dalam bentuk yang siap dikirim ke layar.
     *
     * Dipakai dua tempat — endpoint daftarnya sendiri dan rapor tes di worklist
     * — sehingga bentuk datanya mustahil berbeda antara "dimuat saat drawer
     * dibuka" dan "dimuat ulang setelah mengunggah".
     */
    private static function daftarBerkasAktivitas(int $subTesId): array
    {
        return self::bentukBerkasAktivitas(
            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                ->where('Lamaran_Tahap_Tes_Id', $subTesId)
                ->orderByDesc('Id_Lamaran_Tahap_Berkas')
                ->get()
        );
    }

    /**
     * GET /api/v1/karir/lamaran/sub-tes/berkas-kandidat/{id} — buka berkas yang
     * DIUNGGAH KANDIDAT, dari sisi admin.
     *
     * Endpoint portalnya (tesBerkasFile) memeriksa kepemilikan lewat Id_Users,
     * jadi mustahil dipakai admin: berkas milik kandidat lain selalu 404. Tanpa
     * pintu ini, seluruh aturan "kandidat wajib mengunggah" di Master Alur tidak
     * ada gunanya — berkasnya masuk tapi tak seorang pun di tim bisa membacanya.
     */
    public function berkasKandidatFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = $realId ? DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->first() : null;

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[BERKAS-KANDIDAT] signed URL gagal: ' . $e->getMessage());
        }

        abort(404);
    }

    /**
     * Berkas UNGGAHAN KANDIDAT sebuah aktivitas, dibentuk untuk layar ADMIN.
     *
     * Tautannya menunjuk rute admin, bukan rute portal — lihat alasannya di
     * berkasKandidatFile().
     */
    private static function bentukBerkasKandidat(iterable $rows): array
    {
        return collect($rows)
            ->map(function ($b) {
                $ext = strtolower($b->Ext ?: pathinfo($b->Nama_File, PATHINFO_EXTENSION));

                return [
                    'id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas),
                    'nama' => $b->Nama_File,
                    'ext' => $ext,
                    'ukuran' => (int) $b->Ukuran,
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'url' => route('career.api.lamaran.berkas.kandidat', ['id' => Hashids::encode($b->Id_Lamaran_Tes_Berkas)]),
                    'createdAt' => $b->Created_At,
                    // Kapan berkas INI ikut diserahkan — kosong berarti SUSULAN
                    // yang datang setelah pernyataan lengkap terakhir. Penilai
                    // perlu bisa membedakannya: berkas susulan belum tentu sudah
                    // dimaksudkan kandidat sebagai bagian dari yang dinilai.
                    'terkirim' => ($b->Terkirim_At ?? null) ? (string) $b->Terkirim_At : null,
                ];
            })
            ->values()
            ->all();
    }

    /** Baris berkas → bentuk untuk layar. Satu tempat, dipakai semua pemanggil. */
    private static function bentukBerkasAktivitas(iterable $rows): array
    {
        return collect($rows)
            ->map(fn ($b) => [
                'id' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas),
                'nama' => $b->Nama_File,
                'ext' => $b->Ext,
                'ukuran' => (int) $b->Ukuran,
                'isImage' => in_array(strtolower((string) $b->Ext), ['jpg', 'jpeg', 'png', 'webp'], true),
                'url' => route('career.api.lamaran.tahap.berkas.file', Hashids::encode($b->Id_Lamaran_Tahap_Berkas)),
                'olehSiapa' => $b->Created_By,
                'createdAt' => $b->Created_At,
            ])
            ->values()
            ->all();
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/{id}/berkas — unggah hasil satu aktivitas.
     *
     * Inilah tempat form wawancara yang dipindai berlabuh: melekat pada sesi
     * wawancara yang benar-benar dinilai, bukan pada tahapnya. OPSIONAL — tidak
     * semua wawancara memakai lembar penilaian cetak, dan mewajibkannya hanya
     * membuat penilai mengunggah berkas asal-asalan supaya bisa lanjut.
     *
     * Aktivitas yang SUDAH FINAL ditolak: berkas yang masuk setelah hasilnya
     * dicatat tidak pernah ikut menjadi dasar keputusan, sehingga membiarkannya
     * masuk hanya menciptakan bukti yang tampak mendukung padahal tidak dibaca
     * siapa pun saat memutus.
     */
    public function subTesBerkasUnggah(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            // `Urutan` ikut diambil: gerbang urutan aktivitas membacanya untuk
            // tahu aktivitas mana yang berada di depan yang ini.
            ->select('t.Id_Lamaran_Tahap_Tes', 't.Lamaran_Tahap_Id', 't.Urutan', 't.Label', 't.Tipe_Tahap_Kode', 't.Flag_Selesai', 'h.Lamaran_Id')
            ->first();

        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }
        if ($sub->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas ini sudah final — berkasnya tidak bisa ditambah lagi.', 409);
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg|max:2048'], [
            'file.required' => 'Tidak ada berkas yang dipilih.',
            'file.mimes' => 'Format berkas harus PDF atau JPG.',
            'file.max' => self::pesanUkuran($request, 2048),
        ]);

        $lam = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->where('l.Id_Lamaran', $sub->Lamaran_Id)
            ->select('u.Nama')
            ->first();

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasi($file->getClientOriginalName(), $ext, strlen($konten));
            $folder = $gcs->folderHasilTahap(
                $now->format('Y'),
                $now->format('m'),
                $now->format('d'),
                $lam->Nama ?? 'kandidat',
                $sub->Tipe_Tahap_Kode,
            );
            // Nama file mengandung potongan acak: satu aktivitas boleh menerima
            // beberapa lembar, dan nama deterministik akan menimpa yang lama.
            $label = 'hasil-' . strtolower($sub->Tipe_Tahap_Kode ?: 'aktivitas') . '-' . Str::lower(Str::random(6));
            $path = $gcs->unggah($folder, $label, $ext, $konten);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->insert([
            'Lamaran_Tahap_Id' => $sub->Lamaran_Tahap_Id,
            'Lamaran_Tahap_Tes_Id' => $sub->Id_Lamaran_Tahap_Tes,
            'Lamaran_Id' => $sub->Lamaran_Id,
            'Jenis' => $sub->Tipe_Tahap_Kode,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ukuran' => strlen($konten),
            'Ext' => $gcs->normalkanExt($ext),
            'Created_At' => $now,
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
            'Updated_At' => $now,
        ]);

        Log::channel('web_career')->info("Berkas aktivitas #{$realId} ({$sub->Label}) diunggah: {$file->getClientOriginalName()}");

        return ResponseHelper::success(self::daftarBerkasAktivitas((int) $realId), 'Berkas terunggah.');
    }

    /**
     * POST /api/v1/karir/lamaran/catatan/gambar — unggah gambar untuk DITANAM
     * di dalam catatan berformat (Quill).
     *
     * Yang kembali hanyalah TAUTAN ke rute penyaji, bukan berkasnya sendiri dan
     * bukan pula data URI. Alasannya ada di App\Support\Career\HtmlBersih:
     * hanya bentuk tautan inilah yang lolos penyaring saat catatannya disimpan.
     *
     * Gambar diunggah SEBELUM catatannya disimpan — itu sifat editor teks. Maka
     * baris di sini boleh yatim untuk sementara (catatannya batal ditulis).
     * Itu disengaja: memaksa keduanya satu transaksi berarti gambar baru bisa
     * tampil setelah catatan disimpan, dan penilai menulis sambil melihat
     * kotak kosong.
     */
    public function catatanGambarUnggah(Request $request)
    {
        // Pesan DALAM BAHASA INDONESIA dan MENYEBUT ANGKANYA.
        //
        // Bawaan Laravel berbunyi "The file field must not be greater than 2048
        // kilobytes." — bahasa asing, satuan yang tak lazim dibaca orang, dan
        // tidak menyebut berkas yang barusan dipilih sebesar apa. Yang membaca
        // jadi tidak tahu harus memperkecil sampai berapa.
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'file.required' => 'Tidak ada berkas yang dipilih.',
            'file.mimes' => 'Format gambar harus JPG, PNG, atau WEBP.',
            'file.max' => self::pesanUkuran($request, 2048),
            // Konteksnya ikut bila diketahui — editor selalu dibuka dari sebuah
            // aktivitas atau tahap, jadi asal gambarnya tidak perlu ditebak
            // belakangan lewat mencocokkan tautan di dalam HTML.
            'subTesId' => 'nullable|string|max:64',
            'tahapId' => 'nullable|string|max:64',
        ]);

        $subTesId = $request->filled('subTesId') ? (Hashids::decode($request->input('subTesId'))[0] ?? null) : null;
        $tahapId = $request->filled('tahapId') ? (Hashids::decode($request->input('tahapId'))[0] ?? null) : null;

        // Lamaran ditelusuri dari konteksnya supaya gambar tersimpan di folder
        // kandidat yang benar — bucket yang bisa ditelusuri per orang jauh lebih
        // berguna daripada satu tumpukan bernama tanggal.
        $lamaranId = null;
        if ($subTesId) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
                ->value('h.Lamaran_Id');
        } elseif ($tahapId) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $tahapId)->value('Lamaran_Id');
        }

        $nama = $lamaranId
            ? DB::table('N_WEB_CAREERS_Lamaran as l')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->where('l.Id_Lamaran', $lamaranId)
                ->value('u.Nama')
            : null;

        $gcs = app(GcsBerkas::class);
        $now = now();
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $konten = $file->get();

        try {
            $gcs->validasiGambar($file->getClientOriginalName(), $ext, strlen($konten));
            $path = $gcs->unggahGambarCatatan(
                $gcs->folderCatatan($now->format('Y'), $now->format('m'), $now->format('d'), $nama ?: 'kandidat'),
                $ext,
                $konten,
            );
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        $id = DB::table('N_WEB_CAREERS_Catatan_Gambar')->insertGetId([
            'Konteks' => $subTesId ? 'LAMARAN_TAHAP_TES' : ($tahapId ? 'LAMARAN_TAHAP' : 'LEPAS'),
            'Ref_Id' => $subTesId ?: $tahapId,
            'Lamaran_Id' => $lamaranId ?: null,
            'Nama_File' => $file->getClientOriginalName(),
            'Path_File' => $path,
            'Mime' => $file->getClientMimeType(),
            'Ext' => $gcs->normalkanExt($ext),
            'Ukuran' => strlen($konten),
            'Created_At' => $now,
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
        ]);

        return ResponseHelper::success([
            'id' => Hashids::encode($id),
            // Path relatif, bukan URL absolut: inilah yang disimpan ke dalam
            // HTML, dan menyimpan nama host di sana membuat seluruh gambar mati
            // begitu domainnya berganti (dev → staging → production).
            'url' => '/api/v1/karir/lamaran/catatan/gambar/' . Hashids::encode($id),
        ], 'Gambar terunggah.');
    }

    /**
     * GET /karir/laporan/{lamaran}/berkas/{berkas} — dokumen yang ditautkan
     * dari dalam PDF laporan. BERTANDA TANGAN, tanpa sesi.
     *
     * Pembaca PDF tidak membawa cookie; tautan ke rute admin biasa akan selalu
     * mendarat di halaman login. Yang menggantikan sesi di sini adalah tanda
     * tangan HMAC pada URL-nya (middleware `signed`) berikut kedaluwarsanya.
     *
     * DUA GERBANG, bukan satu:
     *   1. Tanda tangan sah & belum kedaluwarsa — dijaga middleware.
     *   2. Berkasnya BENAR milik lamaran yang disebut di URL — dijaga di sini.
     *      Tanpa nomor 2, satu tautan sah bisa dipelintir nomor berkasnya dan
     *      berubah jadi kunci ke dokumen kandidat lain.
     */
    public function laporanBerkas(string $lamaran, string $berkas)
    {
        $lamaranId = Hashids::decode($lamaran)[0] ?? null;
        $berkasId = Hashids::decode($berkas)[0] ?? null;

        if (! $lamaranId || ! $berkasId) {
            abort(404);
        }

        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
            ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
            ->where('fb.Id_Formulir_Berkas', $berkasId)
            ->where('fp.Lamaran_Id', $lamaranId)
            ->select('fb.Path_File', 'fb.Nama_Asli')
            ->first();

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        // Dicatat: tautan ini hidup di luar sesi, jadi jejaknya satu-satunya
        // cara mengetahui dokumen siapa yang dibuka dari salinan PDF yang mana.
        Log::channel('web_career')->info(
            "[LAPORAN-BERKAS] lamaran #{$lamaranId} berkas #{$berkasId} dibuka dari tautan bertanda tangan"
        );

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if ($disk->exists($b->Path_File)) {
                return redirect()->away($disk->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[LAPORAN-BERKAS] signed URL gagal: ' . $e->getMessage());
        }

        abort(404, 'Berkas tidak ditemukan.');
    }

    /**
     * GET /api/v1/karir/lamaran/catatan/gambar/{id} — sajikan gambar catatan.
     *
     * Lewat rute berwenang, bukan tautan publik GCS: isi catatan penilaian
     * adalah bahan internal, dan tautan bucket yang bisa dibuka siapa saja akan
     * membocorkannya begitu satu potongan HTML tersalin keluar.
     */
    public function catatanGambar(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $g = $realId ? DB::table('N_WEB_CAREERS_Catatan_Gambar')->where('Id_Catatan_Gambar', $realId)->first() : null;
        if (! $g) {
            abort(404);
        }

        try {
            $disk = Storage::disk(GcsBerkas::DISK);
            if ($disk->exists($g->Path_File)) {
                return redirect()->away($disk->temporaryUrl($g->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('Signed URL gambar catatan gagal: ' . $e->getMessage());
        }

        abort(404, 'Gambar tidak ditemukan.');
    }

    /**
     * Rapikan catatan berformat jadi sepasang nilai yang disimpan bersama:
     * HTML lengkapnya + ringkasan teks polos untuk kolom `Catatan`.
     *
     * Ringkasannya DITURUNKAN, bukan diketik terpisah. Dua kolom yang diisi
     * manusia secara terpisah pasti berselisih suatu saat, dan yang tampil di
     * daftar worklist adalah yang polos — begitu ia basi, admin membaca
     * kesimpulan lama untuk catatan yang sudah diperbarui.
     *
     * @return array{0: ?string, 1: ?string} [html, ringkasPolos]
     */
    private static function catatanKaya(?string $html, ?string $polos = null): array
    {
        $bersih = HtmlBersih::saring($html);

        if ($bersih === null) {
            $polos = trim((string) $polos);

            return [null, $polos !== '' ? $polos : null];
        }

        $teks = \App\Support\Career\HtmlBersih::keTeks($bersih);
        // Catatan yang isinya hanya gambar tetap perlu ringkasan yang berarti —
        // baris kosong di worklist terbaca "belum dicatat", padahal sudah.
        if ($teks === '') {
            $teks = '(catatan berupa gambar)';
        }

        return [$bersih, Str::limit($teks, 480)];
    }

    /**
     * POST /api/v1/lamaran/sub-tes/{id}/sinkron — TARIK HASIL DARI HCLEARN.
     *
     * Pengganti "catat manual" untuk ujian online. Kalau webhook CAT tak sampai
     * (jaringan putus, aplikasi restart, secret salah), dulu satu-satunya jalan
     * adalah admin mengetik nilai sendiri — menebak angka yang bukan miliknya.
     * Sekarang admin cukup menarik: nilainya dibaca LANGSUNG dari tabel CAT
     * (`HRIS_KANDIDAT_Ujian_Token` + `..._Ujian_Nilai_Akhir`, satu database),
     * lalu masuk lewat pintu yang sama dengan webhook. Yang tersimpan tetap
     * angka resmi penyedia, bukan ketikan orang.
     *
     * Aman diulang: bila hasilnya sudah tercatat, tahap tidak diproses dua kali.
     */
    public function subTesSinkron(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Hasil aktivitas ini sudah final — tidak perlu disinkronkan.', 422);
            }

            $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';
            if (! $online) {
                return ResponseHelper::error("\"{$sub->Label}\" dikerjakan tim — hasilnya dicatat lewat tombol Catat Hasil, bukan ditarik dari HCLearn.", 422);
            }
            if (! $sub->Penjadwalan_Tahap_Id) {
                return ResponseHelper::error("\"{$sub->Label}\" belum dijadwalkan — belum ada sesi ujian yang bisa ditarik hasilnya.", 422);
            }

            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)->value('Lamaran_Id');

            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Penjadwalan_Tahap_Id', $sub->Penjadwalan_Tahap_Id)
                ->where('Lamaran_Id', $lamaranId)
                ->first();
            if (! $peserta) {
                return ResponseHelper::error('Peserta ujian untuk aktivitas ini tidak ditemukan.', 404);
            }

            // ── TARIK LEWAT API, BUKAN BACA TABEL CAT ───────────────────────
            //
            // Dulu tabel token & nilai CAT dibaca langsung. Itu hanya bekerja
            // saat CAT berjalan lokal dan sedatabase; di staging/production CAT
            // punya databasenya sendiri sehingga pembacaan itu selalu kosong dan
            // sinkron selalu gagal "sesi tidak ditemukan". Sekarang lewat
            // GET penjadwalan/{Id_Ujian_Token} yang memang disediakan CAT.
            if (! $peserta->Ref_Ujian_Token) {
                return ResponseHelper::error(
                    "\"{$sub->Label}\" dijadwalkan sebelum penautan sesi ada, jadi hasilnya tak bisa ditarik. Batalkan penjadwalannya lalu buat ulang.",
                    409
                );
            }

            $balas = app(\App\Services\WebCareers\HclClient::class)->get(
                "penjadwalan/{$peserta->Ref_Ujian_Token}",
                [],
                ['Jenis_Event' => 'SINKRON_HASIL', 'Penjadwalan_Peserta_Id' => $peserta->Id_Penjadwalan_Peserta]
            );

            if (! $balas['sukses']) {
                return ResponseHelper::error("HCLearn: {$balas['message']}", (int) ($balas['status'] ?: 422));
            }

            $sesi = $balas['result'] ?? [];
            $statusKerja = $sesi['Status_Pengerjaan'] ?? null;
            $nilai = $sesi['Hasil'] ?? [];

            // Belum ada nilai → jangan mengarang. Sampaikan apa adanya, sekaligus
            // segarkan status pengerjaan supaya admin melihat perkembangan nyata.
            if (empty($nilai['Id_Ujian_Nilai_Akhir'])) {
                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                    ->update(['Status_Pengerjaan' => $statusKerja, 'Updated_At' => now()]);

                $status = $statusKerja ?: 'belum dimulai';

                return ResponseHelper::error("Belum ada nilai di HCLearn untuk \"{$sub->Label}\" — status pengerjaan saat ini: {$status}. Coba lagi setelah kandidat menyelesaikan tesnya.", 422);
            }

            $hasil = $this->prosesHasilUjian($peserta, [
                'Status_Kelulusan' => $nilai['Status_Kelulusan'] ?? null,
                'Total_Nilai' => $nilai['Total_Nilai'] ?? null,
                'Total_Soal' => $nilai['Total_Soal'] ?? null,
                'Ambang_Batas_Nilai' => $nilai['Ambang_Batas_Nilai'] ?? null,
                'Status_Pengerjaan' => $statusKerja ?: 'selesai',
            ], 'SINKRON');

            if (! $hasil['diproses']) {
                return ResponseHelper::success($hasil, 'Hasil sudah tercatat sebelumnya — tidak ada yang berubah.');
            }

            return ResponseHelper::success(
                $hasil,
                "Hasil \"{$sub->Label}\" ditarik dari HCLearn: " . ($nilai['Status_Kelulusan'] ?? '-') . ' (nilai ' . ($nilai['Total_Nilai'] ?? '-') . ').'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal sinkron hasil sub-tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menarik hasil dari HCLearn.', 500);
        }
    }

    /**
     * PATCH /api/v1/lamaran/sub-tes/{id}/tidak-hadir — ESCAPE HATCH admin.
     * Sub-tes yang kandidatnya tidak hadir / token hangus ditandai TIDAK_HADIR
     * supaya tahap tidak menggantung menunggu hasil yang tak akan datang; mesin
     * langsung dievaluasi ulang (bisa berujung SIAP_DIPUTUS / GUGUR sesuai mode).
     *
     * Catatan OPSIONAL — sama seperti pencatatan kehadiran. Alasannya sering
     * perlu direkam ("sakit, minta jadwal ulang"), tapi mewajibkannya hanya
     * menahan tim pada kasus yang tidak butuh penjelasan apa pun.
     */
    public function subTesTidakHadir(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sub-tes tidak valid.', 422);
        }

        $data = $request->validate([
            'catatan' => 'nullable|string',
        ]);

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Sub-tes tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Sub-tes ini sudah final.', 422);
            }

            if ($kunci = self::kunciUrutan($sub)) {
                return ResponseHelper::error($kunci, 409);
            }

            // SATU TRANSAKSI: menandai tidak hadir dan menyimpulkan tahapnya
            // adalah satu tindakan. Bila evaluasinya gagal setelah aktivitas
            // ditandai final, pintu ini menolak percobaan ulang ("sudah final")
            // dan tahapnya menggantung selamanya — persis keadaan yang justru
            // hendak dilepaskan oleh tombol ini.
            $outcome = DB::transaction(function () use ($realId, $sub, $data) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'TIDAK_HADIR',
                    // PENENTU yang tak hadir dinilai GAGAL (mode Auto_Gugur akan
                    // menggugurkan); INFORMATIF cukup ditandai selesai tanpa hasil.
                    'Hasil' => $sub->Peran === 'PENENTU' ? 'GAGAL' : null,
                    'Flag_Selesai' => 'Y',
                    // Kehadiran ikut ditetapkan: layar membaca Jadwal_Hadir untuk
                    // tahu kehadiran sudah diputuskan. Tanpa ini, aktivitas
                    // berjadwal yang ditandai lewat jalur ini tetap terlihat
                    // "belum ditetapkan" dan menahan tombol keputusan.
                    'Jadwal_Hadir' => $sub->Jadwal_Mulai ? 'T' : $sub->Jadwal_Hadir,
                    'Jadwal_Hadir_At' => $sub->Jadwal_Mulai ? now() : $sub->Jadwal_Hadir_At,
                    'Jadwal_Hadir_By' => $sub->Jadwal_Mulai ? session('career_auth.nama') : $sub->Jadwal_Hadir_By,
                    'Catatan' => ($data['catatan'] ?? null) ?: $sub->Catatan,
                    'Waktu_Selesai' => now(),
                    'Updated_At' => now(),
                ]);

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return $eval['outcome'] ?? null;
            });

            Log::channel('web_career')->info("Sub-tes #{$realId} ditandai TIDAK_HADIR → evaluasi: " . ($outcome ?? '-'));

            return ResponseHelper::success(['outcome' => $outcome], 'Sub-tes ditandai tidak hadir.');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal tandai sub-tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memproses.', 500);
        }
    }

    /**
     * PATCH /api/v1/lamaran/sub-tes/{id}/catat-hasil — admin merekam hasil
     * SUB-TES MANUAL (wawancara/FGD) di tahap campuran. Hasilnya masuk ke mesin
     * keputusan yang SAMA dengan callback HCLearn, sehingga tahap "psikotes 3×
     * + wawancara" bisa menyimpulkan (auto-maju / Siap Diputus) tanpa dipaksa.
     * Sub-tes pihak ke-3 ditolak — hasilnya hanya boleh datang dari HCLearn.
     */
    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/jadwal — tetapkan jadwal wawancara
     * atau tes tatap muka, lalu undang kandidat lewat email.
     *
     * DARING wajib tautan pertemuan; LURING wajib lokasi. Keduanya wajib tanggal
     * & waktu — tanpa itu undangannya tidak berarti apa-apa. Validasinya
     * bersyarat (required_if) supaya rekruter tidak bisa mengirim undangan daring
     * tanpa tautan, yang justru membuat kandidat tidak tahu harus ke mana.
     *
     * Aktivitas ONLINE (ujian CAT) tidak lewat sini — jadwalnya sudah ditangani
     * modul Penjadwalan beserta token & OTP-nya.
     */
    public function subTesJadwal(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $data = $request->validate([
            // Daftar mode dari MASTER — bentuk jadwal baru cukup satu baris data.
            'mode' => ['required', Rule::in(self::masterModeJadwal()->keys()->all())],
            'mulai' => 'required|date',
            'selesai' => 'nullable|date|after:mulai',
            // `required_if` sengaja TIDAK dipakai lagi: ia menuntut nama mode
            // ditulis di sini, dan itu persis yang membuat mode baru harus
            // menyunting validator. Syaratnya ditegakkan setelah ini, dari flag.
            'link' => 'nullable|url|max:500',
            'kontak' => 'nullable|string|max:40',
            // LURING: pilih dari Master Lokasi (berikut petanya). `lokasi` tetap
            // ada sebagai DETAIL — "Gedung B lantai 3, temui resepsionis" — persis
            // seperti catatan alamat pada aplikasi pesan-antar: titik petanya dari
            // master, patokan rincinya diketik.
            'lokasiId' => 'nullable|string|max:64',
            'lokasi' => 'nullable|string|max:300',
            // Tempat yang BELUM terdaftar (lokasiId = "__LAINNYA__"): RS dadakan
            // untuk kandidat luar kota. Wajib-tidaknya ditegakkan dari master
            // peruntukan, bukan dari `required_if` di sini.
            'lokasiNama' => 'nullable|string|max:200',
            'lokasiAlamat' => 'nullable|string|max:500',
            'catatan' => 'nullable|string',
        ], [
            'link.required_if' => 'Tautan pertemuan wajib diisi untuk wawancara daring.',
            'lokasiId.required_if' => 'Pilih lokasi untuk kegiatan tatap muka.',
            'selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
            ->where('t.Id_Lamaran_Tahap_Tes', $realId)
            ->select('t.*', 'h.Lamaran_Id', 'h.Label as TahapLabel', 'h.Urutan as TahapUrutan')
            ->first();

        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if ($sub->Flag_Selesai === 'Y') {
            return ResponseHelper::error('Aktivitas ini sudah selesai — jadwalnya tidak bisa diubah.', 409);
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        // Sebagian aktivitas MUSTAHIL daring: MCU itu pemeriksaan fisik, tanda
        // tangan kontrak butuh kehadiran. Dijaga di SERVER juga — layar bisa
        // dilewati lewat DevTools, dan undangan daring untuk MCU akan membuat
        // kandidat datang ke tautan yang tidak akan pernah ada orangnya.
        $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;

        if ($galat = self::periksaBidangJadwal($data, $tipeSub)) {
            return ResponseHelper::error($galat, 422);
        }

        if (($tipeSub->Flag_Wajib_Luring ?? 'T') === 'Y'
            && (self::masterModeJadwal()->get($data['mode'])->Flag_Luring ?? 'T') !== 'Y') {
            return ResponseHelper::error(
                ($tipeSub->Nama ?? 'Aktivitas ini') . ' hanya bisa dijadwalkan LURING (tatap muka).',
                422,
            );
        }

        $this->terapkanJadwal((int) $realId, $data, $data['mulai'], $data['selesai'] ?? null);

        $terkirim = $this->kirimUndanganJadwal((int) $realId, (int) $sub->Lamaran_Id);

        return ResponseHelper::success(
            ['emailTerkirim' => $terkirim],
            $terkirim ? 'Jadwal disimpan dan undangan dikirim ke kandidat.' : 'Jadwal disimpan. Undangan email gagal dikirim — periksa log.',
        );
    }

    /**
     * Tulis jadwal ke satu aktivitas.
     *
     * Dipisah dari subTesJadwal() supaya penjadwalan SATUAN dan MASSAL menulis
     * kolom yang sama persis. Kalau keduanya punya salinan kodenya sendiri,
     * perbedaan sekecil apa pun — satu kolom yang lupa dikosongkan — hanya akan
     * muncul pada salah satu jalur, dan itu jenis selisih yang paling sulit
     * ditemukan karena keduanya "sama-sama bekerja".
     *
     * `$mulai`/`$selesai` dikirim terpisah dari `$data` karena penjadwalan
     * massal menghitung waktunya sendiri per kandidat (sesi bergiliran).
     */
    /**
     * Master bentuk pelaksanaan jadwal (daring / tatap muka / telepon), by Kode.
     *
     * Tiap baris menyatakan sendiri field apa yang WAJIB diisi untuknya. Dulu
     * 'DARING' dan 'LURING' tertulis mati di sekitar sepuluh tempat; bentuk
     * ketiga — telepon, yang justru paling lazim untuk penawaran gaji — menuntut
     * kesepuluhnya diubah serempak. Sekarang cukup satu baris data.
     */
    /**
     * Master status MCU (Fit / Fit with Note / Temporary Unfit / Unfit), by Kode.
     *
     * Dulu ketiganya array JavaScript di dalam Pelamar.vue. Status MCU
     * menentukan NASIB ORANG — Flag_Lolos menerjemahkannya langsung jadi
     * LULUS/GAGAL — dan aturan sebesar itu tidak boleh hanya hidup di berkas
     * layar, tempat ia tak bisa ditinjau maupun diaudit.
     */
    /**
     * Master jawaban kandidat atas penawaran (setuju / menolak / mundur).
     *
     * Dicatat TIM saat menandai kehadiran negosiasi — bukan ditekan kandidat di
     * portal; tombol itu sudah dicabut. Tiap jawaban membawa akibatnya sendiri,
     * termasuk hasil keputusan yang menutup lamaran bila memang menutup.
     */
    private static function masterJawabanPenawaran()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Jawaban_Penawaran')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    private static function masterMcuStatus()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Mcu_Status')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    private static function masterModeJadwal()
    {
        static $cache = null;

        return $cache ??= DB::table('N_WEB_CAREERS_Master_Mode_Jadwal')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get()->keyBy('Kode');
    }

    /**
     * Field wajib menurut FLAG mode terpilih — pengganti `required_if` yang
     * dulu menuntut nama mode ditulis di dalam validator.
     *
     * @return string|null pesan galat, null bila lengkap
     */
    private static function periksaBidangJadwal(array $data, ?object $tipe = null): ?string
    {
        $m = self::masterModeJadwal()->get($data['mode'] ?? '');
        if (! $m) {
            return 'Bentuk pelaksanaan tidak dikenali.';
        }
        if (($m->Flag_Butuh_Tautan ?? 'T') === 'Y' && empty($data['link'])) {
            return "Bentuk \"{$m->Nama}\" wajib menyertakan tautan pertemuan.";
        }
        if (($m->Flag_Butuh_Lokasi ?? 'T') === 'Y' && empty($data['lokasiId'])) {
            return "Bentuk \"{$m->Nama}\" wajib memilih lokasi.";
        }
        if (($m->Flag_Butuh_Lokasi ?? 'T') === 'Y'
            && ($galat = self::periksaPeruntukanLokasi($data, $tipe))) {
            return $galat;
        }
        // Nomor tidak boleh diambil diam-diam dari profil: yang dijanjikan ke
        // kandidat harus yang benar-benar tercatat, bukan yang kebetulan ada.
        if (($m->Flag_Butuh_Kontak ?? 'T') === 'Y' && empty($data['kontak'])) {
            return "Bentuk \"{$m->Nama}\" wajib mencantumkan nomor yang akan dihubungi.";
        }

        return null;
    }

    /**
     * LOKASI YANG DIPILIH HARUS COCOK DENGAN PERUNTUKAN TIPE AKTIVITASNYA.
     *
     * MCU hanya boleh ke rumah sakit. Layar memang sudah menyaring dropdown-nya,
     * tetapi layar bisa dilewati — dan akibat lolosnya bukan galat yang terlihat
     * melainkan undangan yang benar-benar terkirim: kandidat berangkat ke kantor
     * untuk pemeriksaan kesehatan, dan baru tahu di tempat bahwa tak ada yang
     * memeriksanya.
     *
     * Tipe TANPA peruntukan (phone screen, negosiasi) tidak dibatasi sama sekali
     * — membatasi sesuatu yang memang bisa di mana saja hanya membuat rekruter
     * buntu tanpa sebab.
     *
     * @return string|null pesan galat, null bila sah
     */
    /**
     * Nama kandidat dari jawaban formulir — null bila tidak ada.
     *
     * Aturannya dipusatkan di IdentitasKandidat supaya kartu worklist, drawer,
     * undangan, dan PDF biodata menyebut orang yang sama dengan nama yang sama.
     */
    private static function namaDariJawaban(?array $jawaban, ?string $snapshotJson = null): ?string
    {
        return \App\Support\Career\IdentitasKandidat::nama($jawaban ?: [], $snapshotJson);
    }

    /**
     * Nama resmi tiap lamaran, diambil dari FORMULIR — [Lamaran_Id => nama].
     *
     * Satu kueri untuk seluruh daftar, bukan satu per kartu. Pengisian dibaca
     * urut dari yang PALING AWAL: formulir lamaran diisi lebih dulu dan itulah
     * yang memuat identitas; formulir tahap lanjutan umumnya tidak menanyakan
     * nama lagi.
     */
    private static function namaResmiPerLamaran(array $lamaranIds): \Illuminate\Support\Collection
    {
        if (! $lamaranIds) {
            return collect();
        }

        // Snapshot skema ikut dibaca: ia yang memberi tahu field mana yang
        // MENANYAKAN nama, tanpa perlu kuncinya terdaftar di master lebih dulu.
        $adaSnapshot = \App\Support\Career\FormulirSchema::punyaKolomPengisianSnapshot();

        return DB::table('N_WEB_CAREERS_Formulir_Pengisian')
            ->whereIn('Lamaran_Id', $lamaranIds)
            ->orderBy('Id_Formulir_Pengisian')
            ->get(array_merge(
                ['Lamaran_Id', 'Jawaban_Json'],
                $adaSnapshot ? ['Schema_Snapshot_Json'] : [],
            ))
            ->groupBy('Lamaran_Id')
            ->map(function ($g) {
                foreach ($g as $fp) {
                    $nama = self::namaDariJawaban(
                        json_decode($fp->Jawaban_Json ?: '{}', true) ?: [],
                        $fp->Schema_Snapshot_Json ?? null,
                    );
                    if ($nama) {
                        return $nama;
                    }
                }

                return null;
            })
            ->filter();
    }

    /**
     * TEMPAT sebuah jadwal — dari master ATAU yang diketik sendiri.
     *
     * Satu pintu untuk undangan email, portal kandidat, dan rapor admin. Dulu
     * masing-masing membaca kolomnya sendiri, dan begitu tempat "Lainnya"
     * ditambahkan, ketiganya akan menampilkan hal berbeda untuk jadwal yang
     * sama — yang paling merugikan justru undangan, karena ia sudah terkirim
     * sebelum siapa pun sempat melihat selisihnya.
     *
     * @param  object  $s  baris N_WEB_CAREERS_Lamaran_Tahap_Tes
     */
    public static function tempatJadwal(object $s, ?object $master = null): ?array
    {
        if ($s->Jadwal_Lokasi_Id ?? null) {
            return MasterLokasiController::bentukLokasi(
                $master ?: DB::table('N_WEB_CAREERS_Master_Lokasi')
                    ->where('Id_Master_Lokasi', $s->Jadwal_Lokasi_Id)->first(),
                MasterLokasiController::petaPeruntukan([(int) $s->Jadwal_Lokasi_Id])
                    ->get((int) $s->Jadwal_Lokasi_Id, []),
            );
        }

        return MasterLokasiController::lokasiLepas(
            $s->Jadwal_Lokasi_Nama ?? null,
            $s->Jadwal_Lokasi_Alamat ?? null,
        );
    }

    private static function periksaPeruntukanLokasi(array $data, ?object $tipe): ?string
    {
        $butuh = $tipe->Lokasi_Peruntukan_Kode ?? null;
        $lepas = ($data['lokasiId'] ?? null) === MasterLokasiController::LAINNYA;

        // ── Tempat yang diketik sendiri ────────────────────────────────────
        if ($lepas) {
            $p = $butuh ? MasterLokasiController::masterPeruntukan()->get($butuh) : null;

            // Tanpa peruntukan, tak ada master yang menyatakan "Lainnya" boleh.
            // Menutupnya adalah pilihan yang aman: yang terlanjur dibuka tak
            // bisa ditarik, sedangkan yang tertutup cukup dibuka lewat master.
            if (! $p || ($p->Flag_Izinkan_Lainnya ?? 'T') !== 'Y') {
                return 'Tempat di luar daftar tidak diizinkan untuk aktivitas ini — pilih dari daftar lokasi.';
            }
            if (empty(trim((string) ($data['lokasiNama'] ?? '')))) {
                return ($p->Label_Nama_Lainnya ?: 'Nama tempat') . ' wajib diisi.';
            }
            if (($p->Flag_Wajib_Alamat_Lainnya ?? 'Y') === 'Y'
                && empty(trim((string) ($data['lokasiAlamat'] ?? '')))) {
                return ($p->Label_Alamat_Lainnya ?: 'Alamat') . ' wajib diisi — undangan tanpa alamat membuat kandidat tidak tahu harus datang ke mana.';
            }

            return null;
        }

        if (! $butuh) {
            return null;
        }

        $id = Hashids::decode($data['lokasiId'] ?? '')[0] ?? null;
        if (! $id) {
            return 'Lokasi tidak valid.';
        }

        $cocok = DB::table('N_WEB_CAREERS_Master_Lokasi_Peruntukan_Map')
            ->where('Id_Master_Lokasi', $id)
            ->where('Peruntukan_Kode', $butuh)
            ->exists();

        if (! $cocok) {
            $nama = DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $id)->value('Nama') ?: 'Lokasi itu';
            $p = MasterLokasiController::masterPeruntukan()->get($butuh);

            return "\"{$nama}\" bukan " . mb_strtolower($p->Nama ?? $butuh)
                . '. ' . ($p->Label_Pilih ?? 'Pilih lokasi yang sesuai') . '.';
        }

        return null;
    }

    private function terapkanJadwal(int $subTesId, array $data, string $mulai, ?string $selesai): void
    {
        $mode = self::masterModeJadwal()->get($data['mode']);
        $now = now();
        $nama = session('career_auth.nama');
        $pakaiLokasi = ($mode->Flag_Butuh_Lokasi ?? 'T') === 'Y';
        // Tempat di luar master — sudah divalidasi di periksaPeruntukanLokasi().
        $lepas = $pakaiLokasi && ($data['lokasiId'] ?? null) === MasterLokasiController::LAINNYA;

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $subTesId)
            ->update([
                'Jadwal_Mode' => $data['mode'],
                'Jadwal_Mulai' => $mulai,
                'Jadwal_Selesai' => $selesai,
                // Kolom yang tidak dipakai mode terpilih DIKOSONGKAN, bukan
                // dibiarkan berisi nilai lama — sisa tautan pada jadwal luring
                // membuat kandidat mengira wawancaranya tetap daring.
                // Kolom mana yang terisi ditentukan FLAG MODE-nya, bukan
                // perbandingan dengan nama mode. Bentuk jadwal baru yang
                // ditambahkan lewat master langsung ikut aturan ini tanpa satu
                // baris pun disentuh di sini.
                'Jadwal_Link' => ($mode->Flag_Butuh_Tautan ?? 'T') === 'Y' ? ($data['link'] ?? null) : null,
                'Jadwal_Lokasi' => $pakaiLokasi ? ($data['lokasi'] ?? null) : null,
                'Jadwal_Lokasi_Id' => ($pakaiLokasi && ! $lepas && ! empty($data['lokasiId']))
                    ? (Hashids::decode($data['lokasiId'])[0] ?? null)
                    : null,
                // TEMPAT YANG DIKETIK SENDIRI — kolomnya terpisah dari patokan.
                // Keduanya dikosongkan saat lokasi terdaftar yang dipilih,
                // supaya sisa isian percobaan sebelumnya tidak ikut terbaca
                // sebagai tempat kedua di undangan yang sama.
                'Jadwal_Lokasi_Nama' => $lepas ? trim((string) ($data['lokasiNama'] ?? '')) : null,
                'Jadwal_Lokasi_Alamat' => $lepas ? (trim((string) ($data['lokasiAlamat'] ?? '')) ?: null) : null,
                // Nomor yang akan dihubungi — bagian dari JANJINYA, bukan
                // salinan profil. Lihat penjelasan panjang di .sql-nya.
                'Jadwal_Kontak' => ($mode->Flag_Butuh_Kontak ?? 'T') === 'Y' ? ($data['kontak'] ?? null) : null,
                'Jadwal_Catatan' => $data['catatan'] ?? null,
                'Jadwal_At' => $now,
                'Jadwal_By' => $nama,
                'Jadwal_By_Id' => session('career_auth.id'),
                'Status' => 'DIJADWALKAN',
                'Updated_At' => $now,
                'Updated_By' => $nama,
            ]);
    }

    /**
     * POST /api/v1/karir/lamaran/sub-tes/jadwal-massal — jadwalkan BANYAK
     * kandidat sekaligus.
     *
     * KENAPA INI WAJIB ADA
     * Menjadwalkan wawancara satu per satu masih masuk akal untuk lima orang.
     * Untuk seratus, ia bukan sekadar lambat — ia berbahaya: seratus jendela
     * yang dibuka berturut-turut adalah seratus kesempatan salah ketik tanggal,
     * salah pilih lokasi, atau terlewat satu orang tanpa ada yang menyadarinya.
     * Kesalahan itu baru ketahuan saat kandidat datang di hari yang salah.
     *
     * DUA POLA WAKTU, karena keduanya nyata di lapangan:
     *   SERENTAK   semua di jam yang sama — FGD, tes tertulis massal, briefing.
     *   BERGILIR   satu per satu dengan durasi & jeda tetap — wawancara panel.
     *              Kandidat ke-N mulai di `mulai + N × (durasi + jeda)`.
     *
     * TIDAK ADA "SEBAGIAN GAGAL DIAM-DIAM". Tiap kandidat dilaporkan sendiri:
     * yang berhasil dan yang ditolak berikut alasannya. Penjadwalan massal yang
     * hanya menjawab "berhasil" untuk 97 dari 100 orang adalah cara terbaik
     * kehilangan tiga orang tanpa jejak.
     */
    public function jadwalMassal(Request $request)
    {
        $data = $request->validate([
            'subTesIds' => 'required|array|min:1|max:300',
            'subTesIds.*' => 'required|string|max:64',
            // Daftar mode dari MASTER — bentuk jadwal baru cukup satu baris data.
            'mode' => ['required', Rule::in(self::masterModeJadwal()->keys()->all())],
            'pola' => 'required|in:SERENTAK,BERGILIR',
            'mulai' => 'required|date',
            // Hanya dipakai pola BERGILIR. Batas atas menjaga dari salah ketik
            // yang melempar sesi terakhir ke tahun depan.
            'durasiMenit' => 'nullable|integer|min:5|max:480',
            'jedaMenit' => 'nullable|integer|min:0|max:240',
            // `required_if` sengaja TIDAK dipakai lagi: ia menuntut nama mode
            // ditulis di sini, dan itu persis yang membuat mode baru harus
            // menyunting validator. Syaratnya ditegakkan setelah ini, dari flag.
            'link' => 'nullable|url|max:500',
            'kontak' => 'nullable|string|max:40',
            'lokasiId' => 'nullable|string|max:64',
            'lokasi' => 'nullable|string|max:300',
            'lokasiNama' => 'nullable|string|max:200',
            'lokasiAlamat' => 'nullable|string|max:500',
            'catatan' => 'nullable|string',
        ], [
            'link.required_if' => 'Tautan pertemuan wajib diisi untuk kegiatan daring.',
            'lokasiId.required_if' => 'Pilih lokasi untuk kegiatan tatap muka.',
        ]);

        $bergilir = $data['pola'] === 'BERGILIR';
        $durasi = (int) ($data['durasiMenit'] ?? 30);
        $jeda = (int) ($data['jedaMenit'] ?? 0);
        $mulaiAwal = \Illuminate\Support\Carbon::parse($data['mulai']);

        $tipeSemua = self::masterTipeTahap();
        $berhasil = [];
        $gagal = [];
        $urutanSesi = 0;

        foreach ($data['subTesIds'] as $hash) {
            $id = Hashids::decode($hash)[0] ?? null;

            $sub = $id ? DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->where('t.Id_Lamaran_Tahap_Tes', $id)
                ->select('t.*', 'h.Lamaran_Id', 'l.Status as StatusLamaran', 'u.Nama as Pelamar')
                ->first() : null;

            $nama = $sub->Pelamar ?? ('#' . $hash);

            // ── Gerbang per kandidat ────────────────────────────────────────
            // Sengaja diperiksa ulang di server untuk SETIAP baris, bukan
            // sekali di awal: daftar yang dikirim layar bisa sudah basi —
            // rekan sebelah mungkin baru saja menggugurkan salah satunya.
            $tolak = match (true) {
                ! $sub => 'Aktivitas tidak ditemukan.',
                $sub->StatusLamaran !== 'BERJALAN' => 'Lamaran sudah tidak berjalan.',
                $sub->Flag_Selesai === 'Y' => 'Aktivitas sudah selesai.',
                default => self::kunciUrutan($sub),
            };

            if ($tolak) {
                $gagal[] = ['nama' => $nama, 'alasan' => $tolak];
                continue;
            }

            // MCU & tanda tangan kontrak mustahil daring — dijaga sama seperti
            // pada penjadwalan satuan.
            $tipeSub = $tipeSemua[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            // Peruntukan lokasi ikut diperiksa — kalau tidak, penjadwalan MASSAL
            // jadi pintu belakang yang mengirim seratus orang MCU ke kantor
            // sekaligus, persis kesalahan yang dijaga di jalur satuan.
            if ($galat = self::periksaBidangJadwal($data, $tipeSub)) {
                return ResponseHelper::error($galat, 422);
            }

            if (($tipeSub->Flag_Wajib_Luring ?? 'T') === 'Y'
                && (self::masterModeJadwal()->get($data['mode'])->Flag_Luring ?? 'T') !== 'Y') {
                $gagal[] = ['nama' => $nama, 'alasan' => ($tipeSub->Nama ?? 'Aktivitas ini') . ' hanya bisa LURING.'];
                continue;
            }

            // Nomor sesi dihitung dari yang BENAR-BENAR dijadwalkan, bukan dari
            // posisi di daftar kiriman. Kalau dari posisi, satu kandidat yang
            // ditolak akan meninggalkan lubang jam kosong di tengah rangkaian.
            $mulai = $bergilir
                ? $mulaiAwal->copy()->addMinutes($urutanSesi * ($durasi + $jeda))
                : $mulaiAwal->copy();
            $selesai = $bergilir ? $mulai->copy()->addMinutes($durasi) : null;

            $this->terapkanJadwal(
                (int) $sub->Id_Lamaran_Tahap_Tes,
                $data,
                $mulai->format('Y-m-d H:i:s'),
                $selesai?->format('Y-m-d H:i:s'),
            );

            $terkirim = $this->kirimUndanganJadwal((int) $sub->Id_Lamaran_Tahap_Tes, (int) $sub->Lamaran_Id);

            $berhasil[] = [
                'nama' => $nama,
                'mulai' => $mulai->format('Y-m-d H:i'),
                'emailTerkirim' => $terkirim,
            ];
            $urutanSesi++;
        }

        Log::channel('web_career')->info(
            '[JADWAL-MASSAL] ' . count($berhasil) . ' berhasil, ' . count($gagal) . ' gagal — oleh ' . session('career_auth.nama', 'ADMIN')
        );

        return ResponseHelper::success(
            ['berhasil' => $berhasil, 'gagal' => $gagal],
            count($gagal)
                ? count($berhasil) . ' kandidat dijadwalkan, ' . count($gagal) . ' dilewati — periksa rinciannya.'
                : count($berhasil) . ' kandidat dijadwalkan dan diundang lewat email.',
        );
    }

    /**
     * Kirim undangan jadwal ke kandidat.
     *
     * Kegagalan email TIDAK membatalkan jadwalnya: jadwal sudah tersimpan dan
     * terlihat di portal kandidat, jadi menggagalkan seluruh operasi hanya
     * karena SMTP sedang bermasalah justru merugikan.
     */
    private function kirimUndanganJadwal(int $subTesId, int $lamaranId): bool
    {
        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as t')
                ->join('N_WEB_CAREERS_Lamaran_Tahap as h', 'h.Id_Lamaran_Tahap', '=', 't.Lamaran_Tahap_Id')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->where('t.Id_Lamaran_Tahap_Tes', $subTesId)
                ->select('t.*', 'h.Label as TahapLabel', 'l.Kode', 'u.Id_Users', 'u.Nama', 'u.Email',
                    'p.Nama as ProgramNama', 'x.Posisi')
                ->first();

            if (! $sub || ! $sub->Email) {
                return false;
            }

            // TEMPATNYA ikut, bukan hanya patokan yang diketik rekruter.
            //
            // Undangan sebelumnya cuma membawa Jadwal_Lokasi — teks bebas
            // semacam "Pabrik di Banyuasin". Nama tempat, alamat, dan petanya —
            // justru yang dipilih rekruter di layar penjadwalan — tidak pernah
            // sampai ke kandidat; bila patokannya dikosongkan, undangannya
            // bahkan tidak menyebut lokasi sama sekali. Sekarang keduanya
            // dikirim: TEMPAT sebagai alamat resmi, PATOKAN sebagai penunjuk
            // rinci di dalamnya.
            $tempat = self::tempatJadwal($sub);

            WcJadwalEmailJob::dispatch((int) $sub->Id_Users, [
                'nama' => $sub->Nama,
                'email' => $sub->Email,
                'kode' => $sub->Kode,
                'posisi' => $sub->Posisi ?: $sub->ProgramNama,
                'program' => $sub->ProgramNama,
                'tahap' => $sub->TahapLabel,
                'aktivitas' => $sub->Label,
                'mode' => $sub->Jadwal_Mode,
                'mulai' => (string) $sub->Jadwal_Mulai,
                'selesai' => (string) ($sub->Jadwal_Selesai ?: ''),
                'link' => $sub->Jadwal_Link,
                // Nama tempat yang dipilih; patokan tetap dikirim terpisah.
                'lokasi' => $tempat['nama'] ?? $sub->Jadwal_Lokasi,
                'alamat' => $tempat['alamatLengkap'] ?? null,
                'patokan' => $tempat ? $sub->Jadwal_Lokasi : null,
                'kontak' => $tempat['kontakTelp'] ?? null,
                'mapsUrl' => $tempat['mapsUrl'] ?? null,
                'catatan' => $sub->Jadwal_Catatan,
            ]);

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                ->where('Id_Lamaran_Tahap_Tes', $subTesId)
                ->update(['Jadwal_Email_At' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('[JADWAL] undangan gagal diantrekan: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * PATCH /api/v1/karir/lamaran/sub-tes/{id}/kehadiran — catat hadir/tidak.
     *
     * Untuk aktivitas berjadwal (MCU, wawancara), kehadiran adalah GERBANG
     * sebelum hasil bisa dicatat: tim tidak bisa melampirkan hasil MCU untuk
     * orang yang tidak datang, dan tidak masuk akal meloloskannya.
     *
     * TIDAK HADIR langsung menggugurkan aktivitas lalu tahapnya dievaluasi
     * ulang — kandidat yang tidak datang tanpa kabar memang berhenti di situ.
     */
    public function subTesKehadiran(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Aktivitas tidak valid.', 422);
        }

        $data = $request->validate([
            'hadir' => 'required|in:Y,T',
            // HASIL IKUT DI SINI — satu tindakan, bukan dua.
            //
            // Dulu "Hadir" dan "Catat Hasil" adalah dua tombol dengan dua
            // jendela, padahal keduanya menjelaskan satu peristiwa yang sama:
            // kandidat datang, dan inilah hasilnya. Memisahkannya membuat
            // penilai mengisi catatan di jendela pertama lalu diminta mengisi
            // lagi di jendela kedua — dan yang paling sering terjadi, jendela
            // kedua tidak pernah dibuka sehingga hasilnya tak pernah tercatat.
            'hasil' => 'nullable|in:LULUS,GAGAL',
            'nilai' => 'nullable|numeric|min:0|max:1000',
            // Hasil mode KATEGORI (mis. "Dominance", "Sangat Baik").
            'nilaiTeks' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
            // Catatan penilaian yang sesungguhnya — HTML dari editor berformat.
            // Batasnya jauh lebih longgar dari `catatan`: hasil wawancara ditulis
            // per kompetensi dan kerap memuat kutipan jawaban kandidat, dan
            // memotongnya di 500 karakter berarti penilai menyingkat sampai
            // catatannya tak lagi bisa dipakai orang lain.
            'catatanHtml' => 'nullable|string',
            // ── HASIL MCU IKUT DI LANGKAH INI ────────────────────────────────
            // Kehadiran dan hasil pemeriksaan adalah SATU peristiwa: kandidat
            // datang ke klinik, diperiksa, dan inilah hasilnya. Dulu statusnya
            // diisi di jendela KEPUTUSAN — sehingga petugas yang menerima hasil
            // dari klinik tidak punya tempat mencatatnya, dan orang yang
            // menekan "Loloskan" disodori formulir medis yang bukan urusannya.
            //
            // Daftar statusnya dari MASTER, bukan `in:` yang ditulis di sini.
            'mcuStatus' => ['nullable', Rule::in(self::masterMcuStatus()->keys()->all())],
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string',
            'mcuTanggal' => 'nullable|date',
            // JAWABAN KANDIDAT ATAS PENAWARAN — hanya untuk aktivitas
            // berpenawaran. Daftarnya dari master, bukan `in:` di sini.
            'jawabanPenawaran' => ['nullable', Rule::in(self::masterJawabanPenawaran()->keys()->all())],
        ]);

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (! $sub->Jadwal_Mulai) {
            return ResponseHelper::error('Aktivitas ini belum dijadwalkan.', 409);
        }

        // ── SYARAT KHUSUS MCU ────────────────────────────────────────────────
        // Hanya berlaku bila kandidat HADIR: orang yang tidak datang tidak
        // punya hasil pemeriksaan, dan menuntutnya berarti tak ada cara menutup
        // MCU yang batal.
        // ── SYARAT KHUSUS AKTIVITAS BERPENAWARAN ─────────────────────────────
        // Kehadiran pada negosiasi tanpa menyebut jawabannya adalah catatan yang
        // tidak menjawab apa pun: seluruh proses berakhir pada satu pertanyaan —
        // kandidat mengambil penawaran ini atau tidak.
        $tipeSubAwal = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
        $isPenawaranSub = ($tipeSubAwal->Flag_Penawaran ?? 'T') === 'Y';
        $defJawab = $isPenawaranSub ? self::masterJawabanPenawaran()->get($data['jawabanPenawaran'] ?? '') : null;

        if ($isPenawaranSub && $data['hadir'] === 'Y') {
            if (! $defJawab) {
                return ResponseHelper::error('Jawaban kandidat atas penawaran wajib dipilih.', 422);
            }
            // Jawaban yang MENUTUP lamaran menuntut alasan — itulah satu-satunya
            // umpan balik kenapa penawaran ini tidak jadi, dan tanpanya rekap
            // "kenapa kandidat lepas" tak pernah bisa dipercaya.
            if (($defJawab->Flag_Butuh_Alasan ?? 'T') === 'Y'
                && mb_strlen(trim(strip_tags((string) ($data['catatanHtml'] ?? $data['catatan'] ?? '')))) < 10) {
                return ResponseHelper::error(
                    "\"{$defJawab->Label_Panjang}\" wajib disertai alasan, minimal 10 karakter.",
                    422,
                );
            }
        }

        $isMcuSub = ($sub->Tipe_Tahap_Kode ?? '') === 'MCU';
        $defMcu = $isMcuSub ? self::masterMcuStatus()->get($data['mcuStatus'] ?? '') : null;

        if ($isMcuSub && $data['hadir'] === 'Y') {
            if (! $defMcu) {
                return ResponseHelper::error('Status hasil MCU wajib dipilih.', 422);
            }
            // Pembatasan kerja tanpa penjelasan tidak bisa ditindaklanjuti siapa
            // pun, dan "belum layak sementara" tanpa keterangan kapan diperiksa
            // ulang sama saja menggantung orang tanpa batas waktu.
            if (($defMcu->Flag_Butuh_Catatan ?? 'T') === 'Y' && trim((string) ($data['mcuCatatan'] ?? '')) === '') {
                return ResponseHelper::error(
                    "\"{$defMcu->Label_Panjang}\" wajib disertai keterangan — tuliskan temuan / pembatasannya.",
                    422,
                );
            }
        }

        if ($kunci = self::kunciUrutan($sub)) {
            return ResponseHelper::error($kunci, 409);
        }

        $now = now();
        $nama = session('career_auth.nama');

        [$html, $ringkas] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

        // ── SATU PERISTIWA, SATU TRANSAKSI ───────────────────────────────────
        //
        // Sampai di sini pencatatan kehadiran bisa menyentuh EMPAT hal:
        // kehadiran + rincian MCU pada aktivitasnya, tanggapan kandidat pada
        // tahapnya, penutupan aktivitasnya, dan — bila jawabannya menolak atau
        // mundur — penutupan seluruh lamaran lewat ketukPalu(). Dulu keempatnya
        // berdiri sendiri-sendiri.
        //
        // Yang paling merugikan bukan kegagalan teknis, melainkan penolakan
        // yang WAJAR: kandidat sedang DITAHAN, atau tahapnya sudah diputus
        // rekan sebelah. ketukPalu() menolak, tetapi tiga tulisan sebelumnya
        // sudah telanjur masuk — aktivitasnya tertutup GAGAL dan tahapnya
        // menyandang "kandidat mengundurkan diri", sementara lamarannya masih
        // BERJALAN. Jawabannya balasannya pun 200: "jawaban dicatat, tetapi
        // lamaran gagal ditutup". Tidak ada satu pun cara memperbaiki keadaan
        // itu lewat layar, karena aktivitasnya sudah final.
        //
        // Sekarang seluruhnya jadi atau seluruhnya batal, dan penolakan wajar
        // dibalas 422 dengan alasannya — keadaan basis data persis seperti
        // sebelum tombol ditekan, jadi tim bisa melepas hold lalu mengulang.
        try {
            $aksi = DB::transaction(function () use ($realId, $sub, $data, $now, $nama, $html, $ringkas, $isMcuSub, $defMcu, $defJawab) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Jadwal_Hadir' => $data['hadir'],
                    'Jadwal_Hadir_At' => $now,
                    'Jadwal_Hadir_By' => $nama,
                    'Catatan' => $ringkas ?: $sub->Catatan,
                    // Catatan lama TIDAK ditimpa dengan kosong: menandai kehadiran
                    // sering dilakukan dua kali (salah klik, lalu dibetulkan), dan
                    // pembetulan kedua yang catatannya kosong akan menghapus penilaian
                    // yang sudah ditulis di percobaan pertama.
                    'Catatan_Html' => $html ?: $sub->Catatan_Html,
                    'Updated_At' => $now,
                    'Updated_By' => $nama,
                ] + ($isMcuSub ? [
                    'Mcu_Status' => $data['mcuStatus'] ?? $sub->Mcu_Status,
                    'Mcu_Penyedia' => $data['mcuPenyedia'] ?? $sub->Mcu_Penyedia,
                    'Mcu_Tanggal' => $data['mcuTanggal'] ?? $sub->Mcu_Tanggal,
                    'Mcu_Catatan' => $data['mcuCatatan'] ?? $sub->Mcu_Catatan,
                ] : []));

                // TIDAK HADIR = aktivitas selesai dengan hasil GAGAL; mesin keputusan
                // yang menentukan nasib tahapnya (bisa gugur, bisa menunggu aktivitas
                // lain di tahap yang sama).
                if ($data['hadir'] === 'T') {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                        'Status' => 'TIDAK_HADIR',
                        'Hasil' => $sub->Peran === 'INFORMATIF' ? null : 'GAGAL',
                        'Flag_Selesai' => 'Y',
                        'Waktu_Selesai' => $now,
                    ]);

                    $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                    return ['mode' => 'TIDAK_HADIR', 'outcome' => $eval['outcome'] ?? null];
                }

                // ── HADIR + HASIL SEKALIGUS ─────────────────────────────────
                // Bila hasilnya ikut dikirim, aktivitas langsung DITUTUP di permintaan
                // yang sama. Satu peristiwa ("kandidat datang, hasilnya begini") =
                // satu tindakan; memisahkannya jadi dua jendela membuat yang kedua
                // kerap tak pernah dibuka, dan hasilnya tak pernah tercatat.
                //
                // Ujian online dikecualikan: nilainya datang sendiri dari HCLearn, dan
                // mengetiknya di sini berarti menimpa angka resmi dengan tebakan.
                $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
                $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';
                // VERDICT MCU DITURUNKAN DARI STATUSNYA, tidak ditanyakan dua kali.
                // Flag_Lolos di master sudah menyatakan apakah status itu berarti
                // memenuhi syarat; meminta penilai memilih LULUS/GAGAL lagi hanya
                // membuka peluang keduanya berselisih — "Unfit" tapi ditandai lulus.
                if ($defMcu) {
                    $data['hasil'] = ($defMcu->Flag_Lolos ?? 'T') === 'Y' ? 'LULUS' : 'GAGAL';
                }

                // Jawaban penawaran menentukan verdict aktivitasnya, dan DICATAT sebagai
                // tanggapan kandidat pada tahapnya. Kolom Tanggapan_* itu dulu ditulis
                // dari portal; sejak tombolnya dicabut, TIM yang menuliskannya — dan
                // worklist tetap membaca kolom yang sama, jadi tampilannya tak berubah.
                if ($defJawab) {
                    $data['hasil'] = ($defJawab->Flag_Lolos ?? 'T') === 'Y' ? 'LULUS' : 'GAGAL';

                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
                        ->update([
                            'Tanggapan_Kandidat' => $defJawab->Kode === 'SETUJU' ? 'TERIMA' : 'MUNDUR',
                            'Tanggapan_Catatan' => $ringkas ?: $defJawab->Nama,
                            'Tanggapan_At' => $now,
                            'Updated_At' => $now,
                            'Updated_By' => $nama,
                        ]);
                }

                $bolehTutup = ! $online && ($data['hasil'] !== null || $sub->Peran === 'INFORMATIF');

                if (! $bolehTutup) {
                    return ['mode' => 'HADIR_SAJA'];
                }

                // NILAI DISIMPAN SESUAI MODENYA.
                //
                // Mode KATEGORI menolak nilai di luar daftar pilihannya: predikat asing
                // yang lolos masuk membuat rekap "berapa yang Dominance" tak pernah bisa
                // dipercaya, dan itu satu-satunya alasan kategori dipakai.
                $nilaiAngka = $sub->Nilai;
                $nilaiTeks = $sub->Nilai_Teks;
                $penilaian = self::bentukPenilaian($sub);

                if (($penilaian['tipe'] ?? 'NONE') === 'ANGKA') {
                    $nilaiAngka = $data['nilai'] ?? $sub->Nilai;
                } elseif (($penilaian['tipe'] ?? 'NONE') === 'TEKS' && ! empty($data['nilaiTeks'])) {
                    if ($penilaian['opsi'] && ! in_array($data['nilaiTeks'], $penilaian['opsi'], true)) {
                        // Ditolak SETELAH kehadiran sempat ditulis — maka harus
                        // berupa lemparan, bukan nilai balik: hanya lemparan yang
                        // menggulung balik tulisan itu. Dulu ia `return`, dan
                        // predikat salah ketik meninggalkan aktivitas yang
                        // kehadirannya tercatat tapi tak pernah ditutup.
                        throw new \DomainException(
                            'Pilihan hasil tidak dikenali untuk aktivitas ini: ' . implode(' / ', $penilaian['opsi']) . '.'
                        );
                    }
                    $nilaiTeks = $data['nilaiTeks'];
                }

                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'SELESAI',
                    // Aktivitas INFORMATIF tidak diberi verdict — ia memang bukan penentu.
                    'Hasil' => $sub->Peran === 'INFORMATIF' ? null : $data['hasil'],
                    'Nilai' => $nilaiAngka,
                    'Nilai_Teks' => $nilaiTeks,
                    'Flag_Selesai' => 'Y',
                    'Waktu_Selesai' => $now,
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

                // ── JAWABAN YANG MENUTUP LAMARAN ────────────────────────────
                //
                // "Menolak penawaran" dan "mengundurkan diri" tidak menunggu palu siapa
                // pun: kandidatnya sudah pergi. Ditutup DI SINI, lewat ketukPalu() yang
                // sama persis dengan tombol tahap — supaya seluruh akibat yang
                // menyertainya (email, kuota, pilihan Talent Pool, jejak keputusan)
                // berjalan identik. Jalur kedua yang menulis sendiri ke tabel akan
                // diam-diam berbeda perilakunya, dan selisih itu baru ketahuan
                // berbulan-bulan kemudian lewat laporan yang tidak cocok.
                if ($defJawab && $defJawab->Hasil_Keputusan_Kode) {
                    $hasilTutup = $this->svc->ketukPalu(
                        (int) $sub->Lamaran_Tahap_Id,
                        $defJawab->Hasil_Keputusan_Kode,
                        $ringkas ?: $defJawab->Nama,
                        (int) session('career_auth.id'),
                        null,
                    );

                    if (! $hasilTutup['ok']) {
                        throw new \DomainException($hasilTutup['pesan']);
                    }

                    return ['mode' => 'PENAWARAN_TUTUP', 'outcome' => $defJawab->Hasil_Keputusan_Kode];
                }

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return ['mode' => 'SELESAI', 'outcome' => $eval['outcome'] ?? null, 'hasil' => $data['hasil'] ?? null];
            });
        } catch (\DomainException $e) {
            // Penolakan aturan bisnis — transaksinya sudah digulung balik, jadi
            // kehadiran pun tidak jadi tercatat. Itu memang yang diinginkan:
            // separuh peristiwa lebih menyesatkan daripada tidak ada sama sekali.
            return ResponseHelper::error($e->getMessage(), 422);
        }

        // ── SESUDAH COMMIT ───────────────────────────────────────────────────
        // Email BARU diantrekan di sini, di luar transaksi. Antrean proyek ini
        // berjalan dengan `after_commit` mati, sehingga job yang didaftarkan di
        // dalam transaksi bisa dijemput worker sebelum transaksinya selesai —
        // kandidat menerima surat "Selamat, Anda lolos" atas keputusan yang
        // sedetik kemudian digulung balik, dan surat itu tak bisa ditarik lagi.
        if ($aksi['mode'] === 'TIDAK_HADIR') {
            return ResponseHelper::success(['outcome' => $aksi['outcome']], 'Ditandai TIDAK HADIR — tahap dievaluasi ulang.');
        }

        if ($aksi['mode'] === 'HADIR_SAJA') {
            return ResponseHelper::success(null, 'Kehadiran dicatat.');
        }

        if ($aksi['mode'] === 'PENAWARAN_TUTUP') {
            Log::channel('web_career')->info(
                "Penawaran dijawab {$defJawab->Kode} pada sub-tes #{$realId} → lamaran ditutup {$aksi['outcome']}."
            );

            return ResponseHelper::success(
                ['outcome' => $aksi['outcome']],
                "Jawaban dicatat: {$defJawab->Label_Panjang}. Lamaran ditutup.",
            );
        }

        $outcome = $aksi['outcome'];

        // Tahap yang menyimpulkan sendiri (mode otomatis) tetap mengabari
        // kandidat — sama seperti jalur "Catat Hasil" sebelumnya.
        if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
            $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)
                ->value('Lamaran_Id');
            if ($lamaranId) {
                $this->kirimEmailHasilTahap($lamaranId, $outcome === 'LANJUT');
            }
        }

        Log::channel('web_career')->info(
            "Aktivitas #{$realId} ditandai HADIR + hasil " . ($aksi['hasil'] ?? 'INFORMATIF') . ' → evaluasi: ' . ($outcome ?? '-')
        );

        return ResponseHelper::success(['outcome' => $outcome], 'Kehadiran & hasil tersimpan.');
    }

    public function subTesCatatHasil(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sub-tes tidak valid.', 422);
        }

        $data = $request->validate([
            'hasil' => 'nullable|in:LULUS,GAGAL',
            'nilai' => 'nullable|numeric|min:0|max:1000',
            'catatan' => 'nullable|string',
            // Isi editor berformat — tanpa batas panjang, lihat alasannya di putus().
            'catatanHtml' => 'nullable|string',
            // Field khusus MCU. Menumpang endpoint ini, BUKAN endpoint sendiri:
            // hasil MCU tetap melewati mesin keputusan yang sama seperti hasil
            // aktivitas lain, hanya membawa rincian medis tambahan.
            //
            // Daftarnya DARI MASTER. Dulu ketiganya ditulis mati di sini, dan
            // saat status keempat — TEMPORARY_UNFIT — ditambahkan lewat master,
            // baris ini tidak ikut berubah: pintu ini menolaknya "tidak sah"
            // sementara pintu sebelah (subTesKehadiran) menerimanya. Satu daftar
            // yang hidup di dua tempat pasti berselisih, dan yang kalah selalu
            // yang lupa disunting.
            'mcuStatus' => ['nullable', Rule::in(self::masterMcuStatus()->keys()->all())],
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string',
            'mcuTanggal' => 'nullable|date',
        ]);

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Sub-tes tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Sub-tes ini sudah final.', 422);
            }

            if ($kunci = self::kunciUrutan($sub)) {
                return ResponseHelper::error($kunci, 409);
            }

            // UJIAN ONLINE TIDAK DICATAT MANUAL.
            //
            // Nilainya dihitung dan dilaporkan HCLearn; mengetiknya sendiri di
            // sini berarti menimpa angka resmi dengan tebakan, dan admin tak
            // punya sumber angka itu. Kalau kandidat memang tak mengerjakan,
            // yang benar adalah menandainya TIDAK HADIR — itu fakta yang cuma
            // diketahui tim, dan tetap melepas tahap dari status menunggu.
            //
            // Aktivitas yang dikerjakan tim (wawancara, tes offline, FGD) justru
            // sebaliknya: tak ada sistem lain yang mengirim hasilnya.
            $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;
            $online = ($tipeSub->Perilaku_Kode ?? null) === 'CAT' || ($sub->Provider ?? '') === 'THIRD_PARTY';

            // UJIAN ONLINE BER-PERAN INFORMATIF JUSTRU MENUNGGU ADMIN.
            //
            // Alat tes seperti PAPI Kostick, DISC, dan Kraeplin tidak berbunyi
            // lulus/gagal — keluarannya profil, bukan angka kelulusan. Yang
            // menyatakan layak-tidaknya memang penilai, bukan mesinnya.
            //
            // Yang tetap ditolak hanya ujian PENENTU: nilainya objektif dan
            // sudah punya ambang batas, jadi mengetiknya sendiri di sini berarti
            // menimpa angka resmi dengan tebakan.
            //
            // STATUS-nya ikut disyaratkan. Tanpa itu, ujian yang baru
            // DIJADWALKAN — kandidatnya belum menyentuh soal — sudah bisa
            // dinyatakan lulus lewat pintu ini. Layar memang tidak menawarkan
            // tombolnya, tapi pintu belakang tidak boleh lebih longgar daripada
            // layar depan. Bila hasilnya tak kunjung datang, yang benar adalah
            // "Tidak hadir".
            $adminYangMemutuskan = $online
                && $sub->Peran === 'INFORMATIF'
                && ($sub->Status ?? '') === 'MENUNGGU_KEPUTUSAN';

            if ($online && ! $adminYangMemutuskan) {
                // Dua sebab, dua kalimat. Menyamakannya membuat admin yang
                // sekadar terlalu cepat mengira alat tesnya salah dipasang.
                return ResponseHelper::error(
                    $sub->Peran === 'INFORMATIF'
                        ? "Hasil \"{$sub->Label}\" belum masuk dari HCLearn — keputusannya baru bisa diberikan setelah kandidat mengerjakannya. "
                            . 'Bila ia memang tidak mengerjakan, tandai "Tidak hadir".'
                        : "\"{$sub->Label}\" adalah ujian online penentu — hasilnya masuk sendiri dari HCLearn dan tidak dicatat manual. "
                            . 'Bila kandidat tidak mengerjakannya, tandai "Tidak hadir".',
                    422
                );
            }

            // Nilainya milik HCLearn; admin hanya menyatakan lulus/tidaknya.
            if ($adminYangMemutuskan && empty($data['hasil'])) {
                return ResponseHelper::error(
                    "Pilih Lulus atau Tidak Lulus untuk \"{$sub->Label}\" — nilainya sudah masuk, keputusannya yang ditunggu.",
                    422
                );
            }

            // Sub-tes PENENTU wajib membawa verdict; INFORMATIF cukup selesai + nilai.
            if ($sub->Peran === 'PENENTU' && empty($data['hasil'])) {
                return ResponseHelper::error('Pilih hasil (Lulus / Gagal) untuk aktivitas penentu.', 422);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            [$html, $catatan] = self::catatanKaya($data['catatanHtml'] ?? null, $data['catatan'] ?? null);

            // RINCIAN MCU HANYA DISENTUH BILA AKTIVITASNYA MEMANG MCU.
            //
            // Dulu keempat kolomnya ditulis tanpa syarat dengan `?? null`, dan
            // itu bukan "membiarkan kosong" melainkan MENGHAPUS: setiap kali
            // hasil dicatat lewat pintu ini, status kesehatan yang sudah dicatat
            // saat kehadiran ikut ternol. Layar tidak lagi mengirim field ini —
            // MCU pindah ke jendela Hadir — sehingga penghapusannya berlangsung
            // tanpa satu pun tanda di layar maupun di log.
            //
            // Nilai lamanya dipertahankan bila field-nya tidak dikirim; hanya
            // yang benar-benar disertakan yang menimpa.
            $mcu = ($sub->Tipe_Tahap_Kode ?? '') === 'MCU' ? [
                'Mcu_Status' => $data['mcuStatus'] ?? $sub->Mcu_Status,
                'Mcu_Penyedia' => $data['mcuPenyedia'] ?? $sub->Mcu_Penyedia,
                'Mcu_Catatan' => $data['mcuCatatan'] ?? $sub->Mcu_Catatan,
                'Mcu_Tanggal' => $data['mcuTanggal'] ?? $sub->Mcu_Tanggal,
            ] : [];

            // SATU TRANSAKSI: "hasilnya begini" dan "maka tahapnya begini" adalah
            // satu keputusan. Bila evaluasi gagal setelah aktivitasnya ditandai
            // final, tak ada jalan mengulang — pintu ini menolak aktivitas yang
            // sudah final, dan tahapnya diam menunggu kesimpulan yang tak datang.
            $outcome = DB::transaction(function () use ($realId, $sub, $data, $catatan, $html, $mcu, $nama, $adminYangMemutuskan) {
                DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                    'Status' => 'SELESAI',
                    // INFORMATIF pada UJIAN ONLINE tetap menyimpan verdict-nya:
                    // itulah satu-satunya alasan admin menekan Lulus/Tidak Lulus.
                    // Yang tidak diberi verdict hanya INFORMATIF non-ujian
                    // (wawancara pendamping, catatan) — di sana perannya memang
                    // sekadar bahan pertimbangan.
                    'Hasil' => ($sub->Peran === 'INFORMATIF' && ! $adminYangMemutuskan) ? null : $data['hasil'],
                    // Nilai ujian online milik HCLearn — jangan ditimpa kosong.
                    'Nilai' => $adminYangMemutuskan ? $sub->Nilai : ($data['nilai'] ?? null),
                    'Catatan' => $catatan ?: $sub->Catatan,
                    'Catatan_Html' => $html ?: $sub->Catatan_Html,
                    'Flag_Selesai' => 'Y',
                    'Waktu_Selesai' => now(),
                    'Updated_At' => now(),
                    'Updated_By' => $nama,
                    'Updated_By_Id' => session('career_auth.id'),
                ] + $mcu);

                $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

                return $eval['outcome'] ?? null;
            });

            // Tahap menyimpulkan otomatis (mode auto) → kabari kandidat via email,
            // konsisten dengan jalur callback. SIAP_DIPUTUS tidak berkirim email.
            //
            // DI LUAR TRANSAKSI — lihat alasannya di subTesKehadiran().
            if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
                $lamaranId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $sub->Lamaran_Tahap_Id)->value('Lamaran_Id');
                if ($lamaranId) {
                    $this->kirimEmailHasilTahap($lamaranId, $outcome === 'LANJUT');
                }
            }

            Log::channel('web_career')->info("Sub-tes #{$realId} dicatat " . ($data['hasil'] ?? 'SELESAI') . " → evaluasi: " . ($outcome ?? '-'));

            return ResponseHelper::success(['outcome' => $outcome], 'Hasil dicatat.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal catat hasil sub-tes #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memproses.', 500);
        }
    }

    /**
     * GET berkas — PROFIL LENGKAP kandidat untuk offcanvas: seluruh FORMULIR yang
     * sudah diisi (di-loop, urut tahap) + berkas/PDF tiap formulir + biodata.
     *
     * "Looping": formulir yang tampil = pengisian yang benar-benar ADA. Kandidat di
     * tahap 4 yang mengisi formulir di tahap 1 & 3 otomatis menampilkan keduanya;
     * yang baru di tahap 2 hanya menampilkan formulir tahap 1.
     */
    public function worklistBerkas(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Id_Lamaran', $realId)
            ->select('l.*', 'p.Nama as ProgramNama', 'u.Nama as Pelamar', 'u.Email as EmailAkun', 'x.Posisi')
            ->first();

        if (! $lamaran) {
            return ResponseHelper::error('Lamaran tidak ditemukan.', 404);
        }

        $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian as fp')
            ->leftJoin('N_WEB_CAREERS_Lamaran_Tahap as t', 't.Id_Lamaran_Tahap', '=', 'fp.Lamaran_Tahap_Id')
            ->where('fp.Lamaran_Id', $realId)
            ->orderBy('t.Urutan')
            ->select('fp.*', 't.Urutan as TahapUrutan', 't.Label as TahapLabel')
            ->get();

        $berkasPer = DB::table('N_WEB_CAREERS_Formulir_Berkas')
            ->whereIn('Formulir_Pengisian_Id', $pengisian->pluck('Id_Formulir_Pengisian')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Formulir_Pengisian_Id');

        $formulir = $pengisian->values()->map(function ($fp, $i) use ($berkasPer) {
            $jawaban = json_decode($fp->Jawaban_Json ?: '{}', true) ?: [];

            $berkas = collect($berkasPer->get($fp->Id_Formulir_Pengisian, []))->map(function ($b) {
                $ext = strtolower($b->Ekstensi ?: pathinfo($b->Nama_Asli, PATHINFO_EXTENSION));

                return [
                    'field' => $b->Field_Key,
                    'nama' => $b->Nama_Asli,
                    'url' => url('/api/v1/karir/lamaran/berkas/file/' . Hashids::encode($b->Id_Formulir_Berkas)),
                    'ext' => $ext,
                    'mime' => $b->Mime,
                    'isPdf' => $ext === 'pdf' || $b->Mime === 'application/pdf',
                    'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'ukuran' => (int) $b->Ukuran_Byte,
                    'status' => $b->Status_Verifikasi,
                ];
            })->values();

            return [
                'no' => $i + 1,
                'urutan' => (int) ($fp->TahapUrutan ?? 0),
                'label' => $fp->TahapLabel ?: ($fp->Sumber === 'PENDAFTARAN' ? 'Formulir Pendaftaran' : 'Formulir Tahap'),
                'sumber' => $fp->Sumber,
                // Kode komponen skema (FORMULIR_1/2/…) — dipakai layar untuk
                // mencari LABEL ASLI tiap isian. Tanpa ini label cuma bisa
                // ditebak dari nama kuncinya, dan `v_nama`/`v_wa` terbaca
                // "V Nama"/"V Wa": bahasa mesin, bukan bahasa manusia.
                'komponen' => $fp->Komponen_Kode,
                // String mentah SQL Server — optional()->__toString() di atasnya
                // menghasilkan NULL, membuat formulir terkirim dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Isian yang ternyata BERKAS dibawa berikut url-nya, supaya admin
                // bisa membuka dokumennya langsung dari daftar isian dan tidak
                // hanya melihat nama berkas sebagai teks mati.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    $b = $berkas->firstWhere('field', $k);
                    // Aturan yang SAMA PERSIS dengan layar kandidat — satu
                    // sumber, bukan dua salinan. Lihat nilaiIsian().
                    $isi = self::nilaiIsian($v);

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => $isi['nilai'],
                        'baris' => $isi['baris'],
                        'berkas' => $b ? [
                            'field' => $b['field'],
                            'nama' => $b['nama'],
                            'url' => $b['url'],
                            'ext' => $b['ext'],
                            'isImage' => $b['isImage'],
                            'isPdf' => $b['isPdf'],
                        ] : null,
                    ];
                })->values(),
                'berkas' => $berkas,
            ];
        });

        return ResponseHelper::success([
            'lamaran' => [
                'kode' => $lamaran->Kode,
                // Sumber yang sama dengan kartu worklist — kalau berbeda, satu
                // orang punya dua nama di dua layar yang saling bersebelahan.
                'pelamar' => self::namaResmiPerLamaran([(int) $realId])->get((int) $realId)
                    ?: ($lamaran->Pelamar ?: $lamaran->Created_By),
                'pelamarAkun' => $lamaran->Pelamar ?: null,
                'email' => $lamaran->EmailAkun,
                'posisi' => $lamaran->Posisi ?: $lamaran->Kategori,
                'program' => $lamaran->ProgramNama,
                'kategori' => $lamaran->Kategori,
                'status' => $lamaran->Status,
                'urutan' => (int) $lamaran->Urutan_Tahap,
                'totalTahap' => (int) $lamaran->Total_Tahap,
            ],
            'formulir' => $formulir,
        ], 'Profil kandidat');
    }

    /** GET pratinjau 1 berkas (PDF/gambar/dll) untuk offcanvas/lightbox. */
    public function berkasFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Id_Formulir_Berkas', $realId)->first();
        if (! $b) {
            abort(404);
        }

        // Berkas apply-form tersimpan di GCS (bucket PRIVAT) — akses lewat
        // SIGNED URL berumur pendek (redirect 302), bukan baca file lokal.
        if ($b->Path_File) {
            try {
                $gcs = Storage::disk(GcsBerkas::DISK);
                if ($gcs->exists($b->Path_File)) {
                    return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
                }
            } catch (\Throwable $e) {
                Log::channel('web_career')->warning('Signed URL GCS gagal untuk berkas ' . $b->Id_Formulir_Berkas . ': ' . $e->getMessage());
            }
        }

        // Fallback lokal (data lama / lingkungan dev tanpa GCS).
        foreach ([storage_path('app/' . $b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }

    /** GET /api/v1/lamaran/pengisian/{id} — admin melihat jawaban kandidat. */
    public function lihatPengisian(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Id_Formulir_Pengisian', $realId)->first();
        if (! $row) {
            return ResponseHelper::error('Pengisian tidak ditemukan.', 404);
        }

        return ResponseHelper::success([
            'komponen' => $row->Komponen_Kode,
            'jawaban' => json_decode($row->Jawaban_Json ?: '{}', true),
            'waktuKirim' => $row->Waktu_Kirim,
        ], 'Jawaban kandidat');
    }
}
