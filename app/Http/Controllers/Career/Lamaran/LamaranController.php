<?php

namespace App\Http\Controllers\Career\Lamaran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcApplyFormJob;
use App\Support\Career\LamaranService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                $konten = file_get_contents($file->getRealPath()); // binary di memori (≤2MB), bukan base64
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
                $konten = file_get_contents($f->getRealPath());
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
            Log::channel('web_career')->warning('[APPLY] unggah berkas gagal: ' . $e->getMessage());

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
        return Inertia::render('Career/portal/LamaranSaya', CareerShell::props('/kandidat/portal', 'Lamaran Saya', [
            'lamaran' => $this->lamaranSaya(),
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

        return $rows->map(function ($l) use ($petaMt, $petaRek) {
            $pbHash = $l->Pembukaan_Id ? Hashids::encode($l->Pembukaan_Id) : null;
            $posHash = $l->Program_Posisi_Id ? Hashids::encode($l->Program_Posisi_Id) : null;
            $kartu = $l->Kategori === 'MT'
                ? ($petaMt[$pbHash] ?? null)
                : ($petaRek[$pbHash . '|' . $posHash] ?? null);

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
            ->where('l.Id_Lamaran', $realId)
            ->where('l.Id_Users', $userId)
            ->select('l.*', 'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori', 'p.Penyelenggara',
                'x.Posisi', 'x.Lokasi', 'x.Departemen', 'b.Nama as BatchNama')
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

        $penjadwalanTahapIds = $tahapRows->pluck('Penjadwalan_Tahap_Id')->filter()->unique()->all();

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

        $tahap = $tahapRows->map(function ($t) use ($ujianByTahap, $sekarang) {
            $ujian = null;
            $u = $t->Penjadwalan_Tahap_Id ? $ujianByTahap->get($t->Penjadwalan_Tahap_Id) : null;

            if ($u) {
                $mulai = $u->Jendela_Mulai ? \Carbon\Carbon::parse($u->Jendela_Mulai) : null;
                $akhir = $u->Jendela_Akhir ? \Carbon\Carbon::parse($u->Jendela_Akhir) : null;
                $dalamJendela = $mulai && $akhir && $sekarang->betweenIncluded($mulai, $akhir);

                $ujian = [
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
            }

            return [
                'id' => Hashids::encode($t->Id_Lamaran_Tahap),
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'provider' => $t->Provider,
                'jenisTes' => $t->Jenis_Tes_Kode,
                'formulir' => $t->Formulir_Kode,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
                'skor' => $t->Skor,
                'catatan' => $t->Catatan,
                'waktuMulai' => optional($t->Waktu_Mulai)->__toString(),
                'waktuSelesai' => optional($t->Waktu_Selesai)->__toString(),
                'sudahIsi' => (bool) $t->Formulir_Pengisian_Id,
                'butuhJadwal' => $t->Provider === 'THIRD_PARTY' && ! $u,
                'ujian' => $ujian,
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

        // KONTEKS FORMULIR. Field ber-sumber_opsi (mis. Nama Kampus) mengambil
        // pilihannya dari sini. Channel UMUM/KAMPUS SUDAH DIGABUNG: semua pelamar
        // memilih kampus dari MASTER KAMPUS (daftar penuh, bisa dicari), dan
        // TIDAK boleh menambah sendiri. Pembatasan kampus mitra (bila perlu)
        // dilakukan lewat SYARAT auto-gugur (operator ADA_DI), bukan channel.
        $konteks = [
            'kampus' => DB::table('N_WEB_CAREERS_Master_Kampus')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')
                ->pluck('Nama')
                ->values()
                ->all(),
        ];

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
            // Profil untuk prefill field bertipe "terisi otomatis".
            'profil' => [
                'nama' => session('career_auth.nama'),
                'email' => session('career_auth.email'),
                'hp' => session('career_auth.hp'),
            ],
        ]));
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
                DB::raw("SUM(CASE WHEN Status <> 'GUGUR' THEN 1 ELSE 0 END) as aktif"),
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
                ])->all()
            : [];

        // Pelamar program ini + tahap-tahapnya.
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Program_Id', $programId)
            ->orderByDesc('l.Id_Lamaran')
            ->select('l.*', 'u.Nama as Pelamar', 'x.Posisi')
            ->get();

        $tahapPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran')->all() ?: [0])
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Lamaran_Id');

        $pelamar = $lamaran->map(function ($l) use ($tahapPer) {
            $tahapList = collect($tahapPer->get($l->Id_Lamaran, []));

            if ($l->Status === 'GUGUR') {
                $tk = $tahapList->firstWhere('Hasil', 'GUGUR') ?? $tahapList->last();
            } elseif ($l->Status === 'LULUS') {
                $tk = $tahapList->last();
            } else {
                $tk = $tahapList->firstWhere('Status', 'BERJALAN') ?? $tahapList->first();
            }

            $tAktif = $l->Status === 'BERJALAN' ? $tahapList->firstWhere('Status', 'BERJALAN') : null;
            $skor = $tk->Skor ?? null;
            $isTes = ($tk->Provider ?? null) === 'THIRD_PARTY';
            $butuhKeputusan = $tAktif && $tAktif->Provider === 'INTERNAL' && $tAktif->Formulir_Pengisian_Id && $tAktif->Status === 'BERJALAN';
            $nungguSistem = $tAktif && $tAktif->Provider === 'THIRD_PARTY';

            if ($l->Status === 'GUGUR') {
                $badge = ['tone' => 'gugur', 'teks' => 'Tidak Lolos'];
            } elseif ($l->Status === 'LULUS') {
                $badge = ['tone' => 'lolos', 'teks' => 'Diterima'];
            } elseif ($isTes && $skor !== null) {
                $badge = ['tone' => 'skor', 'teks' => (string) $skor];
            } elseif ($nungguSistem) {
                $badge = ['tone' => 'nunggu', 'teks' => 'Menunggu Tes'];
            } elseif ($butuhKeputusan) {
                $badge = ['tone' => 'perlu', 'teks' => $tAktif->Rekomendasi === 'GUGUR' ? 'Borderline' : 'Perlu Keputusan'];
            } else {
                $badge = ['tone' => 'berjalan', 'teks' => 'Berjalan'];
            }

            return [
                'id' => Hashids::encode($l->Id_Lamaran),
                'tahapId' => $tAktif ? Hashids::encode($tAktif->Id_Lamaran_Tahap) : null,
                'lamaranKode' => $l->Kode,
                'pelamar' => $l->Pelamar ?: $l->Created_By,
                'posisi' => $l->Posisi ?: $l->Kategori,
                'kategori' => $l->Kategori,
                'statusLamaran' => $l->Status,
                'kolomUrutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                'tahap' => $tk->Label ?? '—',
                'urutan' => (int) ($tk->Urutan ?? $l->Urutan_Tahap),
                'totalTahap' => (int) $l->Total_Tahap,
                'badge' => $badge,
                'butuhKeputusan' => (bool) $butuhKeputusan,
                'nungguSistem' => (bool) $nungguSistem,
                'skor' => $skor,
                'rekomendasi' => $tAktif->Rekomendasi ?? null,
                'alasan' => $tAktif->Rekomendasi_Alasan ?? $l->Alasan_Gugur,
                'pengisianId' => ($tAktif && $tAktif->Formulir_Pengisian_Id) ? Hashids::encode($tAktif->Formulir_Pengisian_Id) : null,
            ];
        })->all();

        return [
            'program' => [
                'id' => Hashids::encode($program->Id_Program),
                'nama' => $program->Nama,
                'kategori' => $program->Kategori,
                'alur' => $alur->Nama ?? null,
            ],
            'kolom' => $kolom,
            'pelamar' => $pelamar,
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
            'hasil' => 'required|in:LULUS,GUGUR',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $hasil = $this->svc->ketukPalu((int) $realId, $data['hasil'], $data['catatan'] ?? null, (int) session('career_auth.id'));
            if (! $hasil['ok']) {
                return ResponseHelper::error($hasil['pesan'], 422);
            }

            return ResponseHelper::success(null, $hasil['pesan']);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal ketuk palu: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memproses keputusan.', 500);
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

    /** GET stream 1 berkas (PDF/gambar/dll) untuk pratinjau/unduh di offcanvas. */
    public function berkasFile(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $b = DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Id_Formulir_Berkas', $realId)->first();
        if (! $b) {
            abort(404);
        }

        // Path_File bisa relatif (disk storage) atau absolut — coba lokasi umum.
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
