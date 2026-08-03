<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcApplyEmailJob;
use App\Jobs\Career\WcJadwalEmailJob;
use App\Jobs\Career\WcApplyFormJob;
use App\Support\Career\GcsBerkas;
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
        return self::$tipeCache ??= DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->get(['Kode', 'Nama', 'Ikon', 'Perilaku_Kode', 'Flag_Formulir', 'Flag_Upload_Hasil', 'Flag_Jadwal', 'Flag_Wajib_Luring', 'Flag_Penawaran', 'Pesan_Kandidat'])
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
                'alasan' => 'nullable|string|max:500',
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

        $nama = $jawaban['nama'] ?? $jawaban['nama_lengkap'] ?? session('career_auth.nama', 'Kandidat');
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
            $tes = collect($subTesRows->get($t->Id_Lamaran_Tahap, []))->map(function ($s) use ($ujianByTahap, $bentukUjian, $tipeTahap, $t, $lokasiJadwal) {
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
                    // NILAI sengaja TIDAK dikirim ke portal kandidat. Angka hasil
                    // wawancara/psikotes adalah bahan penilaian internal; yang
                    // berhak diketahui kandidat adalah catatannya dan status
                    // selesai/tidaknya. Menaruhnya di payload sama saja
                    // membocorkannya — cukup buka DevTools untuk membacanya.
                    'catatan' => $s->Catatan,
                    // Aturan unggahan untuk KANDIDAT pada aktivitas ini.
                    // Formatnya ikut dikirim supaya portal menampilkan aturan
                    // yang benar-benar berlaku, bukan aturan umum yang ditebak.
                    'unggah' => ($s->Unggah_Kandidat ?? 'T') === 'Y' ? [
                        'wajib' => ($s->Unggah_Wajib ?? 'T') === 'Y',
                        'format' => array_values(array_filter(array_map('trim', explode(',', (string) ($s->Unggah_Format ?: 'pdf'))))),
                        'maksMb' => (int) ($s->Unggah_Maks_Mb ?: 5),
                        'petunjuk' => $s->Unggah_Petunjuk,
                    ] : null,
                    // Jadwal tatap muka: kapan, di mana / lewat tautan apa.
                    // Inilah yang dicari kandidat begitu diundang wawancara.
                    // Status MCU boleh dilihat kandidat — itu menyangkut dirinya
                    // sendiri. Penyedia & tanggal ikut supaya ia tahu hasil mana
                    // yang dimaksud bila perlu menanyakannya ke klinik.
                    'mcu' => $s->Mcu_Status ? [
                        'status' => $s->Mcu_Status,
                        'label' => match ($s->Mcu_Status) {
                            'FIT' => 'Memenuhi syarat kesehatan',
                            'FIT_WITH_NOTE' => 'Memenuhi syarat dengan catatan',
                            'UNFIT' => 'Belum memenuhi syarat kesehatan',
                            default => $s->Mcu_Status,
                        },
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
                        // Detail yang diketik rekruter ("Gedung B lantai 3").
                        'lokasi' => $s->Jadwal_Lokasi,
                        // Tempatnya sendiri, LENGKAP dengan peta. Kandidat butuh
                        // tahu di mana persisnya — alamat teks menuntut dia
                        // menyalinnya sendiri ke aplikasi peta.
                        'tempat' => \App\Http\Controllers\Career\MasterLokasi\MasterLokasiController::bentukLokasi(
                            $s->Jadwal_Lokasi_Id ? $lokasiJadwal->get($s->Jadwal_Lokasi_Id) : null
                        ),
                        'catatan' => $s->Jadwal_Catatan,
                    ] : null,
                    'selesai' => $s->Flag_Selesai === 'Y',
                    'butuhJadwal' => $online && $s->Flag_Selesai !== 'Y' && ! $su,
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
                // Penawarannya sudah benar-benar diajukan tim? Selama belum,
                // kandidat diberi tahu bahwa suratnya masih disiapkan — bukan
                // disodori tombol "Terima / Mundur" untuk sesuatu yang belum
                // pernah ia terima.
                'penawaranDiajukan' => self::penawaranDiajukan(
                    $subTesRows->get($t->Id_Lamaran_Tahap, []),
                    count($berkasTahap->get($t->Id_Lamaran_Tahap, [])),
                ),
                'tanggapan' => ($t->Tanggapan_Kandidat ?? null) ? [
                    'jawab' => $t->Tanggapan_Kandidat,
                    'catatan' => $t->Tanggapan_Catatan,
                    'waktu' => (string) ($t->Tanggapan_At ?: ''),
                ] : null,
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
            ->select('t.Id_Lamaran_Tahap', 't.Label', 't.Formulir_Kode', 'f.Nama as FormulirNama', 'f.Komponen_Kode')
            ->first();

        $tugas = $aktif ? [
            'tahapId' => Hashids::encode($aktif->Id_Lamaran_Tahap),
            'label' => $aktif->Label,
            'formulir' => $aktif->Formulir_Kode,
            'formulirNama' => $aktif->FormulirNama,
            'komponen' => $aktif->Komponen_Kode,
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
                'kampus' => LamaranService::dataKandidatEmail($realId)['kampus'],
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
                // String mentah dari SQL Server: optional()->__toString()
                // di atasnya menghasilkan NULL, sehingga formulir yang jelas
                // sudah dikirim tetap dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Jawaban yang ternyata BERKAS dibawa berikut url/tipe berkasnya.
                // Tanpa ini nama berkas hanya tampil sebagai teks mati di daftar
                // isian, padahal dokumennya ada dan seharusnya bisa dibuka.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    $b = $berkas->firstWhere('field', $k);

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : $v),
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

        return ResponseHelper::success(self::bentukTesBerkas($baris), 'Berkas terunggah.');
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

        try {
            Storage::disk(GcsBerkas::DISK)->delete($b->Path_File);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning('[TES-BERKAS] sisa GCS gagal dihapus: ' . $e->getMessage());
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Id_Lamaran_Tes_Berkas', $realId)->delete();

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

    /**
     * POST /kandidat/lamaran/tahap/{id}/tanggapan — kandidat menjawab penawaran.
     *
     * DUA JAWABAN, DUA AKIBAT YANG BERBEDA:
     *
     *  TERIMA  Direkam saja. Tahapnya TIDAK ikut diputus — menerima penawaran
     *          bukan akhir proses: kontrak masih disiapkan, tanggal mulai masih
     *          disepakati, dan berkasnya masih diperiksa. Yang berubah cuma satu
     *          hal penting: admin kini tahu kandidat ini menunggu ditindaklanjuti,
     *          bukan sedang menggantung tanpa kabar.
     *
     *  MUNDUR  FINAL dari sisi kandidat, jadi lamarannya langsung ditutup lewat
     *          mesin keputusan yang sama dengan admin (ketukPalu). Kodenya diambil
     *          dari MASTER berdasarkan Flag_Oleh_Kandidat — "menolak penawaran"
     *          dan "mengundurkan diri" adalah dua sebab berbeda, dan tahap
     *          berpenawaran menentukan mana yang berlaku.
     *
     * Kandidat TIDAK bisa meloloskan dirinya sendiri: satu-satunya jawaban yang
     * menutup lamaran di sini adalah yang merugikan dirinya, dan itu memang
     * haknya. Menerima tetap menunggu palu admin.
     */
    public function portalTanggapanPenawaran(Request $request, string $id)
    {
        $userId = (int) session('career_auth.id');
        $realId = Hashids::decode($id)[0] ?? null;

        $data = $request->validate([
            'jawab' => 'required|in:TERIMA,MUNDUR',
            // Alasan mundur diminta — bukan demi formalitas: itulah satu-satunya
            // umpan balik kenapa penawaran kalah, dan tanpanya angka "kandidat
            // mundur" tidak menuntun ke perbaikan apa pun.
            'catatan' => 'nullable|string|max:500',
        ]);

        $tahap = $realId
            ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as h')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'h.Lamaran_Id')
                ->where('h.Id_Lamaran_Tahap', $realId)
                ->where('l.Id_Users', $userId)
                ->select('h.*', 'l.Status as StatusLamaran')
                ->first()
            : null;

        if (! $tahap) {
            return ResponseHelper::error('Tahap tidak ditemukan.', 404);
        }

        if ($tahap->Status !== 'BERJALAN' || $tahap->StatusLamaran !== 'BERJALAN') {
            return ResponseHelper::error('Tahap ini sudah tidak berjalan.', 409);
        }

        $tipe = self::masterTipeTahap()[$tahap->Tipe_Tahap_Kode ?? ''] ?? null;
        if (($tipe->Flag_Penawaran ?? 'T') !== 'Y') {
            return ResponseHelper::error('Tahap ini tidak menuntut jawaban penawaran.', 422);
        }

        if ($tahap->Tanggapan_Kandidat) {
            return ResponseHelper::error('Kamu sudah memberi jawaban untuk tahap ini.', 409);
        }

        // Belum ada penawaran yang diajukan = belum ada yang bisa dijawab.
        // Dijaga di server juga: tombolnya memang disembunyikan di layar, tapi
        // permintaan bisa dikirim langsung tanpa lewat layar.
        $subTes = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Lamaran_Tahap_Id', $realId)->get();
        $jmlBerkas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Lamaran_Tahap_Id', $realId)->count();
        if (! self::penawaranDiajukan($subTes, $jmlBerkas)) {
            return ResponseHelper::error('Penawaran untuk tahap ini belum diajukan tim rekrutmen.', 409);
        }

        $now = now();
        $nama = session('career_auth.nama');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->update([
            'Tanggapan_Kandidat' => $data['jawab'],
            'Tanggapan_Catatan' => $data['catatan'] ?? null,
            'Tanggapan_At' => $now,
            'Updated_At' => $now,
            'Updated_By' => $nama,
        ]);

        if ($data['jawab'] === 'TERIMA') {
            Log::channel('web_career')->info("[PENAWARAN] lamaran #{$tahap->Lamaran_Id} — kandidat MENERIMA di tahap '{$tahap->Label}'.");

            return ResponseHelper::success(null, 'Terima kasih. Jawabanmu sudah kami terima dan tim rekrutmen akan menindaklanjuti.');
        }

        // MUNDUR: kode hasilnya dari master, bukan ditulis di sini. Yang dipakai
        // hasil ber-Flag_Oleh_Kandidat — pada tahap penawaran resmi berarti
        // "menolak penawaran", selebihnya "mengundurkan diri".
        $kode = self::kodeMundurKandidat($tahap->Tipe_Tahap_Kode);
        if (! $kode) {
            return ResponseHelper::error('Jenis keputusan pengunduran diri belum tersedia di master.', 422);
        }

        $hasil = $this->svc->ketukPalu(
            (int) $realId,
            $kode,
            $data['catatan'] ?: 'Kandidat menyatakan mundur lewat portal.',
            null,
        );

        if (! $hasil['ok']) {
            return ResponseHelper::error($hasil['pesan'], 422);
        }

        Log::channel('web_career')->info("[PENAWARAN] lamaran #{$tahap->Lamaran_Id} — kandidat MUNDUR ({$kode}) di tahap '{$tahap->Label}'.");

        return ResponseHelper::success(null, 'Jawabanmu tersimpan. Terima kasih sudah mengabari kami.');
    }

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

        $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Id_Penjadwalan_Peserta', (int) $data['Id_WC_Penjadwalan_Peserta'])
            ->first();
        if (! $peserta) {
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
        $outcome = $eval['outcome'] ?? 'TUNGGU';

        // Email hanya saat tahap BENAR-BENAR menyimpulkan (bukan per sub-tes) —
        // supaya baterai tes tidak membanjiri kandidat dgn email tiap hasil.
        if (in_array($outcome, ['LANJUT', 'GUGUR'], true)) {
            $this->kirimEmailHasilTahap((int) $peserta->Lamaran_Id, $outcome === 'LANJUT');
        }

        Log::channel('web_career')->info("[{$asal}] hasil tes peserta #{$peserta->Id_Penjadwalan_Peserta} = {$hasil} → tahap {$tahap->Label}: {$outcome}.");

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
            $hasil = $this->svc->simpanPengisian((int) $realId, $userId, $data['jawaban'], $request->ip());
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
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
            // berarti membuang berkas yang baru saja diadopsi.
            $pengisianId = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                ->where('Id_Lamaran_Tahap', $realId)
                ->value('Formulir_Pengisian_Id');

            if ($pengisianId) {
                FormulirDrafController::jadikanPermanen((int) $realId, $userId, $pengisianId);
            }

            FormulirDrafController::bersihkan((int) $realId, $userId, hapusBerkas: false);

            return ResponseHelper::success(['rekomendasi' => $hasil['rekomendasi'] ?? null], $hasil['pesan']);
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
                    'talentPool' => ($h->Flag_Talent_Pool ?? 'T') === 'Y',
                    // Keputusan yang datang dari KANDIDAT, bukan dari perusahaan.
                    // Dipisah di layar supaya tidak terbaca sebagai penilaian tim.
                    'olehKandidat' => ($h->Flag_Oleh_Kandidat ?? 'T') === 'Y',
                    'butuhAlasan' => ($h->Butuh_Alasan ?? 'T') === 'Y',
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

        // Perilaku tipe dari master — tak ada kode tipe yang ditulis di sini.
        $tipe = self::masterTipeTahap();

        // Kolom = tahap alur program ini, urut Urutan.
        $kolom = $alur
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alur->Id_Master_Alur)
                ->orderBy('Urutan')
                ->get()
                ->map(fn ($t) => [
                    'urutan' => (int) $t->Urutan,
                    'kode' => $t->Kode,
                    'label' => $t->Label,
                    'provider' => $t->Provider,
                    'tipe' => $t->Tipe_Tahap_Kode,
                    'tipeNama' => $tipe[$t->Tipe_Tahap_Kode]->Nama ?? null,
                    // Cut-off Talent Pool aktif untuk tahap ini? Dipakai worklist
                    // memunculkan tombol "Masuk Talent Pool" sesuai urutan tahap.
                    'talentPool' => ($t->Flag_Talent_Pool ?? 'T') === 'Y',
                    // Upload berkas hasil — dari flag tahap, atau dari tipe yang
                    // memang berbasis berkas (Master Tipe Tahap → Flag_Upload_Hasil).
                    'uploadHasil' => ($t->Flag_Upload_Hasil ?? 'T') === 'Y'
                        || ($tipe[$t->Tipe_Tahap_Kode]->Flag_Upload_Hasil ?? 'T') === 'Y',
                    // Wajib berkas bila tahapnya disetel begitu ATAU tipenya memang
                    // menuntut dokumen (Background Check, Reference Check, Tugas).
                    // Sebelumnya hanya flag per-tahap yang dibaca, sehingga tipe yang
                    // seluruh gunanya adalah dokumen tetap bisa diloloskan kosong.
                    'wajibUpload' => ($t->Flag_Wajib_Upload ?? 'T') === 'Y'
                        || ($tipe[$t->Tipe_Tahap_Kode]->Flag_Upload_Hasil ?? 'T') === 'Y',
                    // Tahap yang membawa PENAWARAN — kandidat harus menjawab
                    // terima atau mundur. Penandanya dari Master Tipe Tahap,
                    // jadi tahap penawaran bernama lain cukup disetel di master.
                    'penawaran' => ($tipe[$t->Tipe_Tahap_Kode]->Flag_Penawaran ?? 'T') === 'Y',
                    // TITIK TUNTAS: meloloskan di sini berarti kandidat DITERIMA,
                    // bukan sekadar maju ke tahap berikutnya. Tombolnya ikut
                    // berganti kata supaya tidak terbaca sebagai langkah antara.
                    'tuntas' => ($t->Flag_Tuntas ?? 'T') === 'Y',
                ])->all()
            : [];

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

        $pelamar = $lamaran->map(function ($l) use ($tahapPer, $subPer, $kuotaPosisi, $terisiKuota, $berkasCount) { // NOSONAR
            $tahapList = collect($tahapPer->get($l->Id_Lamaran, []));

            // Aturan penempatan + badge + kuota dipusatkan di PipelineProgress
            // (dipakai juga oleh halaman Monitoring Rekrutmen).
            $tk = PipelineProgress::tahapKini($l, $tahapList);
            $tAktif = PipelineProgress::tahapAktif($l, $tahapList);
            // Sub-tes tahap aktif ikut dikirim: mode keputusan & kesiapan tahap
            // dinilai dari sana (lihat PipelineProgress::state).
            $st = PipelineProgress::state($l, $tAktif, $tk, $subPer->get($tAktif->Id_Lamaran_Tahap ?? 0, []));
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
                'pelamar' => $l->Pelamar ?: $l->Created_By,
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
                'tests' => collect($subPer->get($tk->Id_Lamaran_Tahap ?? 0, []))->map(fn ($x) => self::rapotTes(
                    $x,
                    count($subPer->get($tk->Id_Lamaran_Tahap ?? 0, []))
                ))->values(),
            ];
        })->all();

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
    private static function rapotTes(object $x, int $jumlahAktivitasTahap): array
    {
        $final = in_array($x->Status, ['SELESAI', 'TIDAK_HADIR'], true);
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
        $dicatatTim = ! $online && $jumlahAktivitasTahap > 1 && ! $isMcu && ! $isPenawaran;

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
            // Tipe yang menuntut waktu & tempat (wawancara, MCU, tes offline).
            // Dibaca dari Master Tipe Tahap — BUKAN daftar kode di dalam kode
            // program, supaya tipe baru cukup ditambahkan lewat master.
            'butuhJadwal' => ($tipe->Flag_Jadwal ?? 'T') === 'Y' && ! $final,
            // Tipe yang MUSTAHIL daring (MCU, tes offline, tanda tangan kontrak).
            // Aturannya melekat di master, bukan ditebak dari nama tipe di layar.
            'wajibLuring' => ($tipe->Flag_Wajib_Luring ?? 'T') === 'Y',
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
                'lokasiId' => $x->Jadwal_Lokasi_Id ? Hashids::encode($x->Jadwal_Lokasi_Id) : null,
                'catatan' => $x->Jadwal_Catatan,
                'olehSiapa' => $x->Jadwal_By,
            ] : null,
            // Ujian online: hasilnya dari HCLearn → tak ada "Catat Hasil".
            'dapatDicatat' => $dicatatTim && ! $final,
            // Ujian online yang sudah dijadwalkan boleh ditarik hasilnya kapan
            // pun — jaring pengaman saat webhook CAT tidak sampai.
            'dapatSinkron' => $online && ! $final && ! empty($x->Penjadwalan_Tahap_Id),
            // "Tidak hadir" berlaku untuk keduanya — hanya tim yang tahu, dan
            // untuk ujian online inilah jalan keluar bila kandidat tak mengerjakan.
            // Penawaran BERJADWAL ikut: ia tak punya "Catat Hasil", jadi tanpa ini
            // kandidat yang tidak datang negosiasi tak bisa ditandai sama sekali.
            'dapatTidakHadir' => ($online || $dicatatTim || ($isPenawaran && $tipeBerjadwal)) && ! $final,
            // Penanda UI: aktivitas ini menunggu hasil dari sistem lain.
            'online' => $online,
        ];
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
            'catatan' => 'nullable|string|max:500',
            // Rincian MCU — hanya terisi bila tahapnya memang berisi pemeriksaan.
            'mcuStatus' => 'nullable|in:FIT,FIT_WITH_NOTE,UNFIT',
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string|max:1000',
            'mcuTanggal' => 'nullable|date',
        ]);

        try {
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first(['Lamaran_Id']);

            // MCU disimpan SEBELUM palu diketuk: begitu tahap ditutup, sub-tesnya
            // ikut final dan tidak boleh disentuh lagi.
            $galatMcu = $this->simpanHasilMcu((int) $realId, $data);
            if ($galatMcu) {
                return ResponseHelper::error($galatMcu, 422);
            }

            $hasil = $this->svc->ketukPalu((int) $realId, $data['hasil'], $data['catatan'] ?? null, (int) session('career_auth.id'));
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
            }

            // KABARI KANDIDAT. Keputusan admin sebelumnya tidak mengirim email
            // sama sekali — kandidat baru tahu kalau kebetulan membuka portal.
            //
            // TALENT_POOL sengaja TIDAK dikabari: ia bukan hasil seleksi yang
            // perlu diumumkan, melainkan catatan internal bahwa kandidat
            // disimpan untuk kesempatan lain. Mengirim email untuk itu justru
            // membingungkan — kandidat merasa diterima padahal tidak.
            if ($tahap && in_array($data['hasil'], ['LULUS', 'GUGUR'], true)) {
                // TALENT_POOL sengaja TIDAK dikirimi email.
                //
                // Kandidat tidak melanjutkan di lowongan ini, tetapi datanya
                // disimpan untuk kesempatan berikutnya — mengabarinya dengan
                // surat "belum dapat kami lanjutkan" salah dua-duanya: ia bukan
                // penolakan, dan bukan kelulusan. Modal konfirmasi di worklist
                // pun sudah menyatakan tidak ada email yang dikirim.
                if ($data['hasil'] !== 'TALENT_POOL') {
                    $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, $data['hasil'] === 'LULUS');
                }
            }

            return ResponseHelper::success(null, $hasil['pesan']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
        }
    }

    /**
     * Rekam hasil MCU pada aktivitas pemeriksaan milik satu tahap.
     *
     * Mengembalikan pesan galat bila datanya kurang, atau null bila beres
     * (termasuk saat tahapnya memang tidak berisi MCU).
     *
     * Aktivitasnya ditutup langsung lewat DB, TANPA memanggil evaluasiTahap():
     * yang menentukan nasib tahap ini adalah palu admin sesaat kemudian, dan
     * membiarkan mesin ikut menyimpulkan lebih dulu berarti tahapnya bisa
     * berpindah keadaan di tengah satu permintaan.
     */
    private function simpanHasilMcu(int $tahapId, array $data): ?string
    {
        $mcu = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Lamaran_Tahap_Id', $tahapId)
            ->where('Tipe_Tahap_Kode', 'MCU')
            ->first();

        if (! $mcu) {
            return null;
        }

        // Talent Pool tidak menilai kandidat di lowongan ini — hasil kesehatan
        // boleh menyusul. LULUS/GUGUR menuntutnya: keputusan tahap MCU tanpa
        // status & penerbitnya tidak punya dasar yang bisa ditelusuri.
        $wajib = in_array($data['hasil'], ['LULUS', 'GUGUR'], true);
        $status = $data['mcuStatus'] ?? null;
        $penyedia = trim((string) ($data['mcuPenyedia'] ?? ''));

        if ($wajib && (! $status || $penyedia === '')) {
            return 'Status kesehatan dan penyedia (klinik/RS) wajib diisi untuk memutus tahap MCU.';
        }

        if (! $status) {
            return null;
        }

        $now = now();
        $nama = session('career_auth.nama');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $mcu->Id_Lamaran_Tahap_Tes)
            ->update([
                'Mcu_Status' => $status,
                'Mcu_Penyedia' => $penyedia ?: null,
                'Mcu_Tanggal' => $data['mcuTanggal'] ?? null,
                'Mcu_Catatan' => $data['mcuCatatan'] ?? null,
                // UNFIT = tidak memenuhi syarat; dua status lainnya memenuhi
                // (yang satu dengan catatan). Aktivitas INFORMATIF tidak diberi
                // hasil — ia memang bukan penentu.
                'Hasil' => $mcu->Peran === 'INFORMATIF' ? null : ($status === 'UNFIT' ? 'GAGAL' : 'LULUS'),
                'Status' => 'SELESAI',
                'Flag_Selesai' => 'Y',
                'Waktu_Selesai' => $mcu->Waktu_Selesai ?: $now,
                'Updated_At' => $now,
                'Updated_By' => $nama,
            ]);

        return null;
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
        try {
            app(GcsBerkas::class)->hapus([$b->Path_File]);
        } catch (\Throwable $e) {
        }
        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')->where('Id_Lamaran_Tahap_Berkas', $realId)->delete();

        return ResponseHelper::success(null, 'Berkas dihapus.');
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
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
            if (! $sub) {
                return ResponseHelper::error('Sub-tes tidak ditemukan.', 404);
            }
            if ($sub->Flag_Selesai === 'Y') {
                return ResponseHelper::error('Sub-tes ini sudah final.', 422);
            }

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
            Log::channel('web_career')->info("Sub-tes #{$realId} ditandai TIDAK_HADIR → evaluasi: " . ($eval['outcome'] ?? '-'));

            return ResponseHelper::success(['outcome' => $eval['outcome'] ?? null], 'Sub-tes ditandai tidak hadir.');
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
            'mode' => 'required|in:DARING,LURING',
            'mulai' => 'required|date',
            'selesai' => 'nullable|date|after:mulai',
            'link' => 'required_if:mode,DARING|nullable|url|max:500',
            // LURING: pilih dari Master Lokasi (berikut petanya). `lokasi` tetap
            // ada sebagai DETAIL — "Gedung B lantai 3, temui resepsionis" — persis
            // seperti catatan alamat pada aplikasi pesan-antar: titik petanya dari
            // master, patokan rincinya diketik.
            'lokasiId' => 'required_if:mode,LURING|nullable|string|max:64',
            'lokasi' => 'nullable|string|max:300',
            'catatan' => 'nullable|string|max:1000',
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

        // Sebagian aktivitas MUSTAHIL daring: MCU itu pemeriksaan fisik, tanda
        // tangan kontrak butuh kehadiran. Dijaga di SERVER juga — layar bisa
        // dilewati lewat DevTools, dan undangan daring untuk MCU akan membuat
        // kandidat datang ke tautan yang tidak akan pernah ada orangnya.
        $tipeSub = self::masterTipeTahap()[$sub->Tipe_Tahap_Kode ?? ''] ?? null;

        if (($tipeSub->Flag_Wajib_Luring ?? 'T') === 'Y' && $data['mode'] !== 'LURING') {
            return ResponseHelper::error(
                ($tipeSub->Nama ?? 'Aktivitas ini') . ' hanya bisa dijadwalkan LURING (tatap muka).',
                422,
            );
        }

        $now = now();

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Id_Lamaran_Tahap_Tes', $realId)
            ->update([
                'Jadwal_Mode' => $data['mode'],
                'Jadwal_Mulai' => $data['mulai'],
                'Jadwal_Selesai' => $data['selesai'] ?? null,
                // Kolom yang tidak dipakai mode terpilih DIKOSONGKAN, bukan
                // dibiarkan berisi nilai lama — sisa tautan pada jadwal luring
                // membuat kandidat mengira wawancaranya tetap daring.
                'Jadwal_Link' => $data['mode'] === 'DARING' ? $data['link'] : null,
                'Jadwal_Lokasi' => $data['mode'] === 'LURING' ? ($data['lokasi'] ?? null) : null,
                'Jadwal_Lokasi_Id' => $data['mode'] === 'LURING' && ! empty($data['lokasiId'])
                    ? (Hashids::decode($data['lokasiId'])[0] ?? null)
                    : null,
                'Jadwal_Catatan' => $data['catatan'] ?? null,
                'Jadwal_At' => $now,
                'Jadwal_By' => session('career_auth.nama'),
                'Jadwal_By_Id' => session('career_auth.id'),
                'Status' => 'DIJADWALKAN',
                'Updated_At' => $now,
                'Updated_By' => session('career_auth.nama'),
            ]);

        $terkirim = $this->kirimUndanganJadwal((int) $realId, (int) $sub->Lamaran_Id);

        return ResponseHelper::success(
            ['emailTerkirim' => $terkirim],
            $terkirim ? 'Jadwal disimpan dan undangan dikirim ke kandidat.' : 'Jadwal disimpan. Undangan email gagal dikirim — periksa log.',
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
            $tempat = $sub->Jadwal_Lokasi_Id
                ? \App\Http\Controllers\Career\MasterLokasi\MasterLokasiController::bentukLokasi(
                    DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $sub->Jadwal_Lokasi_Id)->first()
                )
                : null;

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
            'catatan' => 'nullable|string|max:500',
        ]);

        $sub = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->first();
        if (! $sub) {
            return ResponseHelper::error('Aktivitas tidak ditemukan.', 404);
        }

        if (! $sub->Jadwal_Mulai) {
            return ResponseHelper::error('Aktivitas ini belum dijadwalkan.', 409);
        }

        $now = now();
        $nama = session('career_auth.nama');

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
            'Jadwal_Hadir' => $data['hadir'],
            'Jadwal_Hadir_At' => $now,
            'Jadwal_Hadir_By' => $nama,
            'Catatan' => $data['catatan'] ?: $sub->Catatan,
            'Updated_At' => $now,
            'Updated_By' => $nama,
        ]);

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

            $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));

            return ResponseHelper::success(null, 'Ditandai TIDAK HADIR — tahap dievaluasi ulang.');
        }

        return ResponseHelper::success(null, 'Kehadiran dicatat. Hasil sekarang bisa dilengkapi.');
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
            'catatan' => 'nullable|string|max:500',
            // Field khusus MCU. Menumpang endpoint ini, BUKAN endpoint sendiri:
            // hasil MCU tetap melewati mesin keputusan yang sama seperti hasil
            // aktivitas lain, hanya membawa rincian medis tambahan.
            'mcuStatus' => 'nullable|in:FIT,FIT_WITH_NOTE,UNFIT',
            'mcuPenyedia' => 'nullable|string|max:200',
            'mcuCatatan' => 'nullable|string|max:1000',
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
            if ($online) {
                return ResponseHelper::error(
                    "\"{$sub->Label}\" adalah ujian online — hasilnya masuk sendiri dari HCLearn dan tidak dicatat manual. "
                    . 'Bila kandidat tidak mengerjakannya, tandai "Tidak hadir".',
                    422
                );
            }

            // Sub-tes PENENTU wajib membawa verdict; INFORMATIF cukup selesai + nilai.
            if ($sub->Peran === 'PENENTU' && empty($data['hasil'])) {
                return ResponseHelper::error('Pilih hasil (Lulus / Gagal) untuk aktivitas penentu.', 422);
            }

            $nama = session('career_auth.nama', 'ADMIN');
            $catatan = $data['catatan'] ?? null;

            DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')->where('Id_Lamaran_Tahap_Tes', $realId)->update([
                'Status' => 'SELESAI',
                'Hasil' => $sub->Peran === 'INFORMATIF' ? null : $data['hasil'],
                'Nilai' => $data['nilai'] ?? null,
                'Catatan' => $catatan,
                // Rincian MCU hanya diisi bila memang dikirim — aktivitas non-MCU
                // tidak ikut tercemar kolom kosong bermakna.
                'Mcu_Status' => $data['mcuStatus'] ?? null,
                'Mcu_Penyedia' => $data['mcuPenyedia'] ?? null,
                'Mcu_Catatan' => $data['mcuCatatan'] ?? null,
                'Mcu_Tanggal' => $data['mcuTanggal'] ?? null,
                'Flag_Selesai' => 'Y',
                'Waktu_Selesai' => now(),
                'Updated_At' => now(),
                'Updated_By' => $nama,
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            $eval = $this->svc->evaluasiTahap((int) $sub->Lamaran_Tahap_Id, (int) session('career_auth.id'));
            $outcome = $eval['outcome'] ?? null;

            // Tahap menyimpulkan otomatis (mode auto) → kabari kandidat via email,
            // konsisten dengan jalur callback. SIAP_DIPUTUS tidak berkirim email.
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
                'komponen' => $fp->Komponen_Kode,
                // String mentah SQL Server — optional()->__toString() di atasnya
                // menghasilkan NULL, membuat formulir terkirim dianggap belum.
                'waktuKirim' => (string) ($fp->Waktu_Kirim ?: ''),
                // Isian yang ternyata BERKAS dibawa berikut url-nya, supaya admin
                // bisa membuka dokumennya langsung dari daftar isian dan tidak
                // hanya melihat nama berkas sebagai teks mati.
                'jawaban' => collect($jawaban)->map(function ($v, $k) use ($berkas) {
                    $b = $berkas->firstWhere('field', $k);

                    return [
                        'key' => $k,
                        'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                        'nilai' => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : $v),
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
                'pelamar' => $lamaran->Pelamar ?: $lamaran->Created_By,
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
