<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcApplyEmailJob;
use App\Jobs\Career\WcApplyFormJob;
use App\Support\Career\GcsBerkas;
use App\Support\Career\LamaranService;
use App\Support\Career\PipelineProgress;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private LamaranService $svc)
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
            ->get(['Kode', 'Nama', 'Ikon', 'Perilaku_Kode', 'Flag_Formulir', 'Flag_Upload_Hasil', 'Pesan_Kandidat'])
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

        // Cek duplikat lebih awal (umpan balik cepat) — job juga idempoten.
        $sudah = DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Users', $userId)->where('Program_Posisi_Id', (int) $posisiId)->exists();
        if ($sudah) {
            return ResponseHelper::error('Anda sudah melamar posisi ini.', 422);
        }

        // ── KELAYAKAN JALUR (aturan MT/REKRUTMEN + cooldown, master DB) ──
        // Feedback INSTAN sebelum unggah berkas (hindari berkas GCS yatim). Job &
        // service tetap cek ulang (defense-in-depth).
        $kategoriProgram = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('pb.Id_Pembukaan', (int) $pembukaanId)
            ->value('p.Kategori');
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
                ->select('ps.*', 'pt.Waktu_Mulai as Jendela_Mulai', 'pt.Waktu_Akhir as Jendela_Akhir', 'pt.Nama_Ujian')
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

        $tahap = $tahapRows->map(function ($t) use ($ujianByTahap, $subTesRows, $tipeTahap, $bentukUjian) {
            $u = $t->Penjadwalan_Tahap_Id ? $ujianByTahap->get($t->Penjadwalan_Tahap_Id) : null;
            $ujian = $bentukUjian($u);

            $info = $tipeTahap->get($t->Tipe_Tahap_Kode);

            // TIAP AKTIVITAS punya tipenya sendiri: satu tahap boleh berisi ujian
            // online, tes manual, dan wawancara sekaligus. Yang dikatakan kepada
            // kandidat mengikuti tipe AKTIVITAS, bukan tipe tahap — kalau tidak,
            // sesi wawancara ikut diberi kalimat "menunggu token ujian".
            $tes = collect($subTesRows->get($t->Id_Lamaran_Tahap, []))->map(function ($s) use ($ujianByTahap, $bentukUjian, $tipeTahap, $t) {
                $su = $s->Penjadwalan_Tahap_Id ? $ujianByTahap->get($s->Penjadwalan_Tahap_Id) : null;
                $ti = $tipeTahap->get($s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode);
                $online = ($ti->Perilaku_Kode ?? 'MANUAL') === 'CAT';

                return [
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
                    'nilai' => $s->Nilai,
                    'selesai' => $s->Flag_Selesai === 'Y',
                    'butuhJadwal' => $online && $s->Flag_Selesai !== 'Y' && ! $su,
                    'ujian' => $bentukUjian($su),
                ];
            })->values();

            // Tahap menunggu dijadwalkan bila ada aktivitas ujian online yang
            // belum punya sesi. Aktivitas manual tidak ikut dihitung.
            $butuhJadwal = ! $ujian && $tes->contains(fn ($x) => $x['butuhJadwal']);

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
                'hasil' => $t->Hasil,
                'skor' => $t->Skor,
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
            'profil' => [
                'nama' => session('career_auth.nama'),
                'email' => session('career_auth.email'),
                'hp' => session('career_auth.hp'),
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
                'waktuKirim' => optional($fp->Waktu_Kirim)->__toString(),
                'jawaban' => collect($jawaban)->map(fn ($v, $k) => [
                    'key' => $k,
                    'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                    'nilai' => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : $v),
                ])->values(),
                'berkas' => $berkas,
            ];
        })->all();
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
                ->select('l.Id_Users', 'l.Kode', 'l.Status', 'l.Hasil_Akhir', 'l.Program_Id', 'p.Nama as ProgramNama', 'x.Posisi')
                ->first();
            if (! $l || ! $l->Id_Users) {
                return;
            }

            // LULUS + lamaran DITERIMA (tahap terakhir) → LOLOS; LULUS + lanjut → MENUNGGU.
            $status = ! $lulus ? 'GUGUR' : ($l->Status === 'LULUS' ? 'LOLOS' : 'MENUNGGU');

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
                    'wajibUpload' => ($t->Flag_Wajib_Upload ?? 'T') === 'Y',
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
        // Punya hasil sendiri yang harus dicatat tim: aktivitas manual pada tahap
        // multi-aktivitas (di tahap tunggal, keputusan tahap sudah mewakilinya).
        $dicatatTim = ! $online && $jumlahAktivitasTahap > 1;

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
            // Ujian online: hasilnya dari HCLearn → tak ada "Catat Hasil".
            'dapatDicatat' => $dicatatTim && ! $final,
            // Ujian online yang sudah dijadwalkan boleh ditarik hasilnya kapan
            // pun — jaring pengaman saat webhook CAT tidak sampai.
            'dapatSinkron' => $online && ! $final && ! empty($x->Penjadwalan_Tahap_Id),
            // "Tidak hadir" berlaku untuk keduanya — hanya tim yang tahu, dan
            // untuk ujian online inilah jalan keluar bila kandidat tak mengerjakan.
            'dapatTidakHadir' => ($online || $dicatatTim) && ! $final,
            // Penanda UI: aktivitas ini menunggu hasil dari sistem lain.
            'online' => $online,
        ];
    }

    /** PATCH /api/v1/lamaran/tahap/{id}/putus — admin ketuk palu. */
    public function putus(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Tahap tidak valid.', 422);
        }

        $data = $request->validate([
            'hasil' => 'required|in:LULUS,GUGUR,TALENT_POOL',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')->where('Id_Lamaran_Tahap', $realId)->first(['Lamaran_Id']);

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
                $this->kirimEmailHasilTahap((int) $tahap->Lamaran_Id, $data['hasil'] === 'LULUS');
            }

            return ResponseHelper::success(null, $hasil['pesan']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
        }
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
            $folder = $gcs->folderKandidat($now->format('Y'), $now->format('m'), $now->format('d'), ($lam->Nama ?? 'kandidat') . '-hasil-tahap');
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

            // SESI UJIAN DI CAT — dicari lewat id peserta yang dibawa saat
            // penjadwalan; Short_Token jadi cadangan untuk baris lama.
            $token = DB::table('HRIS_KANDIDAT_Ujian_Token')
                ->where('Sumber_Aplikasi', 'WEB_CAREERS')
                // Digrupkan: tanpa kurung, OR akan membatalkan penyaring di atas
                // dan bisa memungut sesi ujian milik kandidat lain.
                ->where(fn ($q) => $q
                    ->where('Id_WC_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                    ->when($peserta->Short_Token, fn ($w, $t) => $w->orWhere('Short_Token', $t)))
                ->orderByDesc('Id_Ujian_Token')
                ->first();
            if (! $token) {
                return ResponseHelper::error('Sesi ujian tidak ditemukan di HCLearn. Periksa penjadwalannya.', 404);
            }

            $nilai = DB::table('HRIS_KANDIDAT_Ujian_Nilai_Akhir')
                ->where('Id_Ujian_Token', $token->Id_Ujian_Token)
                ->orderByDesc('Id_Ujian_Nilai_Akhir')
                ->first();

            // Belum ada nilai → jangan mengarang. Sampaikan apa adanya, sekaligus
            // segarkan status pengerjaan supaya admin melihat perkembangan nyata.
            if (! $nilai) {
                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                    ->update(['Status_Pengerjaan' => $token->Status_Pengerjaan, 'Updated_At' => now()]);

                $status = $token->Status_Pengerjaan ?: 'belum dimulai';

                return ResponseHelper::error("Belum ada nilai di HCLearn untuk \"{$sub->Label}\" — status pengerjaan saat ini: {$status}. Coba lagi setelah kandidat menyelesaikan tesnya.", 422);
            }

            $hasil = $this->prosesHasilUjian($peserta, [
                'Status_Kelulusan' => $nilai->Status_Kelulusan,
                'Total_Nilai' => $nilai->Total_Nilai,
                'Total_Soal' => $nilai->Total_Soal,
                'Ambang_Batas_Nilai' => $nilai->Ambang_Batas_Nilai,
                'Status_Pengerjaan' => $token->Status_Pengerjaan ?: 'selesai',
            ], 'SINKRON');

            if (! $hasil['diproses']) {
                return ResponseHelper::success($hasil, 'Hasil sudah tercatat sebelumnya — tidak ada yang berubah.');
            }

            return ResponseHelper::success(
                $hasil,
                "Hasil \"{$sub->Label}\" ditarik dari HCLearn: {$nilai->Status_Kelulusan} (nilai {$nilai->Total_Nilai})."
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
     */
    public function subTesTidakHadir(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sub-tes tidak valid.', 422);
        }

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
                'waktuKirim' => optional($fp->Waktu_Kirim)->__toString(),
                'jawaban' => collect($jawaban)->map(fn ($v, $k) => [
                    'key' => $k,
                    'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                    'nilai' => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : $v),
                ])->values(),
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
