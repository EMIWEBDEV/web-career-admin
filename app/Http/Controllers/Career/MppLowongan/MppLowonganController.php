<?php

namespace App\Http\Controllers\Career\MppLowongan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * WEB CAREER — MONITORING MPP (read-only, SPA + WEB, tanpa API terpisah).
 *
 * FASE 1 (sekarang): FRONTEND. Data masih DUMMY tapi bentuk JSON-nya PERSIS
 * seperti output query asli (lihat referensi SQL di bawah), sehingga frontend
 * (Vue) tinggal mengonsumsi `res.data.result` apa adanya.
 *
 * FASE 2 (nanti): cukup ganti isi dummyData() dengan query gabungan
 * (Query Builder) — kontrak JSON & seluruh frontend TIDAK berubah.
 *
 * - index()        : render halaman Inertia (shell admin via CareerShell).
 * - list()         : daftar MPP ringkas (+ jumlah tanggung jawab/persyaratan/skill/benefit).
 * - detail($no)    : detail satu MPP (deskripsi, tanggung jawab, persyaratan, skill, benefit).
 *
 * ── Referensi query asli (fase 2) ──────────────────────────────────────────
 * SELECT g.No_Transaksi,
 *   CASE WHEN g.Status='Y' THEN 'Dibatalkan' ELSE 'Aktif' END          AS Status_Label,
 *   CASE WHEN g.Flag_Selesai='Y' THEN 'Selesai' ELSE 'Belum Selesai' END AS Flag_Selesai_Label,
 *   dv.Keterangan AS Nama_Divisi, sd.Keterangan AS Nama_Sub_Divisi,
 *   lv.Keterangan AS Nama_Level,  jb.Keterangan AS Nama_Jabatan,
 *   g.Jumlah_Rekruitmen, d.Deskripsi, ...counts...
 * FROM HRIS_Transaksi_GForm g
 * JOIN N_WEB_CAREERS_Detail_MPP d ON d.No_Transaksi_MPP = g.No_Transaksi
 * LEFT JOIN HRIS_Divisi dv     ON dv.ID_Divisi=g.Id_Divisi        AND dv.Kode_Perusahaan=g.Kode_Perusahaan
 * LEFT JOIN HRIS_Sub_Divisi sd ON sd.ID_Sub_Divisi=g.Id_Sub_Divisi AND sd.Kode_Perusahaan=g.Kode_Perusahaan
 * LEFT JOIN HRIS_Level lv      ON lv.ID_Level=g.Id_Level          AND lv.Kode_Perusahaan=g.Kode_Perusahaan
 * LEFT JOIN HRIS_Jabatan jb    ON jb.ID_Jabatan=g.Id_Jabatan      AND jb.Kode_Perusahaan=g.Kode_Perusahaan
 * -- Points_MPP (Section responsibility|requirement, urut Urutan),
 * -- Detail_Skill_MPP->Master_Skill, Detail_Benefit_MPP->Master_Benefit.
 * ───────────────────────────────────────────────────────────────────────────
 */
class MppLowonganController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list()/detail(). */
    public function index()
    {
        return Inertia::render(
            'Career/admin/monitoring-mpp/MonitoringMpp',
            CareerShell::props('/karir/monitoring-mpp', 'Monitoring MPP')
        );
    }

    /**
     * Daftar MPP ringkas — pencarian, filter, sort, & PAGINATION dikerjakan di SERVER.
     *
     * Query params: page, per_page, search, status, flag, divisi, periode, sort, dir.
     * FASE 2: cukup terjemahkan param-param ini ke where()/orderBy()/paginate() pada
     * query gabungan — kontrak respons (successWithPagination) tetap sama.
     */
    public function list(Request $request)
    {
        try {
            $page    = max(1, (int) $request->query('page', 1));
            $perPage = min(50, max(1, (int) $request->query('per_page', 9)));
            $search  = trim((string) $request->query('search', ''));
            $fStatus = trim((string) $request->query('status', ''));
            $fFlag   = trim((string) $request->query('flag', ''));
            $fDivisi = trim((string) $request->query('divisi', ''));
            $fPeriode = trim((string) $request->query('periode', ''));
            $sort    = (string) $request->query('sort', 'tanggal_periode');
            $dir     = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            // Whitelist kolom sort — cegah sort ke kolom sembarang.
            if (!in_array($sort, ['tanggal_periode', 'status'], true)) {
                $sort = 'tanggal_periode';
            }

            $rows = collect($this->dummyData())
                ->map(fn ($r) => $this->toSummary($r))
                ->filter(function ($r) use ($search, $fStatus, $fFlag, $fDivisi, $fPeriode) {
                    if ($search !== '') {
                        $hay = mb_strtolower($r['no_transaksi'] . ' ' . $r['jabatan'] . ' ' . $r['divisi']);
                        if (!str_contains($hay, mb_strtolower($search))) {
                            return false;
                        }
                    }
                    if ($fStatus !== '' && $r['status'] !== $fStatus) {
                        return false;
                    }
                    if ($fFlag !== '' && $r['flag_selesai'] !== $fFlag) {
                        return false;
                    }
                    if ($fDivisi !== '' && $r['divisi'] !== $fDivisi) {
                        return false;
                    }
                    if ($fPeriode !== '' && substr((string) $r['tanggal_periode'], 0, 7) !== $fPeriode) {
                        return false;
                    }

                    return true;
                })
                ->values();

            $rows = $dir === 'asc' ? $rows->sortBy($sort) : $rows->sortByDesc($sort);
            $rows = $rows->values();

            $total = $rows->count();
            $paged = $rows->slice(($page - 1) * $perPage, $perPage)->values();

            return ResponseHelper::successWithPagination($paged, $page, $perPage, $total, 'Data MPP dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data MPP', 500);
        }
    }

    /** Opsi filter (divisi, periode) + statistik ringkas — dihit sekali saat halaman mount. */
    public function options()
    {
        try {
            $data = collect($this->dummyData());

            $divisi = $data->pluck('divisi')->filter()->unique()->sort()->values();
            $periode = $data->pluck('tanggal_periode')
                ->map(fn ($d) => substr((string) $d, 0, 7))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values();

            $stats = [
                'total'      => $data->count(),
                'aktif'      => $data->where('status', 'Aktif')->count(),
                'selesai'    => $data->where('flag_selesai', 'Selesai')->count(),
                'dibatalkan' => $data->where('status', 'Dibatalkan')->count(),
            ];

            return ResponseHelper::success([
                'divisi'  => $divisi,
                'periode' => $periode,
                'stats'   => $stats,
            ], 'Opsi filter dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi MPP: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi filter', 500);
        }
    }

    /** Ubah satu record penuh -> bentuk ringkas (subset + jumlah item) untuk card/table. */
    private function toSummary(array $r): array
    {
        return [
            'no_transaksi'       => $r['no_transaksi'],
            'kode_perusahaan'    => $r['kode_perusahaan'],
            'status'             => $r['status'],
            'flag_selesai'       => $r['flag_selesai'],
            'tanggal'            => $r['tanggal'],
            'tanggal_periode'    => $r['tanggal_periode'],
            'jam'                => $r['jam'],
            'jabatan'            => $r['jabatan'],
            'divisi'             => $r['divisi'],
            'sub_divisi'         => $r['sub_divisi'],
            'level'              => $r['level'],
            'jumlah_rekruitmen'  => $r['jumlah_rekruitmen'],
            'penanggung_jawab'   => $r['penanggung_jawab'],
            'jml_tanggung_jawab' => count($r['tanggung_jawab']),
            'jml_persyaratan'    => count($r['persyaratan']),
            'jml_skill'          => count($r['skill']),
            'jml_benefit'        => count($r['benefit']),
        ];
    }

    /** Detail satu MPP (berdasarkan No_Transaksi). */
    public function detail(string $no)
    {
        try {
            foreach ($this->dummyData() as $r) {
                if ($r['no_transaksi'] === $no) {
                    return ResponseHelper::success($r, 'Detail MPP dimuat');
                }
            }

            return ResponseHelper::error('Data MPP tidak ditemukan', 404);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail MPP {$no}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail MPP', 500);
        }
    }

    // ═══════════════════════ SUMBER MPP (DUMMY — FASE 1) ═══════════════════════

    /**
     * Data MPP lengkap (dummy). Mencerminkan seed 6 transaksi (MPP-2026-001..006)
     * dengan variasi Status/Flag_Selesai untuk menguji seluruh badge di UI.
     *
     * FASE 2: ganti isi method ini dengan hasil query gabungan (lihat referensi
     * SQL di header kelas). Bentuk tiap item harus tetap sama seperti di bawah.
     */
    private function dummyData(): array
    {
        return [
            [
                'no_transaksi' => 'MPP-2026-001', 'kode_perusahaan' => '001',
                'status' => 'Aktif', 'flag_selesai' => 'Belum Selesai',
                'tanggal' => '2026-07-01', 'tanggal_periode' => '2026-07-01', 'jam' => '09:00:00',
                'jabatan' => 'Staff Accounting', 'divisi' => 'Accounting', 'sub_divisi' => 'Accounting',
                'level' => 'Staff', 'jumlah_rekruitmen' => 2, 'penanggung_jawab' => 'budi.santoso',
                'deskripsi' => 'Mendukung operasional keuangan perusahaan dengan memastikan pencatatan yang akurat dan pelaporan yang tepat waktu.',
                'tanggung_jawab' => [
                    'Mencatat dan memverifikasi transaksi keuangan harian.',
                    'Melakukan rekonsiliasi bank dan buku besar.',
                    'Menyiapkan dokumen perpajakan bulanan.',
                    'Mendukung penyusunan laporan keuangan.',
                ],
                'persyaratan' => [
                    'S1 Akuntansi.',
                    'Pengalaman min. 1 tahun di bidang accounting/tax.',
                    'Menguasai Ms. Excel dan software akuntansi.',
                    'Teliti, jujur, dan disiplin waktu.',
                ],
                'skill' => ['Accounting', 'Pajak', 'Excel', 'Accurate/SAP', 'Ketelitian'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Pelatihan pajak', 'Jenjang karir'],
            ],
            [
                'no_transaksi' => 'MPP-2026-002', 'kode_perusahaan' => '001',
                'status' => 'Aktif', 'flag_selesai' => 'Belum Selesai',
                'tanggal' => '2026-07-05', 'tanggal_periode' => '2026-07-05', 'jam' => '10:30:00',
                'jabatan' => 'Staff Talent Acquisition', 'divisi' => 'HC-GA', 'sub_divisi' => 'Human Capital',
                'level' => 'Staff', 'jumlah_rekruitmen' => 1, 'penanggung_jawab' => 'siti.aminah',
                'deskripsi' => 'Mengelola proses rekrutmen end-to-end mulai dari sourcing kandidat hingga onboarding karyawan baru.',
                'tanggung_jawab' => [
                    'Melakukan sourcing kandidat melalui berbagai platform.',
                    'Menjadwalkan dan melakukan interview awal kandidat.',
                    'Mengelola proses administrasi rekrutmen hingga onboarding.',
                ],
                'persyaratan' => [
                    'S1 Psikologi/Manajemen SDM.',
                    'Pengalaman min. 2 tahun di bidang recruitment.',
                    'Komunikatif dan mampu bekerja dengan target.',
                ],
                'skill' => ['Recruitment', 'Komunikasi', 'Ketelitian'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Bonus tahunan'],
            ],
            [
                'no_transaksi' => 'MPP-2026-003', 'kode_perusahaan' => '001',
                'status' => 'Aktif', 'flag_selesai' => 'Selesai',
                'tanggal' => '2026-06-15', 'tanggal_periode' => '2026-06-15', 'jam' => '13:15:00',
                'jabatan' => 'Staff Digital Marketing', 'divisi' => 'Business Strategy & Marketing', 'sub_divisi' => 'Marketing Development',
                'level' => 'Staff', 'jumlah_rekruitmen' => 3, 'penanggung_jawab' => 'andi.wijaya',
                'deskripsi' => 'Merencanakan dan mengeksekusi strategi pemasaran digital untuk meningkatkan brand awareness perusahaan.',
                'tanggung_jawab' => [
                    'Menyusun dan menjalankan strategi konten media sosial.',
                    'Menganalisis performa campaign digital secara berkala.',
                    'Berkoordinasi dengan tim kreatif untuk materi promosi.',
                ],
                'persyaratan' => [
                    'S1 Marketing/Komunikasi.',
                    'Pengalaman min. 2 tahun di digital marketing.',
                    'Menguasai tools analitik seperti Google Analytics.',
                ],
                'skill' => ['Digital Marketing', 'Copywriting', 'Komunikasi'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Laptop & fasilitas kerja'],
            ],
            [
                'no_transaksi' => 'MPP-2026-004', 'kode_perusahaan' => '001',
                'status' => 'Dibatalkan', 'flag_selesai' => 'Belum Selesai',
                'tanggal' => '2026-07-10', 'tanggal_periode' => '2026-07-10', 'jam' => '08:45:00',
                'jabatan' => 'Staff Graphic Design', 'divisi' => 'Business Strategy & Marketing', 'sub_divisi' => 'Marketing Communication',
                'level' => 'Staff', 'jumlah_rekruitmen' => 1, 'penanggung_jawab' => 'dewi.lestari',
                'deskripsi' => 'Merancang materi visual dan aset grafis untuk kebutuhan pemasaran dan komunikasi perusahaan.',
                'tanggung_jawab' => [
                    'Merancang materi visual untuk kebutuhan promosi & sosial media.',
                    'Menjaga konsistensi brand guideline di seluruh materi.',
                    'Berkolaborasi dengan tim marketing untuk konsep kreatif.',
                ],
                'persyaratan' => [
                    'S1 Desain Komunikasi Visual.',
                    'Portofolio desain grafis yang relevan.',
                    'Menguasai Adobe Illustrator dan Photoshop.',
                ],
                'skill' => ['Komunikasi', 'Problem Solving'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Laptop & fasilitas kerja'],
            ],
            [
                'no_transaksi' => 'MPP-2026-005', 'kode_perusahaan' => '001',
                'status' => 'Aktif', 'flag_selesai' => 'Selesai',
                'tanggal' => '2026-06-20', 'tanggal_periode' => '2026-06-20', 'jam' => '11:00:00',
                'jabatan' => 'SPV Production', 'divisi' => 'Production', 'sub_divisi' => 'Production',
                'level' => 'Senior Officer', 'jumlah_rekruitmen' => 1, 'penanggung_jawab' => 'rudi.hartono',
                'deskripsi' => 'Mengawasi jalannya proses produksi harian agar sesuai target output dan standar kualitas.',
                'tanggung_jawab' => [
                    'Mengawasi jalannya proses produksi harian.',
                    'Memastikan pencapaian target output produksi.',
                    'Melakukan koordinasi dengan tim QC untuk standar mutu.',
                ],
                'persyaratan' => [
                    'S1 Teknik Industri/sejenisnya.',
                    'Pengalaman min. 3 tahun di bidang produksi manufaktur.',
                    'Memiliki jiwa kepemimpinan yang kuat.',
                ],
                'skill' => ['Leadership', 'Quality Control', 'Problem Solving', 'Maintenance Mesin'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Uang makan & transport', 'Bonus tahunan', 'Jenjang karir'],
            ],
            [
                'no_transaksi' => 'MPP-2026-006', 'kode_perusahaan' => '001',
                'status' => 'Aktif', 'flag_selesai' => 'Belum Selesai',
                'tanggal' => '2026-07-15', 'tanggal_periode' => '2026-07-15', 'jam' => '14:20:00',
                'jabatan' => 'SPV HSE', 'divisi' => 'Production Support', 'sub_divisi' => 'Health Safety Environment',
                'level' => 'Senior Officer', 'jumlah_rekruitmen' => 1, 'penanggung_jawab' => 'agus.saputra',
                'deskripsi' => 'Memastikan penerapan standar keselamatan dan kesehatan kerja di seluruh area produksi.',
                'tanggung_jawab' => [
                    'Menyusun dan mengawasi penerapan SOP keselamatan kerja.',
                    'Melakukan inspeksi rutin area produksi.',
                    'Menyusun laporan insiden dan tindak lanjut perbaikan.',
                ],
                'persyaratan' => [
                    'S1 K3/Teknik Lingkungan.',
                    'Memiliki sertifikasi Ahli K3 Umum.',
                    'Pengalaman min. 2 tahun di bidang HSE manufaktur.',
                ],
                'skill' => ['K3/HSE', 'Problem Solving', 'Komunikasi'],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Uang makan & transport', 'Asuransi kesehatan'],
            ],
        ];
    }
}
