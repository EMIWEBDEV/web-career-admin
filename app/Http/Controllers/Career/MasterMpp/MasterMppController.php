<?php

namespace App\Http\Controllers\Career\MasterMpp;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MASTER MPP (buat & kelola transaksi Manpower Planning).
 *
 * Beda dengan master lain: HRIS_Transaksi_GForm BUKAN tabel modul Web Careers
 * (tanpa prefix N_WEB_CAREERS_) — PK komposit Kode_Perusahaan+No_Transaksi,
 * field organisasi (Divisi/Sub Divisi/Level/Jabatan/Lokasi/Penanggung Jawab)
 * semua FK ke tabel HRIS bersama. Detail (N_WEB_CAREERS_Detail_MPP) adalah
 * ekstensi 1:1 milik modul ini.
 *
 * Riwayatnya sudah dirujuk sistem lain (Lamaran, Program_Posisi, Points/Skill/
 * Benefit MPP, dan CareerAdminController::options_mpp() dipakai ProgramKegiatan)
 * — karena itu TIDAK ADA HAPUS PERMANEN, hanya "Batalkan" (Status='Y', semantik
 * yang sudah ada di kolom itu sejak awal).
 *
 * Dulu ada dua halaman terpisah: /karir/monitoring-mpp (baca-saja, memantau)
 * dan /master-mpp (CRUD, mengarang data). Keduanya sudah DIGABUNG ke sini —
 * lihat docs/superpowers/specs/2026-08-06-master-mpp-merge-monitoring-design.md.
 * list()/detail()/opsiFilter() memuat pola query & pagination dari controller
 * lama itu (MppLowonganController, sudah dihapus).
 *
 * Tanggung Jawab/Persyaratan (Points_MPP) dan Skill/Benefit (Detail_Skill_MPP,
 * Detail_Benefit_MPP) BISA DIEDIT lewat store()/update() — lihat
 * docs/superpowers/specs/2026-08-06-master-mpp-jobdesc-design.md. Master
 * Skill/Benefit belum punya halaman kelola sendiri (dibangun terpisah di
 * branch lain); di sini opsinya dibaca DAN dibuat otomatis kalau admin
 * mengetik nama baru di tag-picker — lihat resolveTagIds().
 */
class MasterMppController extends Controller
{
    private const KODE_PERUSAHAAN = '001';

    private const TABEL_G = 'HRIS_Transaksi_GForm';

    private const TABEL_D = 'N_WEB_CAREERS_Detail_MPP';

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri. */
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-mpp/masterMpp',
            CareerShell::props('/master-mpp', 'Master MPP')
        );
    }

    /** Kolom sort yang diizinkan (whitelist) → cegah sort ke kolom sembarang. */
    private const SORTABLE = ['tanggal_periode', 'status'];

    /** Kolom select dasar (list() & detail()) — method, bukan const: butuh DB::raw() di runtime. */
    private function selectList(): array
    {
        return [
            'g.No_Transaksi as no_transaksi',
            'g.Status as status_raw',
            'g.Flag_Selesai as flag_selesai_raw',
            'g.Flag_MT as flag_mt_raw',
            DB::raw('CONVERT(varchar(10), g.Tanggal_Periode, 23) as tanggal_periode'),
            'g.Id_Divisi as id_divisi',
            'dv.Keterangan as divisi',
            'g.Id_Sub_Divisi as id_sub_divisi',
            'sd.Keterangan as sub_divisi',
            'g.Id_Level as id_level',
            'lv.Keterangan as level',
            'g.Id_Jabatan as id_jabatan',
            'jb.Keterangan as jabatan',
            'g.Jumlah_Rekruitmen as jumlah_rekruitmen',
            'g.Kode_Lokasi as kode_lokasi',
            'lok.Nama_Lokasi as lokasi',
            'g.User_Penganggung_Jawab as kode_karyawan',
            'k.Nama as penanggung_jawab',
            'd.Id_Detail_MPP as id_detail_mpp',
            'd.Deskripsi as deskripsi',
            'd.Employment_Type as employment_type_id',
            'me.Nama_Employment as employment_type',
            'd.Workplace_Type as workplace_type_id',
            'mw.Nama_Workplace as workplace_type',
            'd.Experience_Level as experience_level_id',
            'mx.Nama_Experience_Level as experience_level',
        ];
    }

    /** Daftar MPP — join header+detail+master organisasi, search/filter/sort + PAGINATION di server. */
    public function list(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 9)));
            $sort = (string) $request->query('sort', 'tanggal_periode');
            $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            if (!in_array($sort, self::SORTABLE, true)) {
                $sort = 'tanggal_periode';
            }

            $result = DB::transaction(function () use ($request, $page, $perPage, $sort, $dir) {
                $total = $this->applyFilters($this->baseQuery(), $request)->count();

                $data = $this->applyFilters($this->baseQuery(), $request)->select($this->selectList());
                if ($sort === 'status') {
                    $data->orderByRaw("CASE WHEN g.Status = 'Y' THEN 1 ELSE 0 END " . $dir);
                } else {
                    $data->orderBy('g.Tanggal_Periode', $dir);
                }
                $data->orderBy('g.No_Transaksi'); // tie-breaker → urutan & pagination stabil

                $rows = $data->offset(($page - 1) * $perPage)->limit($perPage)->get();

                return ['total' => $total, 'rows' => $rows];
            });

            $rows = collect($result['rows'])->map(fn ($r) => $this->baris($r))->values();

            return ResponseHelper::successWithPagination($rows, $page, $perPage, (int) $result['total'], 'Data MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data MPP', 500);
        }
    }

    /**
     * Detail satu MPP — dipakai panel off-canvas (baca) DAN prefill form Ubah.
     * Termasuk konten job description (Points/Skill/Benefit) baca-saja — dipindah
     * dari Monitoring MPP, tidak ada CRUD baru untuk sub-tabel itu di sini.
     */
    public function detail(string $no)
    {
        try {
            if (!preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
                return ResponseHelper::error('No transaksi tidak valid', 422);
            }

            $data = DB::transaction(function () use ($no) {
                $head = $this->baseQuery()->where('g.No_Transaksi', $no)->select($this->selectList())->first();
                if (!$head) {
                    return null;
                }
                $id = $head->id_detail_mpp;

                $points = DB::table('N_WEB_CAREERS_Points_MPP')
                    ->where('Id_Detail_MPP', $id)
                    ->orderBy('Urutan')->orderBy('Id_Points_MPP')
                    ->get(['Section', 'Content']);

                $skill = DB::table('N_WEB_CAREERS_Detail_Skill_MPP as sk')
                    ->join('N_WEB_CAREERS_Master_Skill as ms', 'ms.Id_Skill', '=', 'sk.Id_Skill')
                    ->leftJoin('N_WEB_CAREERS_Master_Skill_Kategori as msk', 'msk.Id_Master_Skill_Kategori', '=', 'ms.Id_Master_Skill_Kategori')
                    ->where('sk.Id_Detail_MPP', $id)
                    ->orderByRaw('ISNULL(msk.Urutan, 999999)')->orderBy('ms.Nama_Skill')
                    ->get(['ms.Id_Skill as id', 'ms.Nama_Skill as nama', 'msk.Id_Master_Skill_Kategori as kat_id', 'msk.Nama as kat_nama', 'msk.Ikon as kat_ikon', 'msk.Warna as kat_warna']);

                $benefit = DB::table('N_WEB_CAREERS_Detail_Benefit_MPP as bn')
                    ->join('N_WEB_CAREERS_Master_Benefit as mb', 'mb.Id_Benefit', '=', 'bn.Id_Benefit')
                    ->where('bn.Id_Detail_MPP', $id)
                    ->orderBy('mb.Nama_Benefit')
                    ->get(['mb.Id_Benefit as id', 'mb.Nama_Benefit as nama']);

                return array_merge($this->baris($head), [
                    'tanggungJawab' => $points->where('Section', 'responsibility')->pluck('Content')->values()->all(),
                    'persyaratan' => $points->where('Section', 'requirement')->pluck('Content')->values()->all(),
                    'skill' => $skill->map(fn ($r) => $this->barisSkill($r))->values()->all(),
                    'benefit' => $benefit->map(fn ($r) => ['id' => (int) $r->id, 'nama' => $r->nama])->values()->all(),
                ]);
            });

            if (!$data) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Detail MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail master MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail MPP', 500);
        }
    }

    /** Opsi periode (dari data yang ada) + statistik agregat — dipakai toolbar & stat cards. */
    public function opsiFilter()
    {
        try {
            $result = DB::transaction(function () {
                $periode = collect(
                    $this->baseQuery()
                        ->whereNotNull('g.Tanggal_Periode')
                        ->select(DB::raw('LEFT(CONVERT(varchar(10), g.Tanggal_Periode, 23), 7) as periode'))
                        ->distinct()
                        ->orderByDesc('periode')
                        ->get()
                )->pluck('periode')->values();

                $s = $this->baseQuery()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN ISNULL(g.Status, '') <> 'Y' THEN 1 ELSE 0 END) as aktif")
                    ->selectRaw("SUM(CASE WHEN g.Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai")
                    ->selectRaw("SUM(CASE WHEN g.Status = 'Y' THEN 1 ELSE 0 END) as dibatalkan")
                    ->first();

                return [
                    'periode' => $periode->all(),
                    'stats' => [
                        'total' => (int) ($s->total ?? 0),
                        'aktif' => (int) ($s->aktif ?? 0),
                        'selesai' => (int) ($s->selesai ?? 0),
                        'dibatalkan' => (int) ($s->dibatalkan ?? 0),
                    ],
                ];
            });

            return ResponseHelper::success($result, 'Opsi filter dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi filter master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi filter', 500);
        }
    }

    /** Bentuk satu baris MPP (dipakai list() & detail()) — id mentah + label, siap prefill form Ubah. */
    private function baris(object $r): array
    {
        return [
            'noTransaksi' => $r->no_transaksi,
            'status' => $r->status_raw === 'Y' ? 'DIBATALKAN' : 'AKTIF',
            'selesai' => $r->flag_selesai_raw === 'Y',
            // Flag_MT: 'Y' = Management Trainee, 'T' atau NULL = Rekrutmen biasa.
            'jenisProgram' => $r->flag_mt_raw === 'Y' ? 'MT' : 'REKRUTMEN',
            'tanggalPeriode' => $r->tanggal_periode,
            'divisi' => ['id' => $r->id_divisi, 'nama' => $r->divisi],
            'subDivisi' => $r->id_sub_divisi ? ['id' => $r->id_sub_divisi, 'nama' => $r->sub_divisi] : null,
            'level' => ['id' => $r->id_level, 'nama' => $r->level],
            'jabatan' => ['id' => $r->id_jabatan, 'nama' => $r->jabatan],
            'jumlahRekrutmen' => (int) $r->jumlah_rekruitmen,
            'lokasi' => ['kode' => $r->kode_lokasi, 'nama' => $r->lokasi],
            'penanggungJawab' => ['kode' => $r->kode_karyawan, 'nama' => $r->penanggung_jawab ?: $r->kode_karyawan],
            'deskripsi' => $r->deskripsi,
            'employmentType' => $r->employment_type_id ? ['id' => $r->employment_type_id, 'nama' => $r->employment_type] : null,
            'workplaceType' => $r->workplace_type_id ? ['id' => $r->workplace_type_id, 'nama' => $r->workplace_type] : null,
            'experienceLevel' => $r->experience_level_id ? ['id' => $r->experience_level_id, 'nama' => $r->experience_level] : null,
        ];
    }

    /**
     * Bentuk satu baris skill (dipakai opsiSkill() & detail()) — sertakan
     * kategori (Master_Skill_Kategori: ikon+warna) kalau ada. $opsiShape=true
     * memakai bentuk {value,label,...} yang dipahami el-option; false memakai
     * {id,nama,...} yang dipahami frontend untuk chip baca-saja/prefill form.
     */
    private function barisSkill(object $r, bool $opsiShape = false): array
    {
        $kategori = $r->kat_id ? [
            'id' => (int) $r->kat_id,
            'nama' => $r->kat_nama,
            'ikon' => $r->kat_ikon,
            'warna' => $r->kat_warna,
        ] : null;

        return $opsiShape
            ? ['value' => (int) $r->id, 'label' => $r->nama, 'kategori' => $kategori]
            : ['id' => (int) $r->id, 'nama' => $r->nama, 'kategori' => $kategori];
    }

    /** Buat transaksi MPP baru (header + detail, satu transaksi DB). */
    public function store(Request $request)
    {
        try {
            $data = $this->validasi($request);

            $userId = session('career_auth.id');
            $noTransaksi = DB::transaction(function () use ($data, $userId) {
                $no = $this->nomorBaru();

                DB::table(self::TABEL_G)->insert([
                    'Kode_Perusahaan' => self::KODE_PERUSAHAAN,
                    'No_Transaksi' => $no,
                    'Status' => null,
                    'Tanggal' => now(),
                    'Jam' => now()->format('H:i:s'),
                    'Id_Divisi' => $data['idDivisi'],
                    'Id_Sub_Divisi' => $data['idSubDivisi'],
                    'Id_Level' => $data['idLevel'],
                    'Jumlah_Rekruitmen' => $data['jumlahRekrutmen'],
                    'Id_Jabatan' => $data['idJabatan'],
                    'Flag_Selesai' => 'T',
                    'Tanggal_Periode' => $data['tanggalPeriode'],
                    'User_Penganggung_Jawab' => $data['kodeKaryawan'],
                    'Kode_Lokasi' => $data['kodeLokasi'],
                    'Flag_MT' => $data['jenisProgram'] === 'MT' ? 'Y' : null,
                ]);

                $idDetail = DB::table(self::TABEL_D)->insertGetId([
                    'No_Transaksi_MPP' => $no,
                    'Deskripsi' => $data['deskripsi'],
                    'Employment_Type' => $data['employmentType'],
                    'Workplace_Type' => $data['workplaceType'],
                    'Experience_Level' => $data['experienceLevel'],
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ], 'Id_Detail_MPP');

                $this->simpanPoints($idDetail, $data['tanggungJawab'], $data['persyaratan']);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Skill_MPP', 'Id_Skill', $this->resolveTagIds($data['skills'], 'N_WEB_CAREERS_Master_Skill', 'Id_Skill', 'Nama_Skill', $userId), $userId);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Benefit_MPP', 'Id_Benefit', $this->resolveTagIds($data['benefits'], 'N_WEB_CAREERS_Master_Benefit', 'Id_Benefit', 'Nama_Benefit', $userId), $userId);

                return $no;
            });

            Log::channel('web_career')->info("Master MPP dibuat ({$noTransaksi}) oleh " . session('career_auth.nama', 'ADMIN'));

            return ResponseHelper::success(['noTransaksi' => $noTransaksi], 'Transaksi MPP berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat master MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan transaksi MPP', 500);
        }
    }

    /** Ubah transaksi MPP (header + detail). No_Transaksi tidak berubah. */
    public function update(Request $request, string $no)
    {
        try {
            $row = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->first();
            if (!$row) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            $data = $this->validasi($request);
            $userId = session('career_auth.id');
            $idDetail = (int) DB::table(self::TABEL_D)->where('No_Transaksi_MPP', $no)->value('Id_Detail_MPP');

            DB::transaction(function () use ($data, $no, $userId, $idDetail) {
                DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                    'Id_Divisi' => $data['idDivisi'],
                    'Id_Sub_Divisi' => $data['idSubDivisi'],
                    'Id_Level' => $data['idLevel'],
                    'Jumlah_Rekruitmen' => $data['jumlahRekrutmen'],
                    'Id_Jabatan' => $data['idJabatan'],
                    'Tanggal_Periode' => $data['tanggalPeriode'],
                    'User_Penganggung_Jawab' => $data['kodeKaryawan'],
                    'Kode_Lokasi' => $data['kodeLokasi'],
                    'Flag_MT' => $data['jenisProgram'] === 'MT' ? 'Y' : null,
                ]);

                DB::table(self::TABEL_D)->where('No_Transaksi_MPP', $no)->update([
                    'Deskripsi' => $data['deskripsi'],
                    'Employment_Type' => $data['employmentType'],
                    'Workplace_Type' => $data['workplaceType'],
                    'Experience_Level' => $data['experienceLevel'],
                    'Updated_At' => now(),
                    'Updated_By' => $userId,
                ]);

                $this->simpanPoints($idDetail, $data['tanggungJawab'], $data['persyaratan']);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Skill_MPP', 'Id_Skill', $this->resolveTagIds($data['skills'], 'N_WEB_CAREERS_Master_Skill', 'Id_Skill', 'Nama_Skill', $userId), $userId);
                $this->simpanRelasi($idDetail, 'N_WEB_CAREERS_Detail_Benefit_MPP', 'Id_Benefit', $this->resolveTagIds($data['benefits'], 'N_WEB_CAREERS_Master_Benefit', 'Id_Benefit', 'Nama_Benefit', $userId), $userId);
            });

            Log::channel('web_career')->info("Master MPP {$no} diperbarui");

            return ResponseHelper::success(null, 'Transaksi MPP diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update master MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui transaksi MPP', 500);
        }
    }

    /**
     * Batalkan / aktifkan kembali transaksi MPP (Status).
     * TIDAK ADA hapus permanen — lihat catatan kelas. Membatalkan membuat MPP
     * ini berhenti muncul sebagai opsi di ProgramKegiatan (options_mpp), tapi
     * riwayat lamaran/program yang sudah menempel tetap utuh.
     */
    public function batalkan(Request $request, string $no)
    {
        try {
            $batalkan = $request->boolean('batalkan');

            $terpengaruh = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                'Status' => $batalkan ? 'Y' : null,
            ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master MPP {$no} " . ($batalkan ? 'dibatalkan' : 'diaktifkan kembali'));

            return ResponseHelper::success(null, $batalkan ? 'Transaksi dibatalkan' : 'Transaksi diaktifkan kembali');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal mengubah status batal MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status transaksi', 500);
        }
    }

    /** Tandai selesai / belum selesai (Flag_Selesai) — reversibel, risiko rendah. */
    public function selesai(Request $request, string $no)
    {
        try {
            $selesai = $request->boolean('selesai');

            $terpengaruh = DB::table(self::TABEL_G)->where('No_Transaksi', $no)->update([
                'Flag_Selesai' => $selesai ? 'Y' : 'T',
            ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Transaksi MPP tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master MPP {$no} flag selesai = " . ($selesai ? 'Y' : 'T'));

            return ResponseHelper::success(null, $selesai ? 'Ditandai selesai' : 'Ditandai belum selesai');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal mengubah flag selesai MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status selesai', 500);
        }
    }

    // ═══════════════════════ OPSI DROPDOWN (folder sendiri — lihat spec) ═══════════════════════

    public function opsiDivisi()
    {
        $rows = DB::table('HRIS_Divisi')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Keterangan')
            ->get(['ID_Divisi', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Divisi, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi divisi');
    }

    public function opsiSubDivisi(Request $request)
    {
        $idDivisi = (int) $request->query('divisi');
        if (!$idDivisi) {
            return ResponseHelper::success([], 'Opsi sub divisi');
        }

        $rows = DB::table('HRIS_Divisi_Sub_Divisi as rel')
            ->join('HRIS_Sub_Divisi as sd', function ($j) {
                $j->on('sd.ID_Sub_Divisi', '=', 'rel.ID_Sub_Divisi')
                    ->where('sd.Kode_Perusahaan', self::KODE_PERUSAHAAN);
            })
            ->where('rel.ID_Divisi', $idDivisi)
            ->orderBy('sd.Keterangan')
            ->get(['sd.ID_Sub_Divisi', 'sd.Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Sub_Divisi, 'label' => $r->Keterangan])
            ->unique('value')
            ->values();

        return ResponseHelper::success($rows, 'Opsi sub divisi');
    }

    public function opsiLevel()
    {
        $rows = DB::table('HRIS_Level')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Level_Hierarchy')
            ->get(['ID_Level', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Level, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi level');
    }

    public function opsiJabatan()
    {
        $rows = DB::table('HRIS_Jabatan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->orderBy('Keterangan')
            ->get(['ID_Jabatan', 'Keterangan'])
            ->map(fn ($r) => ['value' => (int) $r->ID_Jabatan, 'label' => $r->Keterangan])
            ->values();

        return ResponseHelper::success($rows, 'Opsi jabatan');
    }

    public function opsiLokasi()
    {
        $rows = DB::table('N_HRIS_Master_Lokasi')
            ->where('Status_Aktif', 'Y')
            ->orderBy('Nama_Lokasi')
            ->get(['Kode_Lokasi', 'Nama_Lokasi'])
            ->map(fn ($r) => ['value' => $r->Kode_Lokasi, 'label' => $r->Nama_Lokasi])
            ->values();

        return ResponseHelper::success($rows, 'Opsi lokasi');
    }

    /** Cari karyawan aktif by nama/kode — dibatasi 20 hasil (bukan dump semua). */
    public function opsiKaryawan(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('Karyawan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('Aktif', 'Y');

        if ($q !== '') {
            $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q);
            $like = '%' . $esc . '%';
            $query->where(fn ($w) => $w->where('Nama', 'like', $like)->orWhere('Kode_Karyawan', 'like', $like));
        }

        $rows = $query->orderBy('Nama')->limit(20)
            ->get(['Kode_Karyawan', 'Nama'])
            ->map(fn ($r) => ['value' => $r->Kode_Karyawan, 'label' => "{$r->Nama} ({$r->Kode_Karyawan})"])
            ->values();

        return ResponseHelper::success($rows, 'Opsi karyawan');
    }

    /**
     * Opsi Skill/Benefit — dipakai tag-picker (allow-create) di form MPP.
     * Master Skill/Benefit belum punya halaman kelola sendiri (sedang dibangun
     * terpisah); di sini hanya dibaca untuk sumber opsi + dibuat otomatis kalau
     * admin mengetik nama baru — lihat resolveTagIds().
     *
     * Skill dikelompokkan per N_WEB_CAREERS_Master_Skill_Kategori (ikon+warna) —
     * kategori disertakan per baris supaya frontend bisa mengelompokkan tampilan
     * tag-picker-nya (skill baru dari allow-create tidak berkategori, wajar:
     * kurasi kategori tetap tugas halaman Master Skill yang sedang dibangun).
     */
    public function opsiSkill()
    {
        $rows = DB::table('N_WEB_CAREERS_Master_Skill as s')
            ->leftJoin('N_WEB_CAREERS_Master_Skill_Kategori as k', 'k.Id_Master_Skill_Kategori', '=', 's.Id_Master_Skill_Kategori')
            ->where('s.Flag_Aktif', 'Y')
            ->orderByRaw('ISNULL(k.Urutan, 999999)')->orderBy('s.Nama_Skill')
            ->get(['s.Id_Skill', 's.Nama_Skill', 'k.Id_Master_Skill_Kategori as kat_id', 'k.Nama as kat_nama', 'k.Ikon as kat_ikon', 'k.Warna as kat_warna'])
            ->map(fn ($r) => $this->barisSkill((object) [
                'id' => $r->Id_Skill, 'nama' => $r->Nama_Skill,
                'kat_id' => $r->kat_id, 'kat_nama' => $r->kat_nama, 'kat_ikon' => $r->kat_ikon, 'kat_warna' => $r->kat_warna,
            ], true))
            ->values();

        return ResponseHelper::success($rows, 'Opsi skill');
    }

    public function opsiBenefit()
    {
        $rows = DB::table('N_WEB_CAREERS_Master_Benefit')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama_Benefit')
            ->get(['Id_Benefit', 'Nama_Benefit'])
            ->map(fn ($r) => ['value' => (int) $r->Id_Benefit, 'label' => $r->Nama_Benefit])
            ->values();

        return ResponseHelper::success($rows, 'Opsi benefit');
    }

    /** Opsi klasifikasi lowongan — 3 master yang sudah ada, hanya baris aktif. */
    public function opsiKlasifikasi()
    {
        $ambil = fn ($tabel, $idKol, $namaKol) => DB::table($tabel)
            ->where('Flag_Aktif', 'Y')
            ->orderBy($namaKol)
            ->get([$idKol . ' as id', $namaKol . ' as nama'])
            ->map(fn ($r) => ['value' => (int) $r->id, 'label' => $r->nama])
            ->values();

        return ResponseHelper::success([
            'employment' => $ambil('N_WEB_CAREERS_Master_Employment', 'Id_Employment', 'Nama_Employment'),
            'workplace' => $ambil('N_WEB_CAREERS_Master_Workplace', 'Id_Workplace', 'Nama_Workplace'),
            'experience' => $ambil('N_WEB_CAREERS_Master_Experience_Level', 'Id_Experience_Level', 'Nama_Experience_Level'),
        ], 'Opsi klasifikasi');
    }

    // ═══════════════════════ INTERNAL ═══════════════════════

    /** Skeleton join dasar — sama pola dengan MppLowonganController::baseQuery(). */
    private function baseQuery()
    {
        return DB::table(self::TABEL_G . ' as g')
            ->join(self::TABEL_D . ' as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->leftJoin('HRIS_Divisi as dv', fn ($j) => $j
                ->on('dv.ID_Divisi', '=', 'g.Id_Divisi')
                ->where('dv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Sub_Divisi as sd', fn ($j) => $j
                ->on('sd.ID_Sub_Divisi', '=', 'g.Id_Sub_Divisi')
                ->where('sd.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Level as lv', fn ($j) => $j
                ->on('lv.ID_Level', '=', 'g.Id_Level')
                ->where('lv.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('HRIS_Jabatan as jb', fn ($j) => $j
                ->on('jb.ID_Jabatan', '=', 'g.Id_Jabatan')
                ->where('jb.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('N_HRIS_Master_Lokasi as lok', 'lok.Kode_Lokasi', '=', 'g.Kode_Lokasi')
            ->leftJoin('Karyawan as k', fn ($j) => $j
                ->on('k.Kode_Karyawan', '=', 'g.User_Penganggung_Jawab')
                ->where('k.Kode_Perusahaan', self::KODE_PERUSAHAAN))
            ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Experience_Level as mx', 'mx.Id_Experience_Level', '=', 'd.Experience_Level');
    }

    /** Search + filter (status/periode/divisi) — dipakai list(). */
    private function applyFilters($q, Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search);
            $like = '%' . $esc . '%';
            $q->where(fn ($w) => $w
                ->where('g.No_Transaksi', 'like', $like)
                ->orWhere('jb.Keterangan', 'like', $like)
                ->orWhere('dv.Keterangan', 'like', $like));
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'AKTIF') {
            $q->whereRaw("ISNULL(g.Status, '') <> 'Y'");
        } elseif ($status === 'DIBATALKAN') {
            $q->where('g.Status', 'Y');
        }

        $selesai = trim((string) $request->query('selesai', ''));
        if ($selesai === '1') {
            $q->where('g.Flag_Selesai', 'Y');
        } elseif ($selesai === '0') {
            $q->whereRaw("ISNULL(g.Flag_Selesai, '') <> 'Y'");
        }

        $divisi = (int) $request->query('divisi', 0);
        if ($divisi) {
            $q->where('g.Id_Divisi', $divisi);
        }

        $periode = trim((string) $request->query('periode', ''));
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $periode . '-01')->startOfDay();
            $end = (clone $start)->addMonthNoOverflow();
            $q->where('g.Tanggal_Periode', '>=', $start)->where('g.Tanggal_Periode', '<', $end);
        }

        $employment = (int) $request->query('employment', 0);
        if ($employment) {
            $q->where('d.Employment_Type', $employment);
        }

        $workplace = (int) $request->query('workplace', 0);
        if ($workplace) {
            $q->where('d.Workplace_Type', $workplace);
        }

        $experience = (int) $request->query('experience', 0);
        if ($experience) {
            $q->where('d.Experience_Level', $experience);
        }

        $jenis = trim((string) $request->query('jenis', ''));
        if ($jenis === 'MT') {
            $q->where('g.Flag_MT', 'Y');
        } elseif ($jenis === 'REKRUTMEN') {
            $q->whereRaw("ISNULL(g.Flag_MT, '') <> 'Y'");
        }

        return $q;
    }

    /** Aturan borang — header + detail dalam satu payload. */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'jenisProgram' => 'required|in:REKRUTMEN,MT',
            'idDivisi' => 'required|integer',
            'idSubDivisi' => 'nullable|integer',
            'idLevel' => 'required|integer',
            'idJabatan' => 'required|integer',
            'jumlahRekrutmen' => 'required|integer|min:1',
            'tanggalPeriode' => 'required|date',
            'kodeLokasi' => 'required|string|max:20',
            'kodeKaryawan' => 'required|string|max:25',
            'deskripsi' => 'required|string|max:1000',
            'employmentType' => 'required|integer',
            'workplaceType' => 'required|integer',
            'experienceLevel' => 'required|integer',
            'tanggungJawab' => 'nullable|array',
            'tanggungJawab.*' => 'string|max:300',
            'persyaratan' => 'nullable|array',
            'persyaratan.*' => 'string|max:300',
            // skills/benefits: elemen bisa int (id skill terpilih) ATAU string (nama baru
            // dari tag-picker allow-create) — sengaja tidak dibatasi tipe di sini, semua
            // di-cast string & dipangkas di baris setelah validate().
            'skills' => 'nullable|array',
            'benefits' => 'nullable|array',
        ], [
            'jenisProgram.required' => 'Jenis program wajib dipilih.',
            'jenisProgram.in' => 'Jenis program tidak valid.',
            'idDivisi.required' => 'Divisi wajib dipilih.',
            'idLevel.required' => 'Level wajib dipilih.',
            'idJabatan.required' => 'Jabatan wajib dipilih.',
            'jumlahRekrutmen.required' => 'Jumlah rekrutmen wajib diisi.',
            'jumlahRekrutmen.min' => 'Jumlah rekrutmen minimal 1.',
            'tanggalPeriode.required' => 'Tanggal periode wajib diisi.',
            'kodeLokasi.required' => 'Lokasi wajib dipilih.',
            'kodeKaryawan.required' => 'Penanggung jawab wajib dipilih.',
            'deskripsi.required' => 'Deskripsi lowongan wajib diisi.',
            'employmentType.required' => 'Tipe kerja wajib dipilih.',
            'workplaceType.required' => 'Lokasi kerja wajib dipilih.',
            'experienceLevel.required' => 'Tingkat pengalaman wajib dipilih.',
        ]);
        $data['idSubDivisi'] = $data['idSubDivisi'] ?? null;
        // Baris kosong (mis. ditambah lalu dibatalkan tanpa diisi) tidak ikut disimpan.
        $bersihkan = fn ($arr) => array_values(array_filter(array_map(fn ($v) => trim((string) $v), $arr ?? [])));
        $data['tanggungJawab'] = $bersihkan($data['tanggungJawab'] ?? []);
        $data['persyaratan'] = $bersihkan($data['persyaratan'] ?? []);
        $data['skills'] = $bersihkan($data['skills'] ?? []);
        $data['benefits'] = $bersihkan($data['benefits'] ?? []);

        return $data;
    }

    /**
     * Nomor baru: MP{bulan 2 digit}{tahun 2 digit}{urut 4 digit}, reset tiap bulan.
     * lockForUpdate menyerialkan pembuatan bersamaan; PK komposit tetap jaring
     * pengaman terakhir kalau ada proses lain yang menulis di luar controller ini.
     */
    private function nomorBaru(): string
    {
        $prefix = 'MP' . now()->format('my');

        $max = DB::table(self::TABEL_G)
            ->where('No_Transaksi', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('No_Transaksi');

        $urut = $max ? ((int) substr($max, -4)) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Ganti seluruh Tanggung Jawab & Persyaratan (N_WEB_CAREERS_Points_MPP) satu
     * MPP — hapus semua baris lama lalu tulis ulang. Volumenya kecil per MPP
     * (belasan baris), jadi "ganti semua" lebih sederhana & aman daripada diff.
     */
    private function simpanPoints(int $idDetail, array $tanggungJawab, array $persyaratan): void
    {
        DB::table('N_WEB_CAREERS_Points_MPP')->where('Id_Detail_MPP', $idDetail)->delete();

        $baris = [];
        foreach ($tanggungJawab as $i => $isi) {
            $baris[] = ['Id_Detail_MPP' => $idDetail, 'Section' => 'responsibility', 'Content' => $isi, 'Urutan' => $i + 1];
        }
        foreach ($persyaratan as $i => $isi) {
            $baris[] = ['Id_Detail_MPP' => $idDetail, 'Section' => 'requirement', 'Content' => $isi, 'Urutan' => $i + 1];
        }
        if ($baris) {
            DB::table('N_WEB_CAREERS_Points_MPP')->insert($baris);
        }
    }

    /**
     * Ganti seluruh baris tabel relasi (Detail_Skill_MPP / Detail_Benefit_MPP)
     * satu MPP dengan daftar id master yang sudah di-resolve (lihat resolveTagIds).
     */
    private function simpanRelasi(int $idDetail, string $tabelRelasi, string $kolomId, array $ids, ?int $userId): void
    {
        DB::table($tabelRelasi)->where('Id_Detail_MPP', $idDetail)->delete();

        if (!$ids) {
            return;
        }

        $now = now();
        DB::table($tabelRelasi)->insert(array_map(fn ($id) => [
            'Id_Detail_MPP' => $idDetail,
            $kolomId => $id,
            'Created_At' => $now,
            'Created_By' => $userId,
        ], $ids));
    }

    /**
     * Ubah nilai tag-picker (mix of existing id & nama baru) jadi daftar id master.
     * - Angka → dipakai langsung sebagai id (kalau memang ada di tabel).
     * - Teks → dicari dulu (case-insensitive, supaya "php" tidak dobel dengan "PHP"
     *   yang sudah ada), kalau belum ada baru dibuat baris master baru saat itu juga.
     * Master Skill/Benefit belum punya halaman kelola sendiri — inilah satu-satunya
     * jalur penambahan datanya untuk saat ini.
     */
    private function resolveTagIds(array $values, string $tabel, string $kolomId, string $kolomNama, ?int $userId): array
    {
        $ids = [];
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $id = (int) $v;
                if (DB::table($tabel)->where($kolomId, $id)->exists()) {
                    $ids[] = $id;
                }
                continue;
            }

            $nama = trim((string) $v);
            if ($nama === '') {
                continue;
            }

            $ada = DB::table($tabel)->whereRaw("LOWER({$kolomNama}) = ?", [mb_strtolower($nama)])->value($kolomId);
            if ($ada) {
                $ids[] = (int) $ada;
                continue;
            }

            $ids[] = DB::table($tabel)->insertGetId([
                $kolomNama => mb_substr($nama, 0, 100),
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $userId,
            ], $kolomId);
        }

        return array_values(array_unique($ids));
    }
}
