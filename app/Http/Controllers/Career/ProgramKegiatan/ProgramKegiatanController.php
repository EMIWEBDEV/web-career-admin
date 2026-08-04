<?php

namespace App\Http\Controllers\Career\ProgramKegiatan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\FieldTurunan;
use App\Support\Career\FormulirSchema;
use App\Support\Career\KodeUnik;
use App\Support\Career\MesinSyarat;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PROGRAM KEGIATAN (induk-detail: Program + Batch + Posisi + Kriteria).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel + Hashids.
 */
class ProgramKegiatanController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/program-kegiatan/programKegiatan', CareerShell::props('/karir/program-kegiatan', 'Program Kegiatan'));
    }

    public function list(Request $request)
    {
        try {
            // ── FILTER SERVER-SIDE (Filter Panel) ──
            $q = trim((string) $request->query('q', ''));
            $kategori = trim((string) $request->query('kategori', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            // Kategori yang BOLEH dilihat pengguna ini (Role_Konten_Access).
            // NULL = tidak dibatasi. Ini gerbang sebenarnya — tab di layar hanya
            // mengikuti, jadi kategori terlarang tak bisa diintip lewat URL.
            $izin = AksesService::kategoriDiizinkan('programPage');

            $dasar = fn () => DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'p.Created_By_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->when($izin, fn ($w) => $w->whereIn('p.Kategori', $izin))
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('p.Nama', 'like', "%{$q}%")
                        ->orWhere('p.Kode', 'like', "%{$q}%")
                        ->orWhere('p.Penyelenggara', 'like', "%{$q}%")
                        ->orWhere('a.Nama', 'like', "%{$q}%");
                }))
                ->when(in_array($status, ['BERJALAN', 'DRAFT', 'SELESAI', 'NONAKTIF'], true), fn ($w) => $w->where('p.Status', $status))
                ->when($dari, fn ($w) => $w->whereDate('p.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('p.Created_At', '<=', $sampai));

            // Hitungan per kategori DIHITUNG SEBELUM tab diterapkan, supaya angka
            // di tiap tab tetap benar saat salah satunya sedang dipilih.
            $hitungKategori = (clone $dasar())
                ->select('p.Kategori', DB::raw('COUNT(*) as jml'))
                ->groupBy('p.Kategori')
                ->pluck('jml', 'Kategori');

            $programs = $dasar()
                ->when($kategori !== '', fn ($w) => $w->where('p.Kategori', $kategori))
                // Terbaru di atas — program yang baru dibuat paling sering dibuka.
                ->orderByDesc('p.Id_Program')
                ->select('p.*', 'u.Nama as Pembuat', 'a.Nama as AlurNama')
                ->get();

            $batch = DB::table('N_WEB_CAREERS_Program_Batch')->orderBy('Id_Program_Batch')->get()->groupBy('Program_Id');
            $posisi = DB::table('N_WEB_CAREERS_Program_Posisi')->orderBy('Id_Program_Posisi')->get()->groupBy('Program_Id');
            $syarat = DB::table('N_WEB_CAREERS_Program_Syarat')->orderBy('Urutan')->get()->groupBy('Program_Id');

            $rows = $programs->map(fn ($p) => [
                'id' => Hashids::encode($p->Id_Program),
                'kode' => $p->Kode,
                'nama' => $p->Nama,
                'kategori' => $p->Kategori,
                'warna' => $p->Warna,
                'mode' => $p->Mode,
                'alur' => $p->Alur_Kode,
                'alurNama' => $p->AlurNama,
                'jadwal' => $p->Jadwal_Kode,
                'penyelenggara' => $p->Penyelenggara,
                'status' => $p->Status,
                'createdBy' => $p->Pembuat ?: $p->Created_By,
                'createdAt' => $p->Created_At,
                'batch' => collect($batch->get($p->Id_Program, []))->map(fn ($b) => ['nama' => $b->Nama, 'kuota' => (int) $b->Kuota, 'terisi' => (int) $b->Terisi, 'status' => $b->Status])->values(),
                'posisi' => collect($posisi->get($p->Id_Program, []))->map(fn ($x) => ['posisi' => $x->Posisi, 'departemen' => $x->Departemen, 'lokasi' => $x->Lokasi, 'kuota' => (int) $x->Kuota, 'status' => $x->Status, 'mppRef' => $x->Mpp_Ref ?? null, 'level' => $x->Level ?? null])->values(),
                // Syarat auto-gugur: satu baris = satu aturan bertingkat, menempel
                // ke tahap alur tertentu (lihat Batch 10).
                'syarat' => collect($syarat->get($p->Id_Program, []))->map(fn ($s) => [
                    'id' => Hashids::encode($s->Id_Program_Syarat),
                    'tahapId' => $s->Master_Alur_Tahap_Id,
                    'formulir' => $s->Formulir_Kode,
                    'nama' => $s->Nama,
                    'aturan' => json_decode($s->Aturan_Json ?: '{}', true),
                    'aksi' => $s->Aksi,
                    'pesanGugur' => $s->Pesan_Gugur,
                    'uji' => $s->Flag_Uji === 'Y',
                    'aktif' => $s->Flag_Aktif === 'Y',
                ])->values(),
            ])->values();

            // Tab kategori DIAMBIL DARI MASTER (bukan hardcode) dan disaring ke
            // kategori yang boleh dilihat pengguna ini. Bila ia hanya berhak atas
            // satu kategori, hanya satu tab yang muncul.
            $tabKategori = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Flag_Aktif', 'Y')
                ->when($izin, fn ($w) => $w->whereIn('Kode', $izin))
                ->orderBy('Id_Master_Talent_Acquisition')
                ->get(['Kode', 'Nama'])
                ->map(fn ($k) => [
                    'kode' => $k->Kode,
                    'nama' => $k->Nama,
                    'jumlah' => (int) ($hitungKategori[$k->Kode] ?? 0),
                ])
                ->values();

            return ResponseHelper::success([
                'data' => $rows,
                'kategori' => $tabKategori,
                'total' => (int) $hitungKategori->sum(),
            ], 'Data program dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat program: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data program', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:150',
            'kategori' => 'required|string|max:20',
            'warna' => 'nullable|string|max:20',
            'mode' => 'nullable|string|max:20',
            'alur' => 'nullable|string|max:30',
            'jadwal' => 'nullable|string|max:30',
            'penyelenggara' => 'nullable|string|max:120',
            // Opsional: saat dibuat status dipaksa BERJALAN (lihat store()), dan saat
            // diubah field ini boleh tidak dikirim -> status lama dipertahankan.
            'status' => 'nullable|in:DRAFT,BERJALAN,SELESAI',
            // Batch, posisi, dan kriteria semuanya OPSIONAL. Program rekrutmen/MT yang
            // jalan langsung tanpa angkatan & tanpa syarat auto-gugur tetap sah.
            'batch' => 'nullable|array',
            'batch.*.nama' => 'required|string|max:120',
            'batch.*.kuota' => 'nullable|integer|min:0',
            'batch.*.terisi' => 'nullable|integer|min:0',
            'batch.*.status' => 'nullable|string|max:20',
            'posisi' => 'nullable|array',
            'posisi.*.posisi' => 'required|string|max:120',
            'posisi.*.departemen' => 'nullable|string|max:120',
            'posisi.*.lokasi' => 'nullable|string|max:80',
            'posisi.*.kuota' => 'nullable|integer|min:0',
            // Sengaja nullable, bukan required: baris posisi lama (diinput manual
            // sebelum aturan "wajib dari MPP") tetap bisa disimpan ulang saat program
            // diedit. Pemaksaan pilih-dari-MPP ada di modal.
            'posisi.*.mppRef' => 'nullable|string|max:60',
            'posisi.*.level' => 'nullable|string|max:40',
            'posisi.*.status' => 'nullable|in:BUKA,PENUH,TUTUP',
            // Syarat auto-gugur. `aturan` berupa pohon DAN/ATAU — strukturnya
            // divalidasi MesinSyarat::bersihkan(), bukan oleh aturan Laravel,
            // karena kedalamannya tidak terbatas.
            'syarat' => 'nullable|array',
            'syarat.*.nama' => 'required|string|max:120',
            'syarat.*.tahapId' => 'nullable|integer',
            'syarat.*.formulir' => 'nullable|string|max:30',
            'syarat.*.aturan' => 'required|array',
            'syarat.*.aksi' => 'nullable|in:TANDAI,GUGUR',
            'syarat.*.pesanGugur' => 'nullable|string|max:500',
            'syarat.*.uji' => 'nullable|boolean',
            'syarat.*.aktif' => 'nullable|boolean',
        ];
    }

    /**
     * Total kursi batch tidak boleh melebihi pagu MPP (jumlah kuota seluruh posisi).
     * Kurang dari pagu boleh — program memang tidak harus mengisi semua kursi sekaligus.
     * Kalau program tidak punya posisi sama sekali, tidak ada pagu yang bisa dilanggar.
     *
     * @return string|null pesan galat, atau null bila lolos
     */
    private function cekKuotaBatch(array $data): ?string
    {
        $pagu = collect($data['posisi'] ?? [])->sum(fn ($p) => (int) ($p['kuota'] ?? 0));
        if ($pagu <= 0) {
            return null;
        }

        $kursi = collect($data['batch'] ?? [])->sum(fn ($b) => (int) ($b['kuota'] ?? 0));

        return $kursi > $pagu
            ? "Total kursi batch ({$kursi}) melebihi kuota MPP ({$pagu})."
            : null;
    }

    private function simpanAnak(int $programId, array $data, ?int $userId): void
    {
        foreach ($data['batch'] ?? [] as $b) {
            DB::table('N_WEB_CAREERS_Program_Batch')->insert(['Program_Id' => $programId, 'Nama' => $b['nama'], 'Kuota' => $b['kuota'] ?? 0, 'Terisi' => $b['terisi'] ?? 0, 'Status' => $b['status'] ?? 'AKTIF', 'Created_By_Id' => $userId, 'Updated_By_Id' => $userId]);
        }
        foreach ($data['posisi'] ?? [] as $p) {
            DB::table('N_WEB_CAREERS_Program_Posisi')->insert(['Program_Id' => $programId, 'Posisi' => $p['posisi'], 'Departemen' => $p['departemen'] ?? null, 'Lokasi' => $p['lokasi'] ?? null, 'Kuota' => $p['kuota'] ?? 0, 'Status' => $p['status'] ?? 'BUKA', 'Mpp_Ref' => $p['mppRef'] ?? null, 'Level' => $p['level'] ?? null, 'Created_By_Id' => $userId, 'Updated_By_Id' => $userId]);
        }
        $now = now();
        $userName = session('career_auth.nama', 'ADMIN');
        $urut = 1;
        $schemaTahap = []; // cache Id_Master_Alur_Tahap -> daftar key field, supaya tidak query berulang

        foreach ($data['syarat'] ?? [] as $s) {
            // Simpul setengah jadi dibuang di sini. Aturan yang tidak lengkap
            // berbahaya: bisa menggugurkan pelamar tanpa maksud siapa pun.
            $aturan = MesinSyarat::bersihkan($s['aturan'] ?? []);
            if (! $aturan) {
                continue;
            }

            // GERBANG FIELD TURUNAN TANPA SUMBER: syarat berbasis "usia"/"jenjang"/
            // dst. hanya berarti kalau formulir tahap itu punya field dengan salah
            // satu key yang dikenal FieldTurunan. Untuk formulir dinamis (bukan
            // FORMULIR_1..4 lama), key-nya bebas ditentukan admin builder — tanpa
            // gerbang ini, syarat GUGUR bisa diam-diam menggugurkan SEMUA kandidat
            // karena field turunannya tidak pernah terhitung.
            //
            // Formulir LAMA (Komponen_Kode terisi) dilewati saja — field-nya cuma
            // ada di skema JS, tidak tercatat di DB, jadi tidak bisa diperiksa dari
            // sini. Formulir lama ditulis dengan field yang sudah cocok dengan
            // FieldTurunan sejak awal, jadi tidak butuh gerbang ini.
            $tahapId = $s['tahapId'] ?? null;
            if ($tahapId) {
                $fieldDipakai = array_intersect(MesinSyarat::fieldDipakai($aturan), array_keys(FieldTurunan::DEFINISI));
                if ($fieldDipakai) {
                    if (! array_key_exists($tahapId, $schemaTahap)) {
                        $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap as t')
                            ->leftJoin('N_WEB_CAREERS_Master_Formulir as f', 'f.Kode', '=', 't.Formulir_Kode')
                            ->where('t.Id_Master_Alur_Tahap', $tahapId)
                            ->first(['t.Formulir_Kode', 'f.Komponen_Kode']);

                        // null = form lama / tidak ada form -> tidak bisa diperiksa, lewati.
                        // array = form dinamis -> daftar key field sungguhan, WAJIB diperiksa.
                        $schemaTahap[$tahapId] = ($tahap && $tahap->Formulir_Kode && ! $tahap->Komponen_Kode)
                            ? FormulirSchema::keyField(FormulirSchema::publishedByKode($tahap->Formulir_Kode)['schema'] ?? null)
                            : null;
                    }

                    if ($schemaTahap[$tahapId] !== null) {
                        foreach ($fieldDipakai as $turunanKey) {
                            if (! FieldTurunan::sumberTersedia($turunanKey, $schemaTahap[$tahapId])) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'syarat' => "Syarat \"{$s['nama']}\" memakai field turunan \"{$turunanKey}\", tapi formulir tahap ini tidak punya field sumbernya — field turunan itu tidak akan pernah terhitung dan syarat ini akan menggugurkan semua kandidat.",
                                ]);
                            }
                        }
                    }
                }
            }

            DB::table('N_WEB_CAREERS_Program_Syarat')->insert([
                'Program_Id' => $programId,
                'Master_Alur_Tahap_Id' => $s['tahapId'] ?? null,
                'Formulir_Kode' => $s['formulir'] ?? null,
                'Nama' => $s['nama'],
                'Aturan_Json' => json_encode($aturan, JSON_UNESCAPED_UNICODE),
                'Aksi' => $s['aksi'] ?? 'TANDAI',
                'Pesan_Gugur' => $s['pesanGugur'] ?? null,
                'Urutan' => $urut++,
                'Flag_Uji' => ! empty($s['uji']) ? 'Y' : 'T',
                'Flag_Aktif' => array_key_exists('aktif', $s) && ! $s['aktif'] ? 'T' : 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
        }
    }

    private function hapusAnak(int $programId): void
    {
        DB::table('N_WEB_CAREERS_Program_Batch')->where('Program_Id', $programId)->delete();
        DB::table('N_WEB_CAREERS_Program_Posisi')->where('Program_Id', $programId)->delete();
        DB::table('N_WEB_CAREERS_Program_Syarat')->where('Program_Id', $programId)->delete();
    }

    /**
     * GUARD INFO DIVISI — dari daftar MPP (mppRefs) posisi terpilih, kembalikan
     * divisi mana yang BELUM punya info di Master Info Divisi (baris belum dibuat).
     * Info divisi WAJIB terisi sebelum lanjut (dipakai landing page). Fail-open:
     * bila cek gagal, TIDAK memblokir admin (cegah bug menjebak).
     */
    public function cekInfoDivisi(Request $request)
    {
        try {
            $refs = collect($request->input('mppRefs', []))->map(fn ($r) => trim((string) $r))->filter()->unique()->values();
            if ($refs->isEmpty()) {
                return ResponseHelper::success(['belumLengkap' => []], 'Tidak ada MPP untuk dicek');
            }

            // MPP → divisi (Id_Divisi + nama).
            $divisi = DB::table('HRIS_Transaksi_GForm as g')
                ->leftJoin('HRIS_Divisi as dv', function ($j) {
                    $j->on('dv.ID_Divisi', '=', 'g.Id_Divisi')->on('dv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan');
                })
                ->whereIn('g.No_Transaksi', $refs->all())
                ->whereNotNull('g.Id_Divisi')
                ->select('g.Id_Divisi', 'dv.Keterangan as Nama')
                ->distinct()->get();

            $idList = $divisi->pluck('Id_Divisi')->filter()->unique()->all() ?: [0];

            // 1) Ada baris info level-divisi?
            $adaInfo = array_flip(DB::table('N_WEB_CAREERS_Division_Informations')->whereIn('Id_Divisi', $idList)->pluck('Id_Divisi')->all());
            // 2) Total sub-divisi per divisi (dari mapping HRIS).
            $subTotal = DB::table('HRIS_Divisi_Sub_Divisi')->whereIn('ID_Divisi', $idList)
                ->select('ID_Divisi', DB::raw('COUNT(DISTINCT ID_Sub_Divisi) as t'))->groupBy('ID_Divisi')->pluck('t', 'ID_Divisi');
            // 3) Sub-divisi yang SUDAH terisi infonya.
            $subTerisi = DB::table('HRIS_Divisi_Sub_Divisi as m')
                ->join('N_WEB_CAREERS_Sub_Divisi_Informations as si', 'si.Id_Sub_Divisi', '=', 'm.ID_Sub_Divisi')
                ->whereIn('m.ID_Divisi', $idList)
                ->select('m.ID_Divisi', DB::raw('COUNT(DISTINCT m.ID_Sub_Divisi) as t'))->groupBy('m.ID_Divisi')->pluck('t', 'ID_Divisi');

            // LENGKAP = ada info divisi + MINIMAL SATU sub-divisi terisi.
            // ACCOUNTING (0/0) → belum lengkap; begitu ada 1 sub terisi → boleh lanjut.
            $belum = [];
            foreach ($divisi as $d) {
                if (isset($belum[$d->Id_Divisi])) {
                    continue;
                }
                $adaRow = isset($adaInfo[$d->Id_Divisi]);
                $total = (int) ($subTotal[$d->Id_Divisi] ?? 0);
                $terisi = (int) ($subTerisi[$d->Id_Divisi] ?? 0);
                $lengkap = $adaRow && $terisi >= 1;
                if ($lengkap) {
                    continue;
                }
                $alasan = ! $adaRow ? 'info divisi belum dibuat'
                    : ($total === 0 ? 'belum ada sub-divisi' : "sub-divisi belum diisi (0/{$total})");
                $belum[$d->Id_Divisi] = [
                    'nama' => $d->Nama ?: ('Divisi #' . $d->Id_Divisi),
                    'id' => Hashids::encode($d->Id_Divisi),
                    'alasan' => $alasan,
                ];
            }

            return ResponseHelper::success(['belumLengkap' => array_values($belum)], 'Cek info divisi');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal cek info divisi: ' . $e->getMessage());

            return ResponseHelper::success(['belumLengkap' => []], 'Cek info divisi (fallback)');
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            if ($galat = $this->cekKuotaBatch($data)) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Program', 'Kode', $data['nama'], 30, 'PROGRAM');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Program')->insertGetId([
                    'Kode' => $kode, 'Nama' => $data['nama'], 'Kategori' => $data['kategori'], 'Warna' => $data['warna'] ?? '#4f46e5',
                    'Mode' => $data['mode'] ?? null, 'Alur_Kode' => $data['alur'] ?? null, 'Jadwal_Kode' => $data['jadwal'] ?? null,
                    // Program baru LANGSUNG berjalan. Admin tidak memilih status saat
                    // membuat — sebuah program yang baru didefinisikan memang dianggap
                    // aktif. DRAFT/SELESAI diatur belakangan lewat toggle / modal ubah.
                    'Penyelenggara' => $data['penyelenggara'] ?? null, 'Status' => 'BERJALAN',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Program');
                $this->simpanAnak($id, $data, $userId);
            });

            Log::channel('web_career')->info("Program dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Program berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat program: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            if ($galat = $this->cekKuotaBatch($data)) {
                return ResponseHelper::error($galat, 422);
            }
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            // $row WAJIB ikut di-use: dipakai sebagai nilai jatuhan untuk Warna & Status.
            DB::transaction(function () use ($data, $realId, $userId, $userName, $row) {
                DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->update([
                    'Nama' => $data['nama'], 'Kategori' => $data['kategori'], 'Warna' => $data['warna'] ?? $row->Warna ?? '#4f46e5',
                    'Mode' => $data['mode'] ?? null, 'Alur_Kode' => $data['alur'] ?? null, 'Jadwal_Kode' => $data['jadwal'] ?? null,
                    // Status boleh tidak dikirim -> pertahankan yang lama.
                    'Penyelenggara' => $data['penyelenggara'] ?? null, 'Status' => $data['status'] ?? $row->Status,
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                $this->hapusAnak($realId);
                $this->simpanAnak($realId, $data, $userId);
            });

            Log::channel('web_career')->info("Program #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Program diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->update([
                'Status' => $aktif ? 'BERJALAN' : 'DRAFT',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Program #{$realId} status " . ($aktif ? 'BERJALAN' : 'DRAFT'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                $this->hapusAnak($realId);
                DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $realId)->delete();
            });
            Log::channel('web_career')->info("Program #{$realId} dihapus");

            return ResponseHelper::success(null, 'Program dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus program #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
