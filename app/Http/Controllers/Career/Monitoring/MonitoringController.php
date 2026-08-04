<?php

namespace App\Http\Controllers\Career\Monitoring;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Http\Controllers\Controller;
use App\Support\Career\AlurKolom;
use App\Support\Career\GcsBerkas;
use App\Support\Career\MetrikRekrutmen;
use App\Support\Career\PipelineProgress;
use App\Support\CareerShell;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MONITORING REKRUTMEN (read-only, untuk atasan/super admin).
 *
 * Satu halaman berlapis, dari ringkas ke rinci:
 *  1. live()         — angka ringkas + "Perlu Perhatian" + funnel per program.
 *  2. papan()        — papan satu program: siapa berada di tahap mana.
 *  3. stageDetail()  — detail satu tahap (statistik, sub-tes agregat, jadwal).
 *     detail()       — perjalanan penuh satu pelamar.
 *  4. tahapPelamar() — satu tahap milik satu pelamar (berkas, formulir, ujian).
 *
 * Halaman ini SENGAJA tidak menyimpulkan apa pun (tanpa narasi/vonis) —
 * penilaian diserahkan sepenuhnya kepada super admin.
 *
 * Aturan penempatan/badge SAMA PERSIS dengan Worklist: sisi PHP lewat
 * PipelineProgress, sisi SQL lewat ekspresi UrutanDisplay (lihat
 * sqlUrutanDisplay()) yang me-mirror aturan tahapKini() agar hitungan
 * funnel identik dengan papan kanban.
 *
 * Optimasi: jumlah query TETAP per endpoint (tanpa N+1), agregat via
 * GROUP BY di DB, dibungkus DB::transaction agar snapshot konsisten.
 * Setiap respons membawa `checkpoint` (jam server) supaya user tahu
 * data per kapan.
 */
class MonitoringController extends Controller
{
    /** Status program yang boleh jadi filter Live View. */
    private const STATUS_PROGRAM = ['BERJALAN', 'DRAFT', 'SELESAI', 'NONAKTIF'];

    /** Halaman (Inertia). Data TIDAK lewat props — halaman fetch sendiri. */
    public function index()
    {
        return Inertia::render(
            'Career/admin/monitoring/Monitoring',
            CareerShell::props('/karir/monitoring', 'Monitoring Rekrutmen')
        );
    }

    // ═══════════════════════ LIVE VIEW ═══════════════════════

    /**
     * GET /api/v1/karir/monitoring/live?status=BERJALAN|SELESAI|...|ALL
     * KPI global + Perlu Perhatian + funnel per program (satu payload).
     */
    public function live(Request $request)
    {
        try {
            // Default mengirim SELURUH program: penyaringan (status, kategori,
            // alur, penyelenggara, kondisi) dikerjakan di layar agar terasa
            // instan dan angka ringkasan bisa ikut menyesuaikan filter.
            $status = strtoupper(trim((string) $request->query('status', 'ALL')));
            if ($status !== 'ALL' && ! in_array($status, self::STATUS_PROGRAM, true)) {
                $status = 'ALL';
            }

            $data = DB::transaction(function () use ($status) {
                // 1) Program dalam scope + alurnya.
                $programs = DB::table('N_WEB_CAREERS_Program as p')
                    ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                    ->when($status !== 'ALL', fn ($q) => $q->where('p.Status', $status))
                    ->orderBy('p.Nama')
                    ->select('p.Id_Program', 'p.Kode', 'p.Nama', 'p.Kategori', 'p.Warna', 'p.Mode',
                        'p.Status', 'p.Penyelenggara', 'a.Id_Master_Alur', 'a.Nama as Alur_Nama')
                    ->get();

                $programIds = $programs->pluck('Id_Program')->map(fn ($v) => (int) $v)->all();

                // 2) KOLOM FUNNEL = alur yang BENAR-BENAR DIPAKAI lamaran program
                //    itu, bukan penunjuk alur di programnya.
                //
                //    Dulu kolom disusun dari Program.Alur_Kode saja. Begitu admin
                //    mengarahkan program ke alur baru, rombongan yang masih
                //    berjalan di alur lama tergambar di kolom yang bukan miliknya —
                //    angkanya tetap keluar, hanya menempel di tahap yang salah.
                $alurProgram = $programs->pluck('Id_Master_Alur', 'Id_Program')
                    ->map(fn ($v) => $v ? (int) $v : null)->all();
                $alurPerProgram = AlurKolom::alurDipakaiBanyak($programIds, $alurProgram);

                $kolomProgram = [];
                foreach ($programIds as $pid) {
                    $kolomProgram[$pid] = AlurKolom::susun(
                        $alurPerProgram[$pid] ?? [],
                        $alurProgram[$pid] ?? null,
                    );
                }

                // 3) Penempatan pelamar per tahap — SATU statement untuk semua
                //    program, dikelompokkan per KODE tahap (identitas), bukan
                //    per nomor urut.
                $penempatan = collect();
                if ($programIds) {
                    $in = implode(',', $programIds);
                    $penempatan = DB::table(DB::raw('(' . MetrikRekrutmen::sqlUrutanDisplayBerkode("l.Program_Id IN ({$in})") . ') d'))
                        ->groupBy('d.Program_Id', 'd.UrutanDisplay', 'd.KodeDisplay')
                        ->select('d.Program_Id', 'd.UrutanDisplay', 'd.KodeDisplay',
                            DB::raw("SUM(CASE WHEN d.Status = 'BERJALAN' THEN 1 ELSE 0 END) as aktif"),
                            DB::raw("SUM(CASE WHEN d.Status = 'GUGUR' THEN 1 ELSE 0 END) as gugur"),
                            DB::raw("SUM(CASE WHEN d.Status = 'LULUS' THEN 1 ELSE 0 END) as lulus"),
                            DB::raw("SUM(CASE WHEN d.Status = 'TALENT_POOL' THEN 1 ELSE 0 END) as talent"))
                        ->get()
                        ->groupBy('Program_Id');
                }

                // 4) KPI global (scope program terfilter).
                $kpiStatus = $programIds
                    ? DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Program_Id', $programIds)
                        ->groupBy('Status')->select('Status', DB::raw('COUNT(*) as J'))->pluck('J', 'Status')
                    : collect();
                $kpiTahap = $programIds
                    ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                        ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                        ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
                        ->whereIn('l.Program_Id', $programIds)
                        ->selectRaw("SUM(CASE WHEN lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siap,
                                     SUM(CASE WHEN lt.Provider = 'THIRD_PARTY' AND lt.Siap_Diputus = 'N' THEN 1 ELSE 0 END) as nunggu")
                        ->first()
                    : null;

                // 5) Kuota per program (MPP posisi) + kursi terisi (LULUS).
                $kuotaPer = $programIds
                    ? DB::table('N_WEB_CAREERS_Program_Posisi')->whereIn('Program_Id', $programIds)
                        ->groupBy('Program_Id')->select('Program_Id', DB::raw('SUM(Kuota) as J'))->pluck('J', 'Program_Id')
                    : collect();
                $terisiPer = $programIds
                    ? DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Program_Id', $programIds)->where('Status', 'LULUS')
                        ->groupBy('Program_Id')->select('Program_Id', DB::raw('COUNT(*) as J'))->pluck('J', 'Program_Id')
                    : collect();

                $macetHari = MetrikRekrutmen::macetHari();
                $sorotHari = MetrikRekrutmen::sorotHari();
                $maks = (int) config('career_monitoring.perhatian_maks');
                $agingSql = MetrikRekrutmen::sqlAging('lt');

                // 6) SKOR KESEHATAN per program — bahan ring di kartu Overview.
                //    Satu query agregat untuk semua program: berapa yang tersendat
                //    (macet) & berapa keputusan menggantung terlalu lama.
                $sehatPer = MetrikRekrutmen::agregatSehat($programIds);

                // 7) PERLU PERHATIAN: Siap Diputus yang menggantung + tahap macet.
                $perhatian = $programIds
                    ? DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                        ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                        ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                        ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                        ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
                        ->whereIn('l.Program_Id', $programIds)
                        ->whereRaw("(lt.Siap_Diputus = 'Y' OR DATEDIFF(day, COALESCE(lt.Waktu_Mulai, lt.Created_At), GETDATE()) > ?)", [$macetHari])
                        ->selectRaw("l.Id_Lamaran, lt.Id_Lamaran_Tahap, u.Nama as Pelamar, l.Created_By as FallbackNama,
                                     l.Program_Id, p.Nama as ProgramNama, lt.Urutan as TahapUrutan, lt.Label as TahapLabel,
                                     CASE WHEN lt.Siap_Diputus = 'Y' THEN 'SIAP_DIPUTUS'
                                          WHEN lt.Provider = 'THIRD_PARTY' THEN 'MENUNGGU_TES'
                                          ELSE 'MACET' END as Jenis,
                                     {$agingSql} as AgingHari")
                        ->orderByDesc(DB::raw($agingSql))
                        ->limit($maks)
                        ->get()
                    : collect();

                return compact('programs', 'kolomProgram', 'penempatan', 'kpiStatus', 'kpiTahap',
                    'kuotaPer', 'terisiPer', 'perhatian', 'sehatPer', 'macetHari', 'sorotHari');
            });

            // Rakit payload program + funnel (clamp urutan di luar alur ke kolom terakhir).
            $programsOut = $data['programs']->map(function ($p) use ($data) {
                $kolom = collect($data['kolomProgram'][(int) $p->Id_Program] ?? []);
                $counts = collect($data['penempatan']->get($p->Id_Program, []));

                // Angka dipasangkan lewat KODE tahap. Nomor urut hanya cadangan
                // untuk baris pra-mesin yang memang tidak punya Kode — di situ
                // nomor adalah satu-satunya petunjuk yang tersisa.
                $perKode = $counts->filter(fn ($c) => (string) ($c->KodeDisplay ?? '') !== '')->keyBy('KodeDisplay');
                $perUrutan = $counts->filter(fn ($c) => (string) ($c->KodeDisplay ?? '') === '')->keyBy('UrutanDisplay');

                $dipakai = [];
                $tahap = $kolom->map(function ($t) use ($perKode, $perUrutan, &$dipakai) {
                    $c = $perKode->get($t['kode']) ?? $perUrutan->get($t['urutan']);
                    if ($c) {
                        $dipakai[spl_object_id($c)] = true;
                    }

                    return [
                        'urutan' => (int) $t['urutan'],
                        'kode' => $t['kode'],
                        'label' => $t['label'],
                        'provider' => $t['provider'],
                        // Kolom peninggalan alur sebelumnya — layar memberinya
                        // keterangan supaya tidak terbaca sebagai alur yang rusak.
                        'alurLain' => (bool) ($t['alurLain'] ?? false),
                        'aktif' => (int) ($c->aktif ?? 0),
                        'gugur' => (int) ($c->gugur ?? 0),
                        'lulus' => (int) ($c->lulus ?? 0),
                        'talent' => (int) ($c->talent ?? 0),
                    ];
                })->values();

                // ── SISA YANG TAK TERTAMPUNG KOLOM MANA PUN ──
                // Tahap yang alurnya sudah dihapus sama sekali. Dulu mereka
                // "menumpang kolom terakhir" — yang membuat tahap akhir tampak
                // lebih ramai daripada kenyataannya, tepat di angka yang paling
                // sering dilaporkan ke atas. Sekarang dipisah dan disebut.
                $sisa = ['aktif' => 0, 'gugur' => 0, 'lulus' => 0, 'talent' => 0];
                foreach ($counts as $c) {
                    if (isset($dipakai[spl_object_id($c)])) {
                        continue;
                    }
                    foreach ($sisa as $k => $v) {
                        $sisa[$k] += (int) $c->{$k};
                    }
                }

                if (array_sum($sisa) > 0) {
                    $tahap->push([
                        'urutan' => (int) ($kolom->max('urutan') ?? 0) + 1,
                        'kode' => AlurKolom::KODE_LAINNYA,
                        'label' => 'Tahap di luar alur',
                        'provider' => null,
                        'alurLain' => true,
                        'aktif' => $sisa['aktif'],
                        'gugur' => $sisa['gugur'],
                        'lulus' => $sisa['lulus'],
                        'talent' => $sisa['talent'],
                    ]);
                }

                $total = $counts->reduce(fn ($sum, $c) => $sum + (int) $c->aktif + (int) $c->gugur + (int) $c->lulus + (int) $c->talent, 0);

                return [
                    'id' => Hashids::encode($p->Id_Program),
                    'kode' => $p->Kode,
                    'nama' => $p->Nama,
                    'kategori' => $p->Kategori,
                    'warna' => $p->Warna,
                    'status' => $p->Status,
                    'mode' => $p->Mode,
                    'penyelenggara' => $p->Penyelenggara,
                    'alur' => $p->Alur_Nama,
                    'kuota' => (int) ($data['kuotaPer'][$p->Id_Program] ?? 0),
                    'terisi' => (int) ($data['terisiPer'][$p->Id_Program] ?? 0),
                    'tahap' => $tahap,
                    'totalPelamar' => $total,
                    'sehat' => MetrikRekrutmen::skorSehat($data['sehatPer']->get($p->Id_Program), $total),
                ];
            })->values();

            $kpi = [
                'aktif' => (int) ($data['kpiStatus']['BERJALAN'] ?? 0),
                'lulus' => (int) ($data['kpiStatus']['LULUS'] ?? 0),
                'gugur' => (int) ($data['kpiStatus']['GUGUR'] ?? 0),
                'talentPool' => (int) ($data['kpiStatus']['TALENT_POOL'] ?? 0),
                'siapDiputus' => (int) ($data['kpiTahap']->siap ?? 0),
                'menungguTes' => (int) ($data['kpiTahap']->nunggu ?? 0),
            ];

            // programId ikut dikirim supaya klik item langsung membuka Spotlight
            // program terkait + drawer orangnya.
            $perhatian = $data['perhatian']->map(fn ($r) => [
                'lamaranId' => Hashids::encode($r->Id_Lamaran),
                'programId' => $r->Program_Id ? Hashids::encode($r->Program_Id) : null,
                'nama' => $r->Pelamar ?: $r->FallbackNama,
                'programNama' => $r->ProgramNama,
                'tahapUrutan' => (int) $r->TahapUrutan,
                'tahapLabel' => $r->TahapLabel,
                'jenis' => $r->Jenis,
                'agingHari' => max(0, (int) $r->AgingHari),
            ])->values();

            return ResponseHelper::success([
                'checkpoint' => self::checkpoint(),
                'kpi' => $kpi,
                'perhatian' => $perhatian,
                'programs' => $programsOut,
                'meta' => [
                    'macetHari' => $data['macetHari'],
                    'sorotHari' => (int) config('career_monitoring.siap_diputus_sorot_hari'),
                    'statusScope' => $status,
                ],
            ], 'Data monitoring dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat monitoring live: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data monitoring', 500);
        }
    }

    /**
     * GET /api/v1/karir/monitoring/program/{id}/papan
     * PAPAN SPOTLIGHT satu program: kolom tahap alur + SELURUH pelamar beserta
     * penempatannya (siapa ada di tahap mana). Satu payload untuk seluruh papan
     * → tidak ada fetch per kolom, filter per tahap dikerjakan di client.
     */
    public function papan(string $id)
    {
        try {
            $programId = Hashids::decode($id)[0] ?? null;
            if (! $programId) {
                return ResponseHelper::error('Program tidak ditemukan', 404);
            }

            $data = DB::transaction(function () use ($programId) {
                $program = DB::table('N_WEB_CAREERS_Program as p')
                    ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                    ->where('p.Id_Program', $programId)
                    ->select('p.*', 'a.Id_Master_Alur', 'a.Nama as Alur_Nama')
                    ->first();
                if (! $program) {
                    return null;
                }

                // Kolom dari alur yang BENAR-BENAR DIPAKAI lamaran program ini,
                // bukan dari penunjuk alur di programnya — lihat AlurKolom.
                //
                // Disusun SEKALI, lalu dipakai dalam dua bentuk: array (untuk
                // AlurKolom::cocok) dan objek (untuk pemakai lama di bawah yang
                // sudah membaca ->Urutan/->Kode). Satu sumber, dua bentuk —
                // supaya tak ada yang menyusun ulang aturannya sendiri.
                $alurProgramId = $program->Id_Master_Alur ? (int) $program->Id_Master_Alur : null;
                $kolomArray = AlurKolom::susun(
                    AlurKolom::alurDipakai((int) $programId, $alurProgramId),
                    $alurProgramId,
                );
                $kolom = collect($kolomArray)->map(fn ($k) => (object) [
                    'Urutan' => $k['urutan'],
                    'Kode' => $k['kode'],
                    'Label' => $k['label'],
                    'Tipe_Tahap_Kode' => $k['tipe'],
                    'Provider' => $k['provider'],
                    'Alur_Lain' => $k['alurLain'],
                ]);

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

                // Sub-tes per tahap — dipakai PipelineProgress menilai apakah
                // hasil aktivitas sudah tercatat. Satu query bergrup, bukan per
                // pelamar, supaya papan tetap ringan. Tanpa ini badge Monitoring
                // bisa berbeda dari worklist untuk kandidat yang sama.
                $subPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->whereIn('Lamaran_Tahap_Id', $tahapPer->flatten(1)->pluck('Id_Lamaran_Tahap')->all() ?: [0])
                    ->orderBy('Urutan')
                    ->get()
                    ->groupBy('Lamaran_Tahap_Id');

                // Atribut kandidat (kampus, jurusan, jenjang, IPK, …) diambil dari
                // jawaban formulir. Tabel Formulir_Jawaban_Index TIDAK dipakai
                // karena hanya memuat field yang dibutuhkan mesin syarat, bukan
                // seluruh isian.
                $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->whereIn('Lamaran_Id', $lamaran->pluck('Id_Lamaran')->all() ?: [0])
                    ->whereNotNull('Jawaban_Json')
                    ->orderBy('Id_Formulir_Pengisian')
                    ->get(['Lamaran_Id', 'Jawaban_Json']);

                $kuota = (int) DB::table('N_WEB_CAREERS_Program_Posisi')->where('Program_Id', $programId)->sum('Kuota');
                $terisi = (int) DB::table('N_WEB_CAREERS_Lamaran')->where('Program_Id', $programId)->where('Status', 'LULUS')->count();

                // RINCIAN PER POSISI. Satu program (terutama MT) bisa menaungi
                // banyak posisi dengan kuota masing-masing; angka gabungan di
                // atas tidak pernah bisa menjawab "posisi MANA yang sudah penuh".
                // Dua kueri agregat, bukan per-posisi, supaya jumlah kueri tetap.
                $posisiRows = DB::table('N_WEB_CAREERS_Program_Posisi')
                    ->where('Program_Id', $programId)
                    ->orderBy('Id_Program_Posisi')
                    ->get(['Id_Program_Posisi', 'Posisi', 'Departemen', 'Kuota', 'Status']);

                $rekapPosisi = DB::table('N_WEB_CAREERS_Lamaran')
                    ->where('Program_Id', $programId)
                    ->whereNotNull('Program_Posisi_Id')
                    ->groupBy('Program_Posisi_Id')
                    ->selectRaw("Program_Posisi_Id,
                                 SUM(CASE WHEN Status = 'LULUS' THEN 1 ELSE 0 END) as lulus,
                                 SUM(CASE WHEN Status = 'BERJALAN' THEN 1 ELSE 0 END) as berjalan,
                                 COUNT(*) as total")
                    ->get()
                    ->keyBy('Program_Posisi_Id');

                // 'subPer' ikut dibawa: PipelineProgress memerlukannya untuk
                // menilai apakah hasil aktivitas tahap aktif sudah tercatat.
                return compact('program', 'kolom', 'kolomArray', 'lamaran', 'tahapPer', 'subPer', 'pengisian', 'kuota', 'terisi', 'posisiRows', 'rekapPosisi');
            });

            if (! $data) {
                return ResponseHelper::error('Program tidak ditemukan', 404);
            }

            $maxUrutan = (int) ($data['kolom']->max('Urutan') ?? 0);

            // Gabung seluruh isian formulir per lamaran (satu lamaran bisa mengisi
            // beberapa formulir; isian terbaru menimpa yang lama).
            $atributPer = [];
            foreach ($data['pengisian'] as $p) {
                $arr = json_decode($p->Jawaban_Json ?? '', true);
                if (! is_array($arr)) {
                    continue;
                }
                foreach ($arr as $k => $v) {
                    if (is_array($v) || is_null($v) || $v === '') {
                        continue;
                    }
                    $atributPer[$p->Lamaran_Id][$k] = is_bool($v) ? ($v ? 'Ya' : 'Tidak') : $v;
                }
            }

            // Samakan nama field pendidikan versi lama ke nama kanonik, supaya
            // pelamar yang mengisi formulir sebelum penyatuan field tetap ikut
            // tersaring bersama yang baru.
            foreach ($atributPer as $lamaranId => $atr) {
                $atributPer[$lamaranId] = self::samakanKunciPendidikan($atr);
            }

            $pelamar = $data['lamaran']->map(function ($l) use ($data, $maxUrutan, $atributPer) {
                // Jejak seluruh tahap ikut dikirim: mode Full Process memakainya,
                // dan datanya sudah ada di memori — tidak ada query tambahan.
                $tahapList = collect($data['tahapPer']->get($l->Id_Lamaran, []));
                $tk = PipelineProgress::tahapKini($l, $tahapList);
                $tAktif = PipelineProgress::tahapAktif($l, $tahapList);
                $st = PipelineProgress::state($l, $tAktif, $tk, $data['subPer']->get($tAktif->Id_Lamaran_Tahap ?? 0, []));
                $acuan = $tAktif ?? $tk;

                // Penempatan per IDENTITAS tahap — sama persis dengan cara
                // worklist & live() menempatkan kartunya. Nomor urut tetap
                // dikirim untuk urutan tampilan, tapi bukan lagi dasar
                // pencocokan kolom: nomor bisa menunjuk tahap yang lain begitu
                // alur disunting atau program dialihkan.
                $urutan = (int) ($tk->Urutan ?? $l->Urutan_Tahap ?? 1);
                $kolomKode = AlurKolom::cocok($data['kolomArray'], $tk);
                $kolomUrutan = ($maxUrutan > 0 && $urutan > $maxUrutan) ? $maxUrutan : $urutan;

                return [
                    'id' => Hashids::encode($l->Id_Lamaran),
                    'kode' => $l->Kode,
                    'nama' => $l->Pelamar ?: $l->Created_By,
                    'posisi' => $l->Posisi ?: $l->Kategori,
                    'status' => $l->Status,
                    'badge' => PipelineProgress::badge($l, $st, $tAktif),
                    'kolomKode' => $kolomKode,
                    'kolomUrutan' => $kolomUrutan,
                    'tahapLabel' => $tk->Label ?? '—',
                    'luarAlur' => $kolomKode === AlurKolom::KODE_LAINNYA || ($maxUrutan > 0 && $urutan > $maxUrutan),
                    'agingHari' => $acuan ? self::agingHari($acuan->Waktu_Mulai ?? $acuan->Created_At ?? null) : null,
                    'siapDiputus' => (bool) $st['siap'],
                    'nungguSistem' => (bool) $st['nungguSistem'],
                    'skor' => $st['skor'] !== null ? (float) $st['skor'] : null,
                    'dariTalentPool' => ! empty($l->Asal_Talent_Pool_Id),
                    'totalTahap' => count($tahapList),
                    'atribut' => (object) ($atributPer[$l->Id_Lamaran] ?? []),
                    'jejak' => self::jejakTahap($l, $tahapList, $data['kolom']),
                ];
            })->values();

            $p = $data['program'];

            return ResponseHelper::success([
                'checkpoint' => self::checkpoint(),
                'program' => [
                    'id' => Hashids::encode($p->Id_Program),
                    'kode' => $p->Kode,
                    'nama' => $p->Nama,
                    'kategori' => $p->Kategori,
                    'warna' => $p->Warna,
                    'status' => $p->Status,
                    'penyelenggara' => $p->Penyelenggara,
                    'alur' => $p->Alur_Nama,
                    'kuota' => $data['kuota'],
                    'terisi' => $data['terisi'],
                    // Rincian kursi per posisi — dipakai papan untuk menunjukkan
                    // posisi mana yang sudah penuh, bukan cuma total programnya.
                    'posisi' => $data['posisiRows']->map(function ($x) use ($data) {
                        $r = $data['rekapPosisi'][$x->Id_Program_Posisi] ?? null;

                        return [
                            'id' => Hashids::encode($x->Id_Program_Posisi),
                            'nama' => $x->Posisi,
                            'departemen' => $x->Departemen,
                            'status' => $x->Status,
                            'kuota' => (int) $x->Kuota,
                            'terisi' => (int) ($r->lulus ?? 0),
                            'berjalan' => (int) ($r->berjalan ?? 0),
                            'pelamar' => (int) ($r->total ?? 0),
                        ];
                    })->values(),
                ],
                'kolom' => $data['kolom']->map(fn ($t) => [
                    'urutan' => (int) $t->Urutan,
                    'kode' => $t->Kode,
                    'label' => $t->Label,
                    'tipe' => $t->Tipe_Tahap_Kode,
                    'provider' => $t->Provider,
                    // Kolom peninggalan alur sebelumnya — dibedakan di layar
                    // agar tidak terbaca sebagai alur yang rusak.
                    'alurLain' => (bool) $t->Alur_Lain,
                ])->values(),
                'pelamar' => $pelamar,
                'filterAtribut' => self::filterDariAtribut($atributPer),
            ], 'Papan program dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat papan program {$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat papan program', 500);
        }
    }

    /**
     * GET /api/v1/karir/monitoring/program/{id}/tahap/{urutan}/detail
     * Detail SATU tahap: statistik, rapor sub-tes agregat, dan sesi penjadwalan
     * yang menyangkut tahap itu. Daftar orang tidak diulang di sini — client
     * memfilternya dari payload papan (hemat query & konsisten).
     */
    public function stageDetail(Request $request, string $id, int $urutan)
    {
        try {
            $programId = Hashids::decode($id)[0] ?? null;
            if (! $programId || $urutan < 1) {
                return ResponseHelper::error('Parameter tidak valid', 422);
            }

            $macetHari = (int) config('career_monitoring.macet_hari');
            $agingSql = MetrikRekrutmen::sqlUmurTahap('lt');

            // IDENTITAS TAHAP, bila layar mengirimkannya.
            //
            // Nomor di URL adalah nomor KOLOM papan, sedangkan kueri di bawah
            // mencarinya di Lamaran_Tahap dengan penomoran KANDIDAT. Selama
            // kolom disusun dari gabungan beberapa alur — atau program pernah
            // dialihkan — kedua penomoran itu tidak lagi sama, dan panel ini
            // akan menghitung statistik tahap yang bukan yang diklik.
            //
            // Kode menutupnya. Nomor tetap jadi cadangan untuk baris lama yang
            // memang tak punya Kode.
            $kode = trim((string) $request->query('kode', '')) ?: null;
            $sempit = fn ($q) => $kode
                ? $q->where('lt.Kode', $kode)
                : $q->where('lt.Urutan', $urutan);

            $data = DB::transaction(function () use ($programId, $urutan, $agingSql, $macetHari, $kode, $sempit) {
                // 1) Statistik tahap (semua lamaran yang PERNAH menyentuh tahap ini).
                $stats = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                    ->where('l.Program_Id', $programId)->where($sempit)
                    ->selectRaw("COUNT(*) as total,
                                 SUM(CASE WHEN lt.Status = 'BERJALAN' THEN 1 ELSE 0 END) as aktif,
                                 SUM(CASE WHEN lt.Status = 'MENUNGGU' THEN 1 ELSE 0 END) as menunggu,
                                 SUM(CASE WHEN lt.Hasil = 'LULUS' THEN 1 ELSE 0 END) as lulus,
                                 SUM(CASE WHEN lt.Hasil = 'GUGUR' THEN 1 ELSE 0 END) as gugur,
                                 SUM(CASE WHEN lt.Hasil = 'TALENT_POOL' THEN 1 ELSE 0 END) as talent,
                                 SUM(CASE WHEN lt.Status = 'BERJALAN' AND lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siapDiputus,
                                 SUM(CASE WHEN lt.Status = 'BERJALAN' AND {$agingSql} > {$macetHari} THEN 1 ELSE 0 END) as macet,
                                 AVG(CASE WHEN lt.Status = 'BERJALAN' THEN {$agingSql} * 1.0 END) as avgAging,
                                 MAX(CASE WHEN lt.Status = 'BERJALAN' THEN {$agingSql} END) as maxAging,
                                 AVG(CASE WHEN lt.Skor IS NOT NULL THEN lt.Skor END) as avgSkor")
                    ->first();

                // 2) Rapor sub-tes agregat per jenis tes pada tahap ini.
                $subtes = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as tt')
                    ->join('N_WEB_CAREERS_Lamaran_Tahap as lt', 'lt.Id_Lamaran_Tahap', '=', 'tt.Lamaran_Tahap_Id')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                    ->where('l.Program_Id', $programId)->where($sempit)
                    ->groupByRaw('tt.Label, tt.Peran, tt.Provider')
                    ->selectRaw("tt.Label as Label, tt.Peran, tt.Provider,
                                 COUNT(*) as jml,
                                 SUM(CASE WHEN tt.Status = 'SELESAI' THEN 1 ELSE 0 END) as selesai,
                                 SUM(CASE WHEN tt.Status = 'TIDAK_HADIR' THEN 1 ELSE 0 END) as tidakHadir,
                                 SUM(CASE WHEN tt.Status IN ('BELUM', 'DIJADWALKAN') THEN 1 ELSE 0 END) as belum,
                                 SUM(CASE WHEN tt.Hasil = 'LULUS' THEN 1 ELSE 0 END) as hasilLulus,
                                 SUM(CASE WHEN tt.Hasil = 'GAGAL' THEN 1 ELSE 0 END) as hasilGagal,
                                 AVG(tt.Nilai) as avgNilai, MIN(tt.Nilai) as minNilai, MAX(tt.Nilai) as maxNilai")
                    ->orderBy('Label')
                    ->get();

                // 3) Siapa yang belum menuntaskan sub-tes (bahan tindak lanjut).
                $belumSubmit = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes as tt')
                    ->join('N_WEB_CAREERS_Lamaran_Tahap as lt', 'lt.Id_Lamaran_Tahap', '=', 'tt.Lamaran_Tahap_Id')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                    ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                    ->where('l.Program_Id', $programId)->where($sempit)
                    ->where('l.Status', 'BERJALAN')
                    ->whereIn('tt.Status', ['BELUM', 'DIJADWALKAN', 'TIDAK_HADIR'])
                    ->orderBy('u.Nama')
                    ->limit(30)
                    ->selectRaw('l.Id_Lamaran, u.Nama as Pelamar, l.Created_By as FallbackNama,
                                 tt.Label as TesLabel, tt.Status')
                    ->get();

                // 4) Sesi penjadwalan yang dipakai tahap ini + jumlah pesertanya.
                $jadwalIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                    ->where('l.Program_Id', $programId)->where($sempit)
                    ->whereNotNull('lt.Penjadwalan_Tahap_Id')
                    ->distinct()->pluck('lt.Penjadwalan_Tahap_Id')->all();

                $jadwal = $jadwalIds
                    ? DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                        ->leftJoin('N_WEB_CAREERS_Penjadwalan as p', 'p.Id_Penjadwalan', '=', 'pt.Penjadwalan_Id')
                        ->whereIn('pt.Id_Penjadwalan_Tahap', $jadwalIds)
                        ->orderByDesc('pt.Waktu_Mulai')
                        ->get(['pt.Id_Penjadwalan_Tahap', 'pt.Label', 'pt.Provider', 'pt.Nama_Ujian',
                            'pt.Waktu_Mulai', 'pt.Waktu_Akhir', 'pt.Durasi_Menit', 'pt.Ambang_Batas_Nilai',
                            'pt.Status as StatusTahap', 'p.Nama as PenjadwalanNama', 'p.Kode as PenjadwalanKode',
                            'p.Status as PenjadwalanStatus', 'p.Tanggal_Mulai', 'p.Tanggal_Selesai'])
                    : collect();

                $pesertaPer = $jadwalIds
                    ? DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                        ->whereIn('Penjadwalan_Tahap_Id', $jadwalIds)
                        ->groupBy('Penjadwalan_Tahap_Id')
                        ->selectRaw("Penjadwalan_Tahap_Id,
                                     COUNT(*) as jml,
                                     SUM(CASE WHEN Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai")
                        ->get()->keyBy('Penjadwalan_Tahap_Id')
                    : collect();

                return compact('stats', 'subtes', 'belumSubmit', 'jadwal', 'pesertaPer');
            });

            $s = $data['stats'];

            return ResponseHelper::success([
                'checkpoint' => self::checkpoint(),
                'stats' => [
                    'total' => (int) ($s->total ?? 0),
                    'aktif' => (int) ($s->aktif ?? 0),
                    'menunggu' => (int) ($s->menunggu ?? 0),
                    'lulus' => (int) ($s->lulus ?? 0),
                    'gugur' => (int) ($s->gugur ?? 0),
                    'talent' => (int) ($s->talent ?? 0),
                    'siapDiputus' => (int) ($s->siapDiputus ?? 0),
                    'macet' => (int) ($s->macet ?? 0),
                    'avgAging' => $s && $s->avgAging !== null ? round((float) $s->avgAging, 1) : null,
                    'maxAging' => $s && $s->maxAging !== null ? (int) $s->maxAging : null,
                    'avgSkor' => $s && $s->avgSkor !== null ? round((float) $s->avgSkor, 1) : null,
                    'konversi' => ($s && ($s->lulus + $s->gugur) > 0)
                        ? round($s->lulus * 100 / ($s->lulus + $s->gugur))
                        : null,
                ],
                'subtes' => $data['subtes']->map(fn ($t) => [
                    'label' => $t->Label,
                    'peran' => $t->Peran,
                    'provider' => $t->Provider,
                    'jml' => (int) $t->jml,
                    'selesai' => (int) $t->selesai,
                    'belum' => (int) $t->belum,
                    'tidakHadir' => (int) $t->tidakHadir,
                    'hasilLulus' => (int) $t->hasilLulus,
                    'hasilGagal' => (int) $t->hasilGagal,
                    'avgNilai' => $t->avgNilai !== null ? round((float) $t->avgNilai, 1) : null,
                    'minNilai' => $t->minNilai !== null ? round((float) $t->minNilai, 1) : null,
                    'maxNilai' => $t->maxNilai !== null ? round((float) $t->maxNilai, 1) : null,
                ])->values(),
                'belumSubmit' => $data['belumSubmit']->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Lamaran),
                    'nama' => $r->Pelamar ?: $r->FallbackNama,
                    'tes' => $r->TesLabel,
                    'status' => $r->Status,
                ])->values(),
                'jadwal' => $data['jadwal']->map(function ($j) use ($data) {
                    $ps = $data['pesertaPer']->get($j->Id_Penjadwalan_Tahap);

                    return [
                        'nama' => $j->PenjadwalanNama,
                        'kode' => $j->PenjadwalanKode,
                        'label' => $j->Label,
                        'namaUjian' => $j->Nama_Ujian,
                        'provider' => $j->Provider,
                        'status' => $j->StatusTahap,
                        'statusPenjadwalan' => $j->PenjadwalanStatus,
                        'waktuMulai' => self::fmt($j->Waktu_Mulai),
                        'waktuAkhir' => self::fmt($j->Waktu_Akhir),
                        'tanggalMulai' => self::fmt($j->Tanggal_Mulai),
                        'tanggalSelesai' => self::fmt($j->Tanggal_Selesai),
                        'durasiMenit' => $j->Durasi_Menit ? (int) $j->Durasi_Menit : null,
                        'ambangNilai' => $j->Ambang_Batas_Nilai !== null ? (float) $j->Ambang_Batas_Nilai : null,
                        'peserta' => (int) ($ps->jml ?? 0),
                        'pesertaSelesai' => (int) ($ps->selesai ?? 0),
                    ];
                })->values(),
                'meta' => ['macetHari' => $macetHari],
            ], 'Detail tahap dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail tahap {$urutan} program {$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail tahap', 500);
        }
    }

    /**
     * GET /api/v1/karir/monitoring/pelamar/{id} — detail perjalanan penuh
     * satu lamaran (expand baris): tahap + sub-tes + jejak + talent pool.
     */
    public function detail(string $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Lamaran tidak ditemukan', 404);
            }

            // Perilaku tipe dari master — tak ada kode tipe yang ditulis di sini.
            $tipeTahap = LamaranController::masterTipeTahap();

            $data = DB::transaction(function () use ($realId) {
                $l = DB::table('N_WEB_CAREERS_Lamaran as l')
                    ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                    ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                    ->leftJoin('N_WEB_CAREERS_Program_Posisi as x', 'x.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                    ->where('l.Id_Lamaran', $realId)
                    ->select('l.*', 'u.Nama as Pelamar', 'u.Email', 'p.Nama as ProgramNama', 'x.Posisi')
                    ->first();
                if (! $l) {
                    return null;
                }

                $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $realId)->orderBy('Urutan')->get();

                $tests = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->whereIn('Lamaran_Tahap_Id', $tahap->pluck('Id_Lamaran_Tahap')->all() ?: [0])
                    ->orderBy('Urutan')->get()->groupBy('Lamaran_Tahap_Id');

                $jejak = DB::table('N_WEB_CAREERS_Lamaran_Keputusan_Jejak')
                    ->where('Lamaran_Id', $realId)->orderBy('Created_At')->orderBy('Id_Lamaran_Keputusan_Jejak')->get();

                // Talent pool: catatan saat lamaran INI dimasukkan pool (1 hop, tanpa rekursi).
                $pool = DB::table('N_WEB_CAREERS_Talent_Pool')
                    ->where('Lamaran_Id', $realId)->orderByDesc('Id_Talent_Pool')->first();
                $ditarikKeKode = ($pool && $pool->Ditarik_Ke_Lamaran_Id)
                    ? DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $pool->Ditarik_Ke_Lamaran_Id)->value('Kode')
                    : null;

                // Asal: lamaran ini lahir dari penarikan talent pool program lain.
                $asal = $l->Asal_Talent_Pool_Id
                    ? DB::table('N_WEB_CAREERS_Talent_Pool')->where('Id_Talent_Pool', $l->Asal_Talent_Pool_Id)->first()
                    : null;

                return compact('l', 'tahap', 'tests', 'jejak', 'pool', 'ditarikKeKode', 'asal');
            });

            if (! $data) {
                return ResponseHelper::error('Lamaran tidak ditemukan', 404);
            }

            $l = $data['l'];
            $tahapOut = $data['tahap']->map(fn ($t) => [
                'urutan' => (int) $t->Urutan,
                'label' => $t->Label,
                'tipe' => $t->Tipe_Tahap_Kode,
                'provider' => $t->Provider,
                'status' => $t->Status,
                'hasil' => $t->Hasil,
                'skor' => $t->Skor !== null ? (float) $t->Skor : null,
                'catatan' => $t->Catatan,
                'waktuMulai' => self::fmt($t->Waktu_Mulai),
                'waktuSelesai' => self::fmt($t->Waktu_Selesai),
                'diputusBy' => $t->Diputus_By,
                'diputusAt' => self::fmt($t->Diputus_At),
                'rekomendasi' => $t->Rekomendasi,
                'rekomendasiAlasan' => $t->Rekomendasi_Alasan,
                'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                'tests' => collect($data['tests']->get($t->Id_Lamaran_Tahap, []))->map(fn ($x) => [
                    'label' => $x->Label,
                    // Tipe MILIK AKTIVITAS: satu tahap bisa mencampur ujian online,
                    // tes manual, dan wawancara — admin harus bisa membedakannya.
                    'tipe' => $x->Tipe_Tahap_Kode,
                    'tipeNama' => $tipeTahap[$x->Tipe_Tahap_Kode]->Nama ?? null,
                    'provider' => $x->Provider,
                    'peran' => $x->Peran,
                    'wajib' => $x->Wajib === 'Y',
                    'status' => $x->Status,
                    'hasil' => $x->Hasil,
                    'nilai' => $x->Nilai !== null ? (float) $x->Nilai : null,
                    'waktuSelesai' => self::fmt($x->Waktu_Selesai),
                    'catatan' => $x->Catatan,
                ])->values(),
            ])->values();

            return ResponseHelper::success([
                'checkpoint' => self::checkpoint(),
                'lamaran' => [
                    'id' => Hashids::encode($l->Id_Lamaran),
                    'kode' => $l->Kode,
                    'nama' => $l->Pelamar ?: $l->Created_By,
                    'email' => $l->Email ?? null,
                    'programNama' => $l->ProgramNama,
                    'posisi' => $l->Posisi ?: $l->Kategori,
                    'status' => $l->Status,
                    'hasilAkhir' => $l->Hasil_Akhir,
                    'gugurDiTahap' => $l->Gugur_Di_Tahap,
                    'alasanGugur' => $l->Alasan_Gugur,
                    'waktuLamar' => self::fmt($l->Waktu_Lamar ?? $l->Created_At),
                    'waktuSelesai' => self::fmt($l->Waktu_Selesai),
                    'mulaiDariUrutan' => $l->Mulai_Dari_Urutan ? (int) $l->Mulai_Dari_Urutan : null,
                ],
                'tahap' => $tahapOut,
                'jejak' => $data['jejak']->map(fn ($j) => [
                    'verdict' => $j->Verdict,
                    'mode' => $j->Mode_Kode,
                    'ringkasan' => $j->Ringkasan,
                    'oleh' => $j->Created_By,
                    'pada' => self::fmt($j->Created_At),
                ])->values(),
                'talentPool' => $data['pool'] ? [
                    'status' => $data['pool']->Status,
                    'tahapAsal' => $data['pool']->Tahap_Asal,
                    'tag' => $data['pool']->Tag,
                    'catatan' => $data['pool']->Catatan,
                    'tanggalMasuk' => self::fmt($data['pool']->Tanggal_Masuk),
                    'tanggalKedaluwarsa' => self::fmt($data['pool']->Tanggal_Kedaluwarsa),
                    'ditarikKeKode' => $data['ditarikKeKode'],
                    'ditarikAt' => self::fmt($data['pool']->Ditarik_At),
                ] : null,
                'asalTalentPool' => $data['asal'] ? [
                    'programNama' => $data['asal']->Program_Nama,
                    'posisi' => $data['asal']->Posisi,
                    'tahapAsal' => $data['asal']->Tahap_Asal,
                    'tanggalMasuk' => self::fmt($data['asal']->Tanggal_Masuk),
                ] : null,
            ], 'Detail pelamar dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat detail monitoring {$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail pelamar', 500);
        }
    }

    // ═══════════════════ DETAIL SATU TAHAP MILIK SATU PELAMAR ═══════════════════

    /**
     * GET /api/v1/karir/monitoring/pelamar/{id}/tahap/{urutan}
     * Isi satu tahap untuk satu pelamar — sumber offcanvas tumpukan ke-3.
     *
     * Blok yang dikirim MUNCUL SESUAI DATA, bukan dari daftar kode tipe yang
     * ditulis di sini: yang menentukan isi tahap adalah Provider / Formulir_Kode
     * / Flag_Upload_Hasil, dan perilaku tiap tipe dibaca dari Master Tipe Tahap
     * (Perilaku_Kode, Flag_Formulir, Flag_Upload_Hasil). Dengan begitu tipe
     * kustom buatan admin tetap tampil benar tanpa menyentuh file ini.
     *
     * Perlu diingat: TIPE MELEKAT PADA AKTIVITAS, bukan pada tahap. Satu tahap
     * bisa berisi ujian online + tes manual + wawancara sekaligus, jadi rapor
     * tes menampilkan tipe tiap aktivitas, bukan tipe tahapnya.
     *
     * Tahap yang BELUM dijalani (baris Lamaran_Tahap belum ada) tetap dapat
     * dibuka: yang dikirim blok `rencana` dari template alur.
     */
    public function tahapPelamar(string $id, int $urutan)
    {
        try {
            $lamaranId = Hashids::decode($id)[0] ?? null;
            if (! $lamaranId || $urutan < 1) {
                return ResponseHelper::error('Parameter tidak valid', 422);
            }

            // Perilaku tipe dari master — tak ada kode tipe yang ditulis di sini.
            $tipeTahap = LamaranController::masterTipeTahap();

            $data = DB::transaction(function () use ($lamaranId, $urutan) {
                $l = DB::table('N_WEB_CAREERS_Lamaran as l')
                    ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                    ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                    ->where('l.Id_Lamaran', $lamaranId)
                    ->select('l.*', 'u.Nama as Pelamar', 'p.Nama as ProgramNama')
                    ->first();
                if (! $l) {
                    return null;
                }

                $t = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->where('Lamaran_Id', $lamaranId)->where('Urutan', $urutan)->first();

                // Template tahap — dipakai untuk ikon/label, dan jadi isi utama
                // saat tahap belum dijalani.
                $master = $l->Master_Alur_Id
                    ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                        ->where('Master_Alur_Id', $l->Master_Alur_Id)->where('Urutan', $urutan)->first()
                    : null;
                $tipeKode = $t->Tipe_Tahap_Kode ?? $master->Tipe_Tahap_Kode ?? null;
                $tipe = $tipeKode
                    ? DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->where('Kode', $tipeKode)
                        ->first(['Kode', 'Nama', 'Ikon', 'Deskripsi'])
                    : null;
                $rencanaTes = $master
                    ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                        ->where('Master_Alur_Tahap_Id', $master->Id_Master_Alur_Tahap)
                        ->orderBy('Urutan')->get()
                    : collect();

                if (! $t) {
                    return compact('l', 't', 'master', 'tipe', 'rencanaTes')
                        + ['berkas' => collect(), 'pengisian' => null, 'dokumen' => collect(),
                            'subtes' => collect(), 'ujian' => collect(), 'jejak' => collect()];
                }

                // Berkas hasil tahap (MCU/interview) yang diunggah admin.
                $berkas = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                    ->where('Lamaran_Tahap_Id', $t->Id_Lamaran_Tahap)
                    ->orderByDesc('Id_Lamaran_Tahap_Berkas')->get();

                // Formulir yang diisi kandidat pada tahap ini + dokumennya.
                $pengisian = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                    ->when($t->Formulir_Pengisian_Id,
                        fn ($q) => $q->where('Id_Formulir_Pengisian', $t->Formulir_Pengisian_Id),
                        fn ($q) => $q->where('Lamaran_Tahap_Id', $t->Id_Lamaran_Tahap))
                    ->first();
                $dokumen = $pengisian
                    ? DB::table('N_WEB_CAREERS_Formulir_Berkas')
                        ->where('Formulir_Pengisian_Id', $pengisian->Id_Formulir_Pengisian)
                        ->orderBy('Urutan')->get()
                    : collect();

                $subtes = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                    ->where('Lamaran_Tahap_Id', $t->Id_Lamaran_Tahap)->orderBy('Urutan')->get();

                // Data ujian CAT per peserta — dikunci DUA kunci sekaligus
                // (Penjadwalan_Tahap_Id + Lamaran_Id) karena masing-masing
                // sendirian tidak unik.
                $jadwalIds = collect([$t->Penjadwalan_Tahap_Id])
                    ->merge($subtes->pluck('Penjadwalan_Tahap_Id'))
                    ->filter()->unique()->values()->all();
                $ujian = $jadwalIds
                    ? DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
                        ->leftJoin('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'pp.Penjadwalan_Tahap_Id')
                        ->leftJoin('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pp.Penjadwalan_Id')
                        ->whereIn('pp.Penjadwalan_Tahap_Id', $jadwalIds)
                        ->where('pp.Lamaran_Id', $lamaranId)
                        ->get(['pp.*', 'pt.Label as TahapLabel', 'pt.Nama_Ujian as UjianNama',
                            'pt.Waktu_Mulai as JadwalMulai', 'pt.Waktu_Akhir as JadwalAkhir',
                            'pt.Durasi_Menit', 'pj.Nama as PenjadwalanNama'])
                    : collect();

                $jejak = DB::table('N_WEB_CAREERS_Lamaran_Keputusan_Jejak')
                    ->where('Lamaran_Tahap_Id', $t->Id_Lamaran_Tahap)
                    ->orderBy('Created_At')->orderBy('Id_Lamaran_Keputusan_Jejak')->get();

                return compact('l', 't', 'master', 'tipe', 'rencanaTes',
                    'berkas', 'pengisian', 'dokumen', 'subtes', 'ujian', 'jejak');
            });

            if (! $data) {
                return ResponseHelper::error('Lamaran tidak ditemukan', 404);
            }

            $l = $data['l'];
            $t = $data['t'];
            $m = $data['master'];

            return ResponseHelper::success([
                'checkpoint' => self::checkpoint(),
                'pelamar' => [
                    'id' => Hashids::encode($l->Id_Lamaran),
                    'nama' => $l->Pelamar ?: $l->Created_By,
                    'kode' => $l->Kode,
                    'programNama' => $l->ProgramNama,
                ],
                'tahap' => [
                    'urutan' => $urutan,
                    'label' => $t->Label ?? $m->Label ?? "Tahap {$urutan}",
                    'tipeKode' => $data['tipe']->Kode ?? null,
                    'tipeNama' => $data['tipe']->Nama ?? null,
                    'tipeIkon' => $data['tipe']->Ikon ?? null,
                    'tipeDeskripsi' => $data['tipe']->Deskripsi ?? null,
                    'provider' => $t->Provider ?? $m->Provider ?? null,
                    'sudahDijalani' => (bool) $t,
                ],
                // Blok keputusan — null bila tahap belum dijalani.
                'keputusan' => $t ? [
                    'status' => $t->Status,
                    'hasil' => $t->Hasil,
                    'skor' => $t->Skor !== null ? (float) $t->Skor : null,
                    'catatan' => $t->Catatan,
                    'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                    'modeKeputusan' => $t->Keputusan_Mode,
                    'rekomendasi' => $t->Rekomendasi,
                    'rekomendasiAlasan' => $t->Rekomendasi_Alasan,
                    'rekomendasiAt' => self::fmt($t->Rekomendasi_At),
                    'diputusBy' => $t->Diputus_By,
                    'diputusAt' => self::fmt($t->Diputus_At),
                    'waktuMulai' => self::fmt($t->Waktu_Mulai),
                    'waktuSelesai' => self::fmt($t->Waktu_Selesai),
                    'modePengumuman' => $t->Mode_Pengumuman,
                    'tanggalPengumuman' => self::fmt($t->Tanggal_Pengumuman),
                    'waktuDiumumkan' => self::fmt($t->Waktu_Diumumkan),
                ] : null,
                'berkasHasil' => $data['berkas']->map(fn ($b) => [
                    'nama' => $b->Nama_File,
                    'ext' => $b->Ext,
                    'ukuran' => (int) $b->Ukuran,
                    'mime' => $b->Mime,
                    'diunggahOleh' => $b->Created_By,
                    'diunggahAt' => self::fmt($b->Created_At),
                    'url' => route('career.api.monitoring.berkas.tahap', [
                        'lamaran' => Hashids::encode($l->Id_Lamaran),
                        'berkas' => Hashids::encode($b->Id_Lamaran_Tahap_Berkas),
                    ]),
                ])->values(),
                'formulir' => $data['pengisian'] ? [
                    'kode' => $data['pengisian']->Kode,
                    'status' => $data['pengisian']->Status,
                    'waktuKirim' => self::fmt($data['pengisian']->Waktu_Kirim),
                    'jawaban' => self::uraikanJawaban($data['pengisian']->Jawaban_Json),
                    'dokumen' => $data['dokumen']->map(fn ($b) => [
                        'field' => self::labelDariKey($b->Field_Key),
                        'nama' => $b->Nama_Asli,
                        'ext' => $b->Ekstensi,
                        'ukuran' => (int) $b->Ukuran_Byte,
                        'mime' => $b->Mime,
                        'verifikasi' => $b->Status_Verifikasi,
                        'url' => route('career.api.monitoring.berkas.formulir', [
                            'lamaran' => Hashids::encode($l->Id_Lamaran),
                            'berkas' => Hashids::encode($b->Id_Formulir_Berkas),
                        ]),
                    ])->values(),
                ] : null,
                'subtes' => $data['subtes']->map(fn ($x) => [
                    'label' => $x->Label,
                    'tipe' => $x->Tipe_Tahap_Kode,
                    'tipeNama' => $tipeTahap[$x->Tipe_Tahap_Kode]->Nama ?? null,
                    'provider' => $x->Provider,
                    'peran' => $x->Peran,
                    'wajib' => $x->Wajib === 'Y',
                    'status' => $x->Status,
                    'hasil' => $x->Hasil,
                    'nilai' => $x->Nilai !== null ? (float) $x->Nilai : null,
                    'ambang' => $x->Ambang_Dipakai !== null ? (int) $x->Ambang_Dipakai : null,
                    'totalSoal' => $x->Total_Soal !== null ? (int) $x->Total_Soal : null,
                    'percobaan' => (int) $x->Percobaan,
                    'waktuSelesai' => self::fmt($x->Waktu_Selesai),
                    'catatan' => $x->Catatan,
                ])->values(),
                // Rekam jejak pengerjaan ujian CAT — kolom ini sebelumnya tidak
                // pernah ditampilkan di mana pun.
                'ujian' => $data['ujian']->map(fn ($p) => [
                    'ujianNama' => $p->UjianNama ?: $p->TahapLabel,
                    'penjadwalan' => $p->PenjadwalanNama,
                    'jadwalMulai' => self::fmt($p->JadwalMulai),
                    'jadwalAkhir' => self::fmt($p->JadwalAkhir),
                    'durasiMenit' => $p->Durasi_Menit ? (int) $p->Durasi_Menit : null,
                    'statusKirim' => $p->Status_Kirim,
                    'statusPengerjaan' => $p->Status_Pengerjaan,
                    'selesai' => ($p->Flag_Selesai ?? 'T') === 'Y',
                    'mulaiAkses' => self::fmt($p->Waktu_Mulai_Akses),
                    'selesaiAkses' => self::fmt($p->Waktu_Selesai_Akses),
                    'totalSoal' => $p->Total_Soal !== null ? (int) $p->Total_Soal : null,
                    'totalNilai' => $p->Total_Nilai !== null ? (float) $p->Total_Nilai : null,
                    'ambang' => $p->Ambang_Batas_Nilai !== null ? (float) $p->Ambang_Batas_Nilai : null,
                    'kelulusan' => $p->Status_Kelulusan,
                    'rincian' => self::uraikanSummary($p->Summary_Json),
                ])->values(),
                'jejak' => $data['jejak']->map(fn ($j) => [
                    'verdict' => $j->Verdict,
                    'mode' => $j->Mode_Kode,
                    'ringkasan' => $j->Ringkasan,
                    'oleh' => $j->Created_By,
                    'pada' => self::fmt($j->Created_At),
                ])->values(),
                // Blok "apa yang akan dijalani" — berguna untuk tahap yang
                // belum dimulai, sekaligus pembanding untuk yang sudah jalan.
                'rencana' => $m ? [
                    'label' => $m->Label,
                    'provider' => $m->Provider,
                    'formulirKode' => $m->Formulir_Kode,
                    'modeKeputusan' => $m->Mode_Keputusan_Kode,
                    'modePengumuman' => $m->Mode_Pengumuman,
                    'sla' => $m->SLA,
                    // Berbasis berkas: dari flag tahap ATAU dari tipe yang memang
                    // berkas (Master Tipe Tahap) — dulu kode 'MCU' ditulis di sini.
                    'uploadHasil' => ($m->Flag_Upload_Hasil ?? 'T') === 'Y'
                        || ($tipeTahap[$m->Tipe_Tahap_Kode]->Flag_Upload_Hasil ?? 'T') === 'Y',
                    'wajibUpload' => ($m->Flag_Wajib_Upload ?? 'T') === 'Y',
                    'talentPool' => ($m->Flag_Talent_Pool ?? 'T') === 'Y',
                    'tes' => $data['rencanaTes']->map(fn ($x) => [
                        'label' => $x->Label,
                        'tipe' => $x->Tipe_Tahap_Kode,
                        'tipeNama' => $tipeTahap[$x->Tipe_Tahap_Kode]->Nama ?? null,
                        'provider' => $x->Provider,
                        'peran' => $x->Peran,
                        'wajib' => $x->Wajib === 'Y',
                        'ambang' => $x->Ambang_Batas !== null ? (int) $x->Ambang_Batas : null,
                    ])->values(),
                ] : null,
            ], 'Detail tahap pelamar dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat tahap {$urutan} lamaran {$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memuat detail tahap', 500);
        }
    }

    /**
     * Sajikan berkas hasil tahap (signed URL GCS 15 menit).
     * Berbeda dari endpoint worklist: di sini kepemilikan DIVERIFIKASI —
     * berkas harus benar milik lamaran yang diminta, sehingga hashid berkas
     * milik lamaran lain tidak bisa dipakai menembus.
     */
    public function berkasTahapFile(string $lamaran, string $berkas)
    {
        return $this->sajikanBerkas($lamaran, $berkas, 'tahap');
    }

    /** Sajikan dokumen unggahan kandidat pada formulir tahap. */
    public function berkasFormulirFile(string $lamaran, string $berkas)
    {
        return $this->sajikanBerkas($lamaran, $berkas, 'formulir');
    }

    // ═══════════════════════ HELPERS ═══════════════════════

    /** Pencarian berkas + gerbang kepemilikan + signed URL, dipakai dua route file. */
    private function sajikanBerkas(string $lamaran, string $berkas, string $jenis)
    {
        $lamaranId = Hashids::decode($lamaran)[0] ?? null;
        $berkasId = Hashids::decode($berkas)[0] ?? null;
        if (! $lamaranId || ! $berkasId) {
            abort(404);
        }

        if ($jenis === 'tahap') {
            $b = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Berkas')
                ->where('Id_Lamaran_Tahap_Berkas', $berkasId)
                ->where('Lamaran_Id', $lamaranId)   // gerbang kepemilikan
                ->first(['Path_File', 'Mime']);
        } else {
            $b = DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
                ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
                ->where('fb.Id_Formulir_Berkas', $berkasId)
                ->where('fp.Lamaran_Id', $lamaranId) // gerbang kepemilikan
                ->first(['fb.Path_File', 'fb.Mime']);
        }

        if (! $b || ! $b->Path_File) {
            abort(404);
        }

        try {
            $gcs = Storage::disk(GcsBerkas::DISK);
            if ($gcs->exists($b->Path_File)) {
                return redirect()->away($gcs->temporaryUrl($b->Path_File, now()->addMinutes(15)));
            }
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("Signed URL berkas {$jenis} gagal: " . $e->getMessage());
        }

        // Fallback lokal (data lama / dev tanpa GCS) — pola sama dengan worklist.
        foreach ([storage_path('app/' . $b->Path_File), public_path($b->Path_File), $b->Path_File] as $kandidat) {
            if ($kandidat && is_file($kandidat)) {
                return response()->file($kandidat, ['Content-Type' => $b->Mime ?: 'application/octet-stream']);
            }
        }

        abort(404, 'File tidak ditemukan di penyimpanan.');
    }

    /**
     * Jawaban_Json → daftar {label, nilai} siap tampil. Skema formulir hidup
     * di frontend, jadi label dikarang dari key (pola sama dengan worklist).
     */
    private static function uraikanJawaban(?string $json): array
    {
        $arr = $json ? json_decode($json, true) : null;
        if (! is_array($arr)) {
            return [];
        }

        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v) && array_is_list($v) === false) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_array($v)) {
                $v = implode(', ', $v);
            } elseif (is_bool($v)) {
                $v = $v ? 'Ya' : 'Tidak';
            }
            $out[] = ['label' => self::labelDariKey((string) $k), 'nilai' => $v === '' ? null : $v];
        }

        return $out;
    }

    /** Summary_Json hasil CAT → daftar {label, nilai} bila bentuknya dikenali. */
    private static function uraikanSummary(?string $json): array
    {
        $arr = $json ? json_decode($json, true) : null;
        if (! is_array($arr)) {
            return [];
        }

        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $v = $v ? 'Ya' : 'Tidak';
            }
            $out[] = ['label' => self::labelDariKey((string) $k), 'nilai' => $v];
        }

        return $out;
    }

    /**
     * Singkatan yang sering dipakai di key formulir → tulisan manusiawi.
     * Tanpa ini label tampil mentah seperti "Jkel" / "StatusMhs" / "Ipk".
     */
    private const KAMUS_LABEL = [
        'jkel' => 'Jenis Kelamin',
        'gender' => 'Jenis Kelamin',
        'hp' => 'No. HP',
        'nohp' => 'No. HP',
        'telp' => 'Telepon',
        'ipk' => 'IPK',
        'mhs' => 'Mahasiswa',
        'prodi' => 'Program Studi',
        'univ' => 'Universitas',
        'tgl' => 'Tanggal',
        'thn' => 'Tahun',
        'cv' => 'CV',
        'ktp' => 'KTP',
        'npwp' => 'NPWP',
        'sim' => 'SIM',
        'nik' => 'NIK',
        'sk' => 'SK',
        'url' => 'URL',
        'id' => 'ID',
    ];

    /** camelCase / snake_case / kebab-case → "Judul Berspasi" yang enak dibaca. */
    private static function labelDariKey(string $key): string
    {
        // Pecah camelCase lebih dulu ("statusMhs" → "status Mhs"), lalu pemisah lain.
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $key);
        $kata = preg_split('/[\s_\-]+/', trim((string) $s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function ($w) {
            $k = mb_strtolower($w);

            return self::KAMUS_LABEL[$k] ?? ucfirst($k);
        }, $kata));
    }

    // sqlUrutanDisplay() DIPINDAH ke App\Support\Career\MetrikRekrutmen.
    // Alasannya: Dashboard butuh hitungan penempatan yang SAMA PERSIS dengan
    // funnel di sini. Selama ekspresinya privat di kelas ini, satu-satunya cara
    // memakainya adalah menyalin — dan dua salinan pasti berbeda begitu salah
    // satunya diperbaiki. Aturan badge/penempatan tetap satu: PipelineProgress
    // di sisi PHP, MetrikRekrutmen::sqlUrutanDisplay() di sisi SQL.

    /**
     * FILTER PENDIDIKAN — kelompok yang SELALU ditawarkan bila datanya ada.
     *
     * Bedanya dengan filter turunan otomatis di bawah: yang ini tidak dibuang
     * hanya karena pilihannya cuma satu, dan tidak dibatasi 40 opsi. Alasannya
     * beda kebutuhan — atasan memang mencari "siapa dari kampus mana", jadi
     * daftar 300 kampus tetap berguna asal kolomnya bisa dicari, dan
     * "semua pelamar S1" adalah informasi, bukan filter rusak.
     *
     * `alias` menampung nama key dari formulir versi lama supaya pengisian
     * yang tersimpan sebelum penyatuan field tetap ikut tersaring.
     */
    private const FILTER_PENDIDIKAN = [
        'jenjang_pendidikan' => [
            'label' => 'Jenjang Pendidikan',
            'ikon' => 'bi-mortarboard',
            'alias' => ['jenjang', 'jenjang_politeknik', 'jenjang_universitas'],
        ],
        'jenis_institusi' => [
            'label' => 'Jenis Institusi',
            'ikon' => 'bi-building',
            'alias' => ['institusi', 'jenis_institusi_pendidikan'],
        ],
        'nama_kampus' => [
            'label' => 'Kampus / Sekolah Asal',
            'ikon' => 'bi-bank',
            'cari' => true,
            'alias' => ['perguruan_tinggi', 'kampus', 'asal_sekolah'],
        ],
        'jurusan' => [
            'label' => 'Jurusan / Program Studi',
            'ikon' => 'bi-journal-bookmark',
            'cari' => true,
            'alias' => ['program_studi', 'prodi', 'jurusan_gabungan'],
        ],
        'fakultas' => [
            'label' => 'Fakultas',
            'ikon' => 'bi-diagram-3',
            'cari' => true,
            'alias' => [],
        ],
        'status_kemahasiswaan' => [
            'label' => 'Status Pendidikan',
            'ikon' => 'bi-person-badge',
            'alias' => ['status_mhs', 'status_mahasiswa', 'status_pendidikan'],
        ],
        'tahun_lulus' => [
            'label' => 'Tahun Lulus',
            'ikon' => 'bi-calendar-check',
            'alias' => ['tahun_kelulusan'],
        ],
    ];

    /** Batas opsi: kelompok pendidikan longgar (dropdown-nya bisa dicari). */
    private const MAKS_OPSI_PENDIDIKAN = 300;

    private const MAKS_OPSI_UMUM = 40;

    /**
     * Isi key kanonik pendidikan dari nama lama bila key barunya tidak ada.
     *
     * Tanpa ini, lamaran yang mengisi formulir versi lama tidak akan pernah kena
     * filter jenjang/kampus/jurusan — padahal datanya ada, cuma beda nama.
     * Nilai asli tidak dihapus; hanya ditambahi key kanoniknya.
     *
     * Pencocokan mengabaikan gaya penulisan, karena formulir lama bercampur
     * camelCase dan snake_case: "statusMhs", "status_mhs", dan "Status Mhs"
     * ketiganya dianggap satu nama yang sama.
     */
    private static function samakanKunciPendidikan(array $atr): array
    {
        $polos = [];
        foreach ($atr as $k => $v) {
            $polos[self::polosKunci($k)] = $v;
        }

        foreach (self::FILTER_PENDIDIKAN as $kanonik => $def) {
            if (isset($atr[$kanonik]) && trim((string) $atr[$kanonik]) !== '') {
                continue;
            }
            foreach (array_merge([$kanonik], $def['alias']) as $calon) {
                $p = self::polosKunci($calon);
                if (isset($polos[$p]) && trim((string) $polos[$p]) !== '') {
                    $atr[$kanonik] = $polos[$p];
                    break;
                }
            }
        }

        return $atr;
    }

    /** "statusMhs" / "Status_Mhs" / "status mhs" → "statusmhs". */
    private static function polosKunci(string $k): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($k));
    }

    /** Field isian yang tidak berguna sebagai penyaring (identitas/berkas). */
    private const BUKAN_FILTER = [
        'nama', 'namalengkap', 'email', 'hp', 'nohp', 'telp', 'telepon', 'wa', 'whatsapp',
        'alamat', 'nik', 'ktp', 'npwp', 'foto', 'fotoverifikasi', 'ttd', 'tandatangan',
        'lahir', 'tgllahir', 'tanggallahir', 'linkedin', 'portofolio', 'catatan', 'alasan',
    ];

    /**
     * Tentukan filter apa yang PANTAS ditawarkan, diturunkan dari isian formulir
     * yang benar-benar ada. Dengan begitu program MT (kampus/jurusan/IPK) dan
     * program rekrutmen (pengalaman/domisili) otomatis dapat filter yang sesuai,
     * tanpa perlu daftar field yang di-hardcode per jenis program.
     *
     * Dua kelompok, dengan aturan berbeda karena kebutuhannya memang berbeda:
     *
     *  PENDIDIKAN (lihat FILTER_PENDIDIKAN) — selalu tampil selama datanya ada,
     *  urutannya mengikuti alur pertanyaan (jenjang → institusi → kampus →
     *  jurusan → fakultas → status → tahun lulus), batas opsi longgar.
     *
     *  LAINNYA — diturunkan otomatis dari isian apa pun yang tersisa, sehingga
     *  program rekrutmen (pengalaman, domisili) dan magang (skema, durasi)
     *  ikut dapat filter tanpa daftar yang di-hardcode. Di sini penyaringannya
     *  ketat: identitas dibuang, paragraf dibuang, butuh ≥2 nilai berbeda
     *  (satu pilihan tidak menyaring apa pun), maksimal 40 opsi.
     */
    private static function filterDariAtribut(array $atributPer): array
    {
        $nilaiPer = [];
        foreach ($atributPer as $atr) {
            foreach ($atr as $k => $v) {
                $polos = mb_strtolower(str_replace(['_', '-'], '', $k));
                $pendidikan = isset(self::FILTER_PENDIDIKAN[$k]);
                if (! $pendidikan && in_array($polos, self::BUKAN_FILTER, true)) {
                    continue;
                }
                $s = trim((string) $v);
                if ($s === '' || mb_strlen($s) > ($pendidikan ? 150 : 60)) {
                    continue;
                }
                $nilaiPer[$k][] = $s;
            }
        }

        $pendidikan = [];
        $lain = [];

        foreach ($nilaiPer as $key => $nilai) {
            $def = self::FILTER_PENDIDIKAN[$key] ?? null;
            $unik = array_values(array_unique($nilai));

            $minUnik = $def ? 1 : 2;
            $maksUnik = $def ? self::MAKS_OPSI_PENDIDIKAN : self::MAKS_OPSI_UMUM;
            if (count($unik) < $minUnik || count($unik) > $maksUnik) {
                continue;
            }

            $angka = count(array_filter($unik, fn ($v) => is_numeric($v))) === count($unik);
            if ($angka) {
                sort($unik, SORT_NUMERIC);
            } else {
                sort($unik, SORT_NATURAL | SORT_FLAG_CASE);
            }

            $baris = [
                'key' => $key,
                'label' => $def['label'] ?? self::labelDariKey($key),
                'tipe' => $angka ? 'angka' : 'teks',
                'grup' => $def ? 'pendidikan' : 'lain',
                'ikon' => $def['ikon'] ?? null,
                // Kolom yang isinya bisa ratusan (kampus, jurusan) wajib bisa
                // dicari; yang pendek justru terganggu kalau diberi kotak ketik.
                'cari' => (bool) ($def['cari'] ?? false) || count($unik) > 8,
                'opsi' => $unik,
            ];

            if ($def) {
                $pendidikan[$key] = $baris;
            } else {
                $lain[] = $baris;
            }
        }

        // Pendidikan mengikuti urutan pertanyaannya, bukan abjad — atasan
        // membacanya sebagai rantai, dari yang paling umum ke paling khusus.
        $urut = [];
        foreach (array_keys(self::FILTER_PENDIDIKAN) as $key) {
            if (isset($pendidikan[$key])) {
                $urut[] = $pendidikan[$key];
            }
        }

        usort($lain, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return array_merge($urut, $lain);
    }

    // skorSehat() + agregatnya DIPINDAH ke App\Support\Career\MetrikRekrutmen
    // (bersama sqlUrutanDisplay & sqlAging) supaya Dashboard menilai kesehatan
    // program dengan rumus yang sama, bukan rumus keduanya.

    /**
     * JEJAK SATU PELAMAR di seluruh tahap alur — bahan mode "Full Process",
     * yang menampilkan satu baris penuh per orang alih-alih satu kartu di
     * kolom tempatnya berada sekarang.
     *
     * KEADAAN SEL. Yang paling penting di sini: tahap sesudah orang gugur
     * BUKAN "gagal". Di database tahap itu tetap MENUNGGU dengan Hasil NULL —
     * memang tidak pernah dijalani. Mengecatnya merah akan membuat papan
     * mengarang kegagalan yang tidak pernah terjadi, dan atasan membaca
     * "gagal 4 kali" padahal gugurnya sekali. Karena itu ada keadaan
     * TIDAK_DIJALANI yang terpisah dari BELUM (akan dijalani, gilirannya
     * belum tiba).
     *
     * LAMA DI TAHAP. Tahap yang sedang berjalan memakai perhitungan yang
     * PERSIS SAMA dengan chip aging di mode live — supaya satu orang tidak
     * punya dua angka berbeda untuk hal yang sama di layar yang sama.
     * Tahap yang sudah selesai dihitung dari saat masuk sampai diputus;
     * saat masuk diambil dari Waktu_Mulai, dan bila kosong dari Waktu_Selesai
     * tahap sebelumnya — karena naik tahap memang tidak menulis Waktu_Mulai
     * (lihat LamaranService::tetapkanTahap). Bila keduanya tidak ada,
     * durasinya null: lebih baik kosong daripada angka karangan.
     *
     * @param  \Illuminate\Support\Collection  $tahapList  tahap milik lamaran ini, urut
     * @param  \Illuminate\Support\Collection  $kolom      tahap alur (acuan kolom papan)
     */
    private static function jejakTahap(object $l, $tahapList, $kolom): array
    {
        $selesaiLamaran = in_array($l->Status, ['GUGUR', 'TALENT_POOL', 'LULUS'], true);
        $perUrutan = $tahapList->keyBy('Urutan');
        // Peta per KODE — inilah pasangan yang sebenarnya. Nomor urut hanya
        // cadangan untuk baris lama yang memang tak punya Kode. Tanpa ini, satu
        // kolom "Wawancara User" bisa menampilkan perjalanan "Psikotes" milik
        // kandidat yang alurnya berbeda, dan matriksnya terbaca meyakinkan
        // justru karena setiap selnya terisi.
        $perKode = $tahapList->filter(fn ($t) => (string) ($t->Kode ?? '') !== '')->keyBy('Kode');

        $out = [];
        $selesaiSebelumnya = null;

        foreach ($kolom as $k) {
            $urutan = (int) $k->Urutan;
            $t = $perKode->get($k->Kode) ?? $perUrutan->get($urutan);

            if (! $t) {
                // Alur berubah setelah lamaran dibuat — tahap ini tidak dimiliki
                // pelamar tsb. Ditandai apa adanya, bukan disembunyikan.
                $out[] = [
                    'urutan' => $urutan, 'kode' => $k->Kode, 'label' => $k->Label, 'keadaan' => 'TIDAK_ADA',
                    'hari' => null, 'skor' => null, 'siapDiputus' => false,
                    'mulai' => null, 'selesai' => null, 'catatan' => null,
                ];
                continue;
            }

            $hasil = $t->Hasil ?: null;
            $keadaan = match (true) {
                $hasil === 'LULUS' => 'LULUS',
                $hasil === 'GUGUR' => 'GUGUR',
                $hasil === 'TALENT_POOL' => 'TALENT',
                $t->Status === 'BERJALAN' => 'SEKARANG',
                $t->Status === 'SELESAI' => 'SELESAI',
                $selesaiLamaran => 'TIDAK_DIJALANI',
                default => 'BELUM',
            };

            $mulaiEfektif = $t->Waktu_Mulai ?: $selesaiSebelumnya;

            $hari = match ($keadaan) {
                'SEKARANG' => self::agingHari($t->Waktu_Mulai ?? $t->Created_At ?? null),
                'LULUS', 'GUGUR', 'TALENT', 'SELESAI' => self::rentangHari($mulaiEfektif, $t->Waktu_Selesai),
                default => null,
            };

            $out[] = [
                // NOMOR MILIK KANDIDAT, bukan nomor kolomnya.
                //
                // Sel ini bisa diklik, dan yang dikirim ke
                // /monitoring/pelamar/{id}/tahap/{urutan} adalah angka ini —
                // dicari lagi di Lamaran_Tahap dengan penomoran KANDIDAT.
                // Selama barisnya dicocokkan lewat Kode, nomor kolom dan nomor
                // kandidat bisa berbeda (alur baru menyisipkan tahap di tengah,
                // atau alur lama punya urutan lain). Mengirim nomor kolom
                // membuat drawer membuka tahap yang bukan itu — rapi, terisi,
                // dan salah orang.
                'urutan' => (int) ($t->Urutan ?? $urutan),
                // Kode kolom — dipakai layar sebagai kunci baris matriks.
                // Nomor tidak lagi aman jadi kunci: setelah dicocokkan per
                // Kode, dua kolom bisa jatuh ke tahap kandidat yang sama dan
                // Vue menemukan dua kunci kembar dalam satu baris.
                'kode' => $k->Kode,
                'label' => $t->Label ?: $k->Label,
                'keadaan' => $keadaan,
                'hari' => $hari,
                'skor' => $t->Skor !== null ? (float) $t->Skor : null,
                'siapDiputus' => ($t->Siap_Diputus ?? 'N') === 'Y',
                'mulai' => self::fmt($mulaiEfektif),
                'selesai' => self::fmt($t->Waktu_Selesai),
                'catatan' => $t->Catatan ?: null,
            ];

            if ($t->Waktu_Selesai) {
                $selesaiSebelumnya = $t->Waktu_Selesai;
            }
        }

        return $out;
    }

    /** Selisih hari antara dua waktu; null bila salah satunya tidak ada. */
    private static function rentangHari($dari, $sampai): ?int
    {
        if (! $dari || ! $sampai) {
            return null;
        }
        try {
            return max(0, Carbon::parse($dari)->startOfDay()->diffInDays(Carbon::parse($sampai)->startOfDay(), false));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Umur (hari) sejak $sejak — negatif dianggap 0; null bila tak ada data. */
    private static function agingHari($sejak): ?int
    {
        if (! $sejak) {
            return null;
        }
        try {
            return max(0, Carbon::parse($sejak)->startOfDay()->diffInDays(now()->startOfDay(), false));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Format datetime DB → 'Y-m-d H:i' (frontend yang mempercantik). */
    private static function fmt($val): ?string
    {
        if (! $val) {
            return null;
        }
        try {
            return Carbon::parse($val)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Jam server = checkpoint data pada setiap respons. */
    private static function checkpoint(): array
    {
        $now = now();

        return ['iso' => $now->toIso8601String(), 'jam' => $now->format('H:i:s')];
    }
}
