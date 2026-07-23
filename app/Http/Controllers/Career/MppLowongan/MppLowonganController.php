<?php

namespace App\Http\Controllers\Career\MppLowongan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MONITORING MPP (read-only).
 *
 * Sumber data (SQL Server, koneksi sqlsrv):
 *   HRIS_Transaksi_GForm g  (induk transaksi MPP)
 *     JOIN  N_WEB_CAREERS_Detail_MPP d      ON d.No_Transaksi_MPP = g.No_Transaksi   (1:1)
 *     LEFT  HRIS_Divisi / Sub_Divisi / Level / Jabatan  (ON id + Kode_Perusahaan → Keterangan)
 *   Detail: N_WEB_CAREERS_Points_MPP (Section responsibility|requirement, urut Urutan),
 *           Detail_Skill_MPP→Master_Skill, Detail_Benefit_MPP→Master_Benefit.
 *
 * Optimasi:
 *  - TANPA N+1: list() memakai correlated subquery untuk semua jumlah dalam SATU statement;
 *    detail() memakai jumlah query TETAP (head + points + skill + benefit), bukan per-baris.
 *  - ATOMIC: setiap operasi baca dibungkus DB::transaction agar snapshot konsisten.
 *  - EFISIEN: filter periode via rentang tanggal (sargable → pakai index Tanggal_Periode),
 *    sort pakai kolom terindeks + tie-breaker No_Transaksi (pagination stabil),
 *    escaping wildcard LIKE, pagination OFFSET/FETCH di DB.
 *
 * Mapping label: Status 'Y' = Dibatalkan (selain itu Aktif); Flag_Selesai 'Y' = Selesai (selain itu Belum Selesai).
 */
class MppLowonganController extends Controller
{
    /** Kolom sort yang diizinkan (whitelist) → cegah sort ke kolom sembarang. */
    private const SORTABLE = ['tanggal_periode', 'status'];

    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list()/detail(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/monitoring-mpp/MonitoringMpp',
            CareerShell::props('/karir/monitoring-mpp', 'Monitoring MPP')
        );
    }

    /**
     * Daftar MPP ringkas — pencarian, filter, sort, & PAGINATION dikerjakan di SERVER (DB).
     * Query params: page, per_page, search, status, flag, divisi, periode, sort, dir.
     */
    public function list(Request $request)
    {
        try {
            $page    = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 9)));
            $sort    = (string) $request->query('sort', 'tanggal_periode');
            $dir     = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            if (!in_array($sort, self::SORTABLE, true)) {
                $sort = 'tanggal_periode';
            }

            // Atomic: total & data dibaca dalam satu boundary transaksi.
            $result = DB::transaction(function () use ($request, $page, $perPage, $sort, $dir) {
                $total = $this->applyFilters($this->baseQuery(), $request)->count();

                $data = $this->applyFilters($this->baseQuery(), $request)
                    ->select([
                        'g.No_Transaksi as no_transaksi',
                        'g.Kode_Perusahaan as kode_perusahaan',
                        DB::raw("CASE WHEN g.Status = 'Y' THEN 'Dibatalkan' ELSE 'Aktif' END as status"),
                        DB::raw("CASE WHEN g.Flag_Selesai = 'Y' THEN 'Selesai' ELSE 'Belum Selesai' END as flag_selesai"),
                        DB::raw('CONVERT(varchar(10), g.Tanggal, 23) as tanggal'),
                        DB::raw('CONVERT(varchar(10), g.Tanggal_Periode, 23) as tanggal_periode'),
                        DB::raw('CONVERT(varchar(8), g.Jam, 108) as jam'),
                        'jb.Keterangan as jabatan',
                        'dv.Keterangan as divisi',
                        'sd.Keterangan as sub_divisi',
                        'lv.Keterangan as level',
                        'g.Jumlah_Rekruitmen as jumlah_rekruitmen',
                        DB::raw('COALESCE(k.Nama, g.User_Penganggung_Jawab) as penanggung_jawab'),
                        // Correlated subquery = ikut satu statement (bukan N+1); ditopang index Id_Detail_MPP.
                        DB::raw("(SELECT COUNT(*) FROM N_WEB_CAREERS_Points_MPP p WHERE p.Id_Detail_MPP = d.Id_Detail_MPP AND p.Section = 'responsibility') as jml_tanggung_jawab"),
                        DB::raw("(SELECT COUNT(*) FROM N_WEB_CAREERS_Points_MPP p WHERE p.Id_Detail_MPP = d.Id_Detail_MPP AND p.Section = 'requirement') as jml_persyaratan"),
                        DB::raw('(SELECT COUNT(*) FROM N_WEB_CAREERS_Detail_Skill_MPP sk WHERE sk.Id_Detail_MPP = d.Id_Detail_MPP) as jml_skill'),
                        DB::raw('(SELECT COUNT(*) FROM N_WEB_CAREERS_Detail_Benefit_MPP bn WHERE bn.Id_Detail_MPP = d.Id_Detail_MPP) as jml_benefit'),
                        'me.Nama_Employment as employment_type',
                        'mw.Nama_Workplace as workplace_type',
                        'mx.Nama_Experience_Level as experience_level',
                    ]);

                if ($sort === 'status') {
                    $data->orderByRaw("CASE WHEN g.Status = 'Y' THEN 'Dibatalkan' ELSE 'Aktif' END " . $dir);
                } else {
                    $data->orderBy('g.Tanggal_Periode', $dir);
                }
                $data->orderBy('g.No_Transaksi'); // tie-breaker → urutan & pagination stabil

                $rows = $data->offset(($page - 1) * $perPage)->limit($perPage)->get();

                return ['total' => $total, 'rows' => $rows];
            });

            $rows = collect($result['rows'])->map(fn ($r) => [
                'no_transaksi'       => $r->no_transaksi,
                'kode_perusahaan'    => $r->kode_perusahaan,
                'status'             => $r->status,
                'flag_selesai'       => $r->flag_selesai,
                'tanggal'            => $r->tanggal,
                'tanggal_periode'    => $r->tanggal_periode,
                'jam'                => $r->jam,
                'jabatan'            => $r->jabatan,
                'divisi'             => $r->divisi,
                'sub_divisi'         => $r->sub_divisi,
                'level'              => $r->level,
                'jumlah_rekruitmen'  => (int) $r->jumlah_rekruitmen,
                'penanggung_jawab'   => $r->penanggung_jawab,
                'jml_tanggung_jawab' => (int) $r->jml_tanggung_jawab,
                'jml_persyaratan'    => (int) $r->jml_persyaratan,
                'jml_skill'          => (int) $r->jml_skill,
                'jml_benefit'        => (int) $r->jml_benefit,
                'employment_type'    => $r->employment_type,
                'workplace_type'     => $r->workplace_type,
                'experience_level'   => $r->experience_level,
            ])->values();

            return ResponseHelper::successWithPagination($rows, $page, $perPage, (int) $result['total'], 'Data MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data MPP', 500);
        }
    }

    /** Detail satu MPP (berdasarkan No_Transaksi) — jumlah query TETAP, dibungkus transaksi (atomic). */
    public function detail(string $no)
    {
        try {
            if (!preg_match('#^[A-Za-z0-9\-/]{1,50}$#', $no)) {
                return ResponseHelper::error('No transaksi tidak valid', 422);
            }

            $data = DB::transaction(function () use ($no) {
                $head = $this->baseQuery()
                    ->where('g.No_Transaksi', $no)
                    ->select([
                        'g.No_Transaksi as no_transaksi',
                        'g.Kode_Perusahaan as kode_perusahaan',
                        DB::raw("CASE WHEN g.Status = 'Y' THEN 'Dibatalkan' ELSE 'Aktif' END as status"),
                        DB::raw("CASE WHEN g.Flag_Selesai = 'Y' THEN 'Selesai' ELSE 'Belum Selesai' END as flag_selesai"),
                        DB::raw('CONVERT(varchar(10), g.Tanggal, 23) as tanggal'),
                        DB::raw('CONVERT(varchar(10), g.Tanggal_Periode, 23) as tanggal_periode'),
                        DB::raw('CONVERT(varchar(8), g.Jam, 108) as jam'),
                        'jb.Keterangan as jabatan',
                        'dv.Keterangan as divisi',
                        'sd.Keterangan as sub_divisi',
                        'lv.Keterangan as level',
                        'g.Jumlah_Rekruitmen as jumlah_rekruitmen',
                        DB::raw('COALESCE(k.Nama, g.User_Penganggung_Jawab) as penanggung_jawab'),
                        'd.Deskripsi as deskripsi',
                        'd.Id_Detail_MPP as id_detail_mpp',
                        'me.Nama_Employment as employment_type',
                        'mw.Nama_Workplace as workplace_type',
                        'mx.Nama_Experience_Level as experience_level',
                    ])
                    ->first();

                if (!$head) {
                    return null;
                }
                $id = $head->id_detail_mpp;

                // Satu query untuk KEDUA section (responsibility & requirement) → dipisah di PHP.
                $points = DB::table('N_WEB_CAREERS_Points_MPP')
                    ->where('Id_Detail_MPP', $id)
                    ->orderBy('Urutan')->orderBy('Id_Points_MPP')
                    ->get(['Section', 'Content']);

                $skill = DB::table('N_WEB_CAREERS_Detail_Skill_MPP as sk')
                    ->join('N_WEB_CAREERS_Master_Skill as ms', 'ms.Id_Skill', '=', 'sk.Id_Skill')
                    ->where('sk.Id_Detail_MPP', $id)
                    ->orderBy('ms.Nama_Skill')
                    ->pluck('ms.Nama_Skill');

                $benefit = DB::table('N_WEB_CAREERS_Detail_Benefit_MPP as bn')
                    ->join('N_WEB_CAREERS_Master_Benefit as mb', 'mb.Id_Benefit', '=', 'bn.Id_Benefit')
                    ->where('bn.Id_Detail_MPP', $id)
                    ->orderBy('mb.Nama_Benefit')
                    ->pluck('mb.Nama_Benefit');

                return [
                    'no_transaksi'      => $head->no_transaksi,
                    'kode_perusahaan'   => $head->kode_perusahaan,
                    'status'            => $head->status,
                    'flag_selesai'      => $head->flag_selesai,
                    'tanggal'           => $head->tanggal,
                    'tanggal_periode'   => $head->tanggal_periode,
                    'jam'               => $head->jam,
                    'jabatan'           => $head->jabatan,
                    'divisi'            => $head->divisi,
                    'sub_divisi'        => $head->sub_divisi,
                    'level'             => $head->level,
                    'jumlah_rekruitmen' => (int) $head->jumlah_rekruitmen,
                    'penanggung_jawab'  => $head->penanggung_jawab,
                    'deskripsi'         => $head->deskripsi,
                    'tanggung_jawab'    => $points->where('Section', 'responsibility')->pluck('Content')->values()->all(),
                    'persyaratan'       => $points->where('Section', 'requirement')->pluck('Content')->values()->all(),
                    'skill'             => $skill->all(),
                    'benefit'           => $benefit->all(),
                    'employment_type'   => $head->employment_type,
                    'workplace_type'    => $head->workplace_type,
                    'experience_level'  => $head->experience_level,
                ];
            });

            if (!$data) {
                return ResponseHelper::error('Data MPP tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Detail MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail MPP', 500);
        }
    }

    /** Opsi filter (divisi, periode) + statistik ringkas — dihit sekali saat halaman mount. */
    public function options()
    {
        try {
            $result = DB::transaction(function () {
                $divisi = $this->baseQuery()
                    ->whereNotNull('dv.Keterangan')
                    ->distinct()
                    ->orderBy('dv.Keterangan')
                    ->pluck('dv.Keterangan')
                    ->values();

                $periode = collect(
                    $this->baseQuery()
                        ->whereNotNull('g.Tanggal_Periode')
                        ->select(DB::raw('LEFT(CONVERT(varchar(10), g.Tanggal_Periode, 23), 7) as periode'))
                        ->distinct()
                        ->orderByDesc('periode')
                        ->get()
                )->pluck('periode')->values();

                // Statistik global dalam SATU query agregat.
                $s = $this->baseQuery()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN ISNULL(g.Status, '') <> 'Y' THEN 1 ELSE 0 END) as aktif")
                    ->selectRaw("SUM(CASE WHEN g.Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai")
                    ->selectRaw("SUM(CASE WHEN g.Status = 'Y' THEN 1 ELSE 0 END) as dibatalkan")
                    ->first();

                return [
                    'divisi'  => $divisi->all(),
                    'periode' => $periode->all(),
                    'stats'   => [
                        'total'      => (int) ($s->total ?? 0),
                        'aktif'      => (int) ($s->aktif ?? 0),
                        'selesai'    => (int) ($s->selesai ?? 0),
                        'dibatalkan' => (int) ($s->dibatalkan ?? 0),
                    ],
                ];
            });

            return ResponseHelper::success($result, 'Opsi filter dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi filter', 500);
        }
    }

    // ═══════════════════════ QUERY BUILDER ═══════════════════════

    /**
     * Skeleton join dasar (dipakai list/detail/options) — INNER ke Detail_MPP (1:1),
     * LEFT ke master divisi/sub/level/jabatan (nama dari kolom Keterangan, ON id + Kode_Perusahaan).
     */
    private function baseQuery()
    {
        return DB::table('HRIS_Transaksi_GForm as g')
            ->join('N_WEB_CAREERS_Detail_MPP as d', 'd.No_Transaksi_MPP', '=', 'g.No_Transaksi')
            ->leftJoin('HRIS_Divisi as dv', fn ($j) => $j
                ->on('dv.ID_Divisi', '=', 'g.Id_Divisi')
                ->on('dv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan'))
            ->leftJoin('HRIS_Sub_Divisi as sd', fn ($j) => $j
                ->on('sd.ID_Sub_Divisi', '=', 'g.Id_Sub_Divisi')
                ->on('sd.Kode_Perusahaan', '=', 'g.Kode_Perusahaan'))
            ->leftJoin('HRIS_Level as lv', fn ($j) => $j
                ->on('lv.ID_Level', '=', 'g.Id_Level')
                ->on('lv.Kode_Perusahaan', '=', 'g.Kode_Perusahaan'))
            ->leftJoin('HRIS_Jabatan as jb', fn ($j) => $j
                ->on('jb.ID_Jabatan', '=', 'g.Id_Jabatan')
                ->on('jb.Kode_Perusahaan', '=', 'g.Kode_Perusahaan'))
            // Penanggung jawab: User_Penganggung_Jawab = Karyawan.Kode_Karyawan → ambil Nama.
            ->leftJoin('Karyawan as k', fn ($j) => $j
                ->on('k.Kode_Karyawan', '=', 'g.User_Penganggung_Jawab')
                ->on('k.Kode_Perusahaan', '=', 'g.Kode_Perusahaan'))
            // Extend Phase 2: Employment / Workplace / Experience Level.
            ->leftJoin('N_WEB_CAREERS_Master_Employment as me', 'me.Id_Employment', '=', 'd.Employment_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Workplace as mw', 'mw.Id_Workplace', '=', 'd.Workplace_Type')
            ->leftJoin('N_WEB_CAREERS_Master_Experience_Level as mx', 'mx.Id_Experience_Level', '=', 'd.Experience_Level');
    }

    /** Terapkan search + filter (status/flag/divisi/periode) ke query. Mengembalikan query agar bisa dirantai. */
    private function applyFilters($q, Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            // Escape wildcard LIKE SQL Server ( [ % _ ) agar dianggap literal.
            $esc = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search);
            $like = '%' . $esc . '%';
            $q->where(function ($w) use ($like) {
                $w->where('g.No_Transaksi', 'like', $like)
                    ->orWhere('jb.Keterangan', 'like', $like)
                    ->orWhere('dv.Keterangan', 'like', $like);
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'Dibatalkan') {
            $q->where('g.Status', 'Y');
        } elseif ($status === 'Aktif') {
            $q->whereRaw("ISNULL(g.Status, '') <> 'Y'");
        }

        $flag = trim((string) $request->query('flag', ''));
        if ($flag === 'Selesai') {
            $q->where('g.Flag_Selesai', 'Y');
        } elseif ($flag === 'Belum Selesai') {
            $q->whereRaw("ISNULL(g.Flag_Selesai, '') <> 'Y'");
        }

        $divisi = trim((string) $request->query('divisi', ''));
        if ($divisi !== '') {
            $q->where('dv.Keterangan', $divisi);
        }

        $periode = trim((string) $request->query('periode', ''));
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) {
            // Rentang [awal bulan, awal bulan berikutnya) → sargable, pakai index Tanggal_Periode.
            $start = Carbon::createFromFormat('Y-m-d', $periode . '-01')->startOfDay();
            $end = (clone $start)->addMonthNoOverflow();
            $q->where('g.Tanggal_Periode', '>=', $start)->where('g.Tanggal_Periode', '<', $end);
        }

        return $q;
    }
}
