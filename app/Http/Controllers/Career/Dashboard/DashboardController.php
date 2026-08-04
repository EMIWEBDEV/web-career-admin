<?php

namespace App\Http\Controllers\Career\Dashboard;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\AksesService;
use App\Support\Career\AlurKolom;
use App\Support\Career\MetrikRekrutmen;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — DASHBOARD ADMIN, satu halaman bertab per KATEGORI program
 * (Rekrutmen / Magang / MT).
 *
 * TAB = HAK AKSES, bukan menu terpisah. Kategori yang boleh dilihat seorang
 * admin datang dari N_WEB_CAREERS_Role_Konten_Access lewat
 * AksesService::kategoriDiizinkan('dashboardPage'). Admin yang dicentang dua
 * kategori melihat dua tab; kosong berarti TIDAK DIBATASI (aturan yang sama
 * dipakai Program Kegiatan & Pembukaan, dan ditulis di layar /hak-akses).
 *
 * Kenapa gagal-terbuka: /karir adalah halaman beranda admin. Kalau kategori
 * kosong diartikan "tidak boleh apa-apa", setiap admin baru akan mendarat di
 * halaman kosong sebelum Hak Akses-nya diisi. Karena itu route /karir SENGAJA
 * tidak dipasangi career.permission — yang dibatasi adalah ISI tabnya, dan
 * batas itu ditegakkan di setiap endpoint di bawah (bukan hanya disembunyikan
 * di layar, supaya kategori terlarang tidak bisa diintip lewat query string).
 *
 * PAYLOAD DIPECAH TIGA supaya halaman terbit cepat dan tiap seksi punya
 * keadaan muat/galat/kosongnya sendiri — bukan satu respons raksasa yang
 * membuat seluruh layar menunggu kueri terlambat:
 *   ringkas()  — KPI + antrean "Butuh Aksi"  (di atas lipatan, dimuat pertama)
 *   analitik() — funnel, tren, kesehatan program, kuota, agenda
 *   khas()     — seksi khusus kategori + empat panel ekstra
 *
 * Setiap respons dibungkus DB::transaction (potret konsisten, pola
 * MonitoringController::live) dan membawa `checkpoint` jam server.
 *
 * Hitungan pipeline TIDAK ditulis ulang di sini: penempatan, aging, dan skor
 * kesehatan dipinjam dari App\Support\Career\MetrikRekrutmen supaya angka di
 * dashboard identik dengan Monitoring dan Worklist.
 */
class DashboardController extends Controller
{
    /** Key hak akses halaman ini (baris N_WEB_CAREERS_Menu). */
    private const PAGE = 'dashboardPage';

    /** Periode yang sah untuk widget bertren. 'all' juga diterima. */
    private const PERIODE_SAH = [7, 30, 90];

    /** Urutan tab sesuai cara admin menyebutnya: Rekrutmen → Magang → MT. */
    private const URUTAN_TAB = ['REKRUTMEN' => 1, 'INTERNSHIP' => 2, 'MT' => 3];

    /** Rupa tab per kategori. Kategori lain (mis. OUTSOURCING) dapat bawaan. */
    private const RUPA_TAB = [
        'REKRUTMEN' => ['ikon' => 'bi bi-briefcase-fill', 'warna' => '#6366f1'],
        'INTERNSHIP' => ['ikon' => 'bi bi-journal-bookmark-fill', 'warna' => '#059669'],
        'MT' => ['ikon' => 'bi bi-mortarboard-fill', 'warna' => '#d97706'],
    ];

    /** Batas baris tiap keranjang antrean aksi (jumlah sebenarnya tetap dilaporkan). */
    private const AKSI_MAKS = 25;

    /**
     * Kata kerja per tipe tahap untuk keranjang "Menunggu Tindakan Kamu".
     *
     * Nama & ikon tahap diambil dari Master_Tipe_Tahap (jadi tipe baru langsung
     * tampil benar); yang tidak bisa diturunkan dari master hanyalah KATA
     * KERJANYA — "Tes Online" tidak memberi tahu admin bahwa yang kurang adalah
     * jadwalnya. Kode yang tidak terdaftar di sini jatuh ke kalimat umum, jadi
     * tipe tahap baru tetap muncul, hanya dengan ajakan yang lebih datar.
     */
    private const AKSI_TIPE = [
        'HCLEARN_TEST' => 'Jadwalkan tes online',
        'TES_OFFLINE_MANUAL' => 'Jadwalkan tes offline',
        'INTERVIEW' => 'Atur & catat wawancara',
        'MCU' => 'Jadwalkan MCU',
        'OFFERING' => 'Kirim penawaran',
        'ADMIN_SCREENING' => 'Screening berkas',
        'DOCUMENT' => 'Verifikasi dokumen',
        'FORM' => 'Verifikasi isian',
    ];

    // ═══════════════════════════ HALAMAN ═══════════════════════════

    /**
     * GET /karir — kerangka halaman. Data TIDAK lewat props: halaman menembak
     * tiga endpoint di bawah sendiri, supaya pindah tab & muat ulang berkala
     * tidak perlu memuat ulang seluruh halaman Inertia.
     */
    public function index()
    {
        $tabs = $this->tabs();

        return Inertia::render('Career/admin/dashboard/Dashboard', CareerShell::props('/karir', 'Dashboard Web Career', [
            'tabs' => $tabs,
            'tabAwal' => $tabs[0]['kode'] ?? null,
            'ambang' => [
                'macetHari' => MetrikRekrutmen::macetHari(),
                'sorotHari' => MetrikRekrutmen::sorotHari(),
            ],
            'checkpoint' => MetrikRekrutmen::checkpoint(),
        ]));
    }

    // ═══════════════════════ ZONA A + B — RINGKAS ═══════════════════════

    /**
     * GET /api/v1/karir/dashboard/ringkas?kategori=..&periode=..
     * KPI + antrean "Butuh Aksi Kamu".
     */
    public function ringkas(Request $request)
    {
        [$kategori, $galat] = $this->kategoriDiminta($request);
        if ($galat) {
            return $galat;
        }

        try {
            $periode = $this->periode($request);

            $data = DB::transaction(function () use ($kategori, $periode) {
                $programs = $this->programs($kategori);
                $ids = $programs->pluck('Id_Program')->map(fn ($v) => (int) $v)->all();

                return [
                    'kpi' => $this->kpi($ids, $periode),
                    'aksi' => $this->antreanAksi($kategori, $ids),
                    'programAktif' => $programs->where('Status', 'BERJALAN')->count(),
                    'programTotal' => $programs->count(),
                ];
            });

            return ResponseHelper::success($data + [
                'periode' => $periode['hari'],
                'checkpoint' => MetrikRekrutmen::checkpoint(),
            ], 'Ringkasan dashboard');
        } catch (\Throwable $e) {
            return $this->gagal('ringkas', $kategori, $e);
        }
    }

    /**
     * KPI — HANYA ANGKA. Label, ikon, warna, dan tujuan klik tiap kartu
     * ditentukan di layar.
     *
     * Ini pelajaran dari dashboard lama: controller mengirim label/nilai/ikon,
     * template membaca value/icon/trend, dan tidak ada satu pun yang menyalak —
     * kartunya cuma tampil kosong berbulan-bulan. Kontrak jadi sesempit
     * mungkin: satu peta angka. Kalau kuncinya salah, layar langsung terlihat
     * salah, bukan diam-diam blank.
     */
    private function kpi(array $ids, array $periode): array
    {
        if (! $ids) {
            return ['aktif' => 0, 'lulus' => 0, 'gugur' => 0, 'talent' => 0, 'siap' => 0,
                'nungguTes' => 0, 'macet' => 0, 'baru' => 0, 'baruSebelum' => null, 'total' => 0,
                'ditahan' => 0, 'pascaPenerimaan' => 0];
        }

        $status = DB::table('N_WEB_CAREERS_Lamaran')
            ->whereIn('Program_Id', $ids)
            ->groupBy('Status')
            ->select('Status', DB::raw('COUNT(*) as J'))
            ->pluck('J', 'Status');

        $umur = MetrikRekrutmen::sqlUmurTahap('lt');
        $macetHari = MetrikRekrutmen::macetHari();

        $tahap = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $ids)
            // NULL-safe: `lt.Hold_Flag <> 'Y'` telanjang membuat baris ber-
            // Hold_Flag NULL ikut terkecualikan (NULL <> 'Y' = NULL, bukan TRUE).
            ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
            ->selectRaw("SUM(CASE WHEN lt.Siap_Diputus = 'Y' THEN 1 ELSE 0 END) as siap,
                         SUM(CASE WHEN lt.Provider = 'THIRD_PARTY' AND lt.Siap_Diputus = 'N' THEN 1 ELSE 0 END) as nungguTes,
                         SUM(CASE WHEN lt.Siap_Diputus <> 'Y' AND {$umur} > {$macetHari} THEN 1 ELSE 0 END) as macet")
            ->first();

        // DITAHAN — kandidat yang sengaja tidak diikutkan antrean aksi apa pun
        // di atas. Semantik "ADALAH ditahan" (= 'Y'), bukan exclusion, jadi
        // TIDAK perlu COALESCE: baris Hold_Flag NULL memang benar tidak boleh
        // terhitung ke sini.
        $ditahan = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->where('lt.Hold_Flag', 'Y')
            ->whereIn('l.Program_Id', $ids)
            ->count();

        // PROSES ADMINISTRASI — kandidat sudah LULUS tapi masih ada tahap
        // administratif BERJALAN (kontrak, onboarding). Tidak menyentuh
        // Hold_Flag sama sekali: kandidat pasca-penerimaan bukan bagian dari
        // seleksi yang bisa "ditahan" di alur ini.
        $pascaPenerimaan = (int) DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('l.Status', 'LULUS')->where('lt.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $ids)
            ->distinct('l.Id_Lamaran')->count('l.Id_Lamaran');

        // Waktu_Lamar bisa kosong pada baris lama → COALESCE ke Created_At,
        // kalau tidak lamaran itu hilang dari hitungan "baru" tanpa jejak.
        $kolom = 'COALESCE(l.Waktu_Lamar, l.Created_At)';
        $total = (int) $status->sum();

        if ($periode['dari'] === null) {
            $baru = $total;         // periode "semua" → yang baru = seluruhnya
            $sebelum = null;        // tidak ada pembanding, jadi tanpa chip tren
        } else {
            $baru = (int) DB::table('N_WEB_CAREERS_Lamaran as l')->whereIn('l.Program_Id', $ids)
                ->whereRaw("{$kolom} >= ?", [$periode['dari']])->count();
            $sebelum = (int) DB::table('N_WEB_CAREERS_Lamaran as l')->whereIn('l.Program_Id', $ids)
                ->whereRaw("{$kolom} >= ? AND {$kolom} < ?", [$periode['dariSebelum'], $periode['dari']])->count();
        }

        return [
            'aktif' => (int) ($status['BERJALAN'] ?? 0),
            'lulus' => (int) ($status['LULUS'] ?? 0),
            'gugur' => (int) ($status['GUGUR'] ?? 0),
            'talent' => (int) ($status['TALENT_POOL'] ?? 0),
            'siap' => (int) ($tahap->siap ?? 0),
            'nungguTes' => (int) ($tahap->nungguTes ?? 0),
            'macet' => (int) ($tahap->macet ?? 0),
            'baru' => $baru,
            'baruSebelum' => $sebelum,
            'total' => $total,
            'ditahan' => $ditahan,
            'pascaPenerimaan' => $pascaPenerimaan,
        ];
    }

    /**
     * ANTREAN AKSI — enam keranjang, diurut dari yang paling jelas salah kita.
     *
     * Tiap keranjang melaporkan `total` sebenarnya di samping baris yang
     * dikirim, supaya pemotongan di AKSI_MAKS terlihat ("25 dari 61") dan tidak
     * terbaca sebagai "cuma ada 25".
     *
     * TIDAK ADA baris yang muncul di dua keranjang. Urutan klaimnya:
     * siap diputus → giliran admin → menunggu tes → macet. Kalau dibiarkan
     * tumpang tindih, lencana ringkasan di kepala seksi akan menghitung orang
     * yang sama dua kali dan angkanya berhenti bisa dipercaya.
     */
    private function antreanAksi(string $kategori, array $ids): array
    {
        $kosong = ['baris' => [], 'total' => 0];
        $hasil = ['tindakanAdmin' => $kosong + ['ringkas' => []], 'keputusan' => $kosong,
            'macet' => $kosong, 'menungguTes' => $kosong,
            'tutupSegera' => $kosong, 'gagalLamar' => $kosong, 'pascaPenerimaan' => $kosong];

        $umur = MetrikRekrutmen::sqlUmurTahap('lt');
        $aging = MetrikRekrutmen::sqlAging('lt');
        $macetHari = MetrikRekrutmen::macetHari();
        $giliran = MetrikRekrutmen::sqlGiliranAdmin('lt', 'mtt');

        if ($ids) {
            $hasil['tindakanAdmin'] = $this->tindakanAdmin($kategori, $ids);

            // Satu kueri untuk tiga keranjang berikutnya; pemilahan per Jenis
            // dilakukan di PHP agar DB tidak dipanggil tiga kali untuk hal sama.
            //
            // MENUNGGU_TES kini berarti "sudah dijadwalkan, tinggal menunggu
            // penyedia" — bukan lagi sekadar Provider = THIRD_PARTY. Yang belum
            // dijadwalkan sama sekali dikeluarkan oleh NOT(giliran) di bawah dan
            // masuk keranjang tindakanAdmin, karena itu justru pekerjaan admin,
            // bukan hal yang di luar kendalinya. Sebelum ini keduanya tercampur
            // di bawah label "di luar kendali admin" — dan yang belum
            // terjadwal tidak pernah ada yang mengerjakan.
            $jenisSql = "CASE WHEN lt.Siap_Diputus = 'Y' THEN 'SIAP_DIPUTUS'
                              WHEN lt.Provider = 'THIRD_PARTY' THEN 'MENUNGGU_TES'
                              ELSE 'MACET' END";

            $dasar = fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
                ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Tipe_Tahap as mtt', 'mtt.Kode', '=', 'lt.Tipe_Tahap_Kode')
                ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
                ->whereIn('l.Program_Id', $ids)
                // NULL-safe: `lt.Hold_Flag <> 'Y'` telanjang membuat baris
                // ber-Hold_Flag NULL ikut terkecualikan (NULL <> 'Y' = NULL).
                ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
                // Sudah diklaim keranjang tindakanAdmin (yang tidak memakai
                // ambang umur, jadi selalu superset dari irisan ini).
                ->whereRaw("(lt.Siap_Diputus = 'Y' OR NOT {$giliran})")
                ->whereRaw("(lt.Siap_Diputus = 'Y' OR {$umur} > ?)", [$macetHari]);

            // Jumlah SEBENARNYA per keranjang (tanpa limit) — dasar label "N dari M".
            $totalPer = $dasar()->groupByRaw($jenisSql)
                ->selectRaw("{$jenisSql} as Jenis, COUNT(*) as J")
                ->pluck('J', 'Jenis');

            $baris = $dasar()
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->selectRaw("l.Id_Lamaran, l.Program_Id, u.Nama as Pelamar, l.Created_By as FallbackNama,
                             p.Nama as ProgramNama, pos.Posisi as PosisiNama,
                             lt.Urutan as TahapUrutan, lt.Label as TahapLabel,
                             {$jenisSql} as Jenis, {$aging} as AgingHari")
                ->orderByDesc(DB::raw($aging))
                ->limit(self::AKSI_MAKS * 3)
                ->get();

            $peta = ['SIAP_DIPUTUS' => 'keputusan', 'MACET' => 'macet', 'MENUNGGU_TES' => 'menungguTes'];
            foreach ($peta as $jenis => $kunci) {
                $isi = $baris->where('Jenis', $jenis)->take(self::AKSI_MAKS)->values();
                $hasil[$kunci] = [
                    'total' => (int) ($totalPer[$jenis] ?? 0),
                    'baris' => $isi->map(fn ($r) => [
                        'id' => Hashids::encode((int) $r->Id_Lamaran),
                        'nama' => $r->Pelamar ?: ($r->FallbackNama ?: 'Tanpa nama'),
                        'program' => $r->ProgramNama,
                        'posisi' => $r->PosisiNama,
                        'tahap' => trim(($r->TahapUrutan ? $r->TahapUrutan . '. ' : '') . ($r->TahapLabel ?: '—')),
                        'umurHari' => max(0, (int) $r->AgingHari),
                        'tautan' => $this->tautanWorklist($kategori, $r->ProgramNama),
                    ])->all(),
                ];
            }
        }

        $hasil['tutupSegera'] = $this->pembukaanTutupSegera($kategori, $ids);
        $hasil['gagalLamar'] = $this->lamaranGagalMasuk($kategori);
        $hasil['pascaPenerimaan'] = $ids ? $this->pascaPenerimaan($kategori, $ids) : $kosong;

        return $hasil;
    }

    /**
     * MENUNGGU TINDAKAN KAMU — kandidat yang berhenti bukan karena prosesnya,
     * melainkan karena belum ada admin yang mengerjakan bagiannya.
     *
     * Ini keranjang yang paling mudah luput: kandidat di tahap Psikotes yang
     * jadwalnya belum dibuat, atau di tahap Offering yang suratnya belum
     * dikirim, TIDAK terlihat di halaman mana pun sebelum ini. Dia bukan
     * "macet" (ambang macet baru menyala setelah 7 hari) dan bukan "menunggu
     * tes" (tidak ada tes yang sedang berjalan) — dia hanya diam sampai ada
     * yang sadar. Halaman kandidatnya bahkan sudah menulis "Tim rekrutmen
     * sedang menyiapkan jadwal ujianmu" (Master_Tipe_Tahap.Pesan_Kandidat),
     * jadi janjinya sudah terucap ke kandidat sebelum ada yang menagihnya ke
     * admin.
     *
     * TANPA AMBANG UMUR — sengaja. Keranjang lain baru muncul setelah lewat
     * ambang macet; yang ini muncul sejak hari pertama, karena tujuannya
     * mencegah tahap jadi macet, bukan melaporkan setelah terlanjur.
     *
     * `ringkas` adalah daftar "apa saja yang perlu dilakukan" per jenis
     * pekerjaan — itu yang dibaca dulu oleh admin, sebelum turun ke nama orang.
     */
    private function tindakanAdmin(string $kategori, array $ids): array
    {
        $umur = MetrikRekrutmen::sqlUmurTahap('lt');
        $giliran = MetrikRekrutmen::sqlGiliranAdmin('lt', 'mtt');

        $dasar = fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->leftJoin('N_WEB_CAREERS_Master_Tipe_Tahap as mtt', 'mtt.Kode', '=', 'lt.Tipe_Tahap_Kode')
            ->where('lt.Status', 'BERJALAN')->where('l.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $ids)
            // NULL-safe: `lt.Hold_Flag <> 'Y'` telanjang membuat baris
            // ber-Hold_Flag NULL ikut terkecualikan (NULL <> 'Y' = NULL).
            ->whereRaw("COALESCE(lt.Hold_Flag, 'T') <> 'Y'")
            // Siap diputus punya keranjangnya sendiri — di sana palunya yang
            // ditunggu, bukan pekerjaan yang belum dikerjakan.
            ->where('lt.Siap_Diputus', '<>', 'Y')
            ->whereRaw($giliran);

        // Ringkasan per jenis pekerjaan (tanpa limit) — ini yang jadi daftar
        // "apa saja yang perlu dilakukan" begitu penanda diklik.
        $ringkas = $dasar()
            ->groupBy('lt.Tipe_Tahap_Kode', 'mtt.Nama', 'mtt.Ikon', 'mtt.Perilaku_Kode')
            ->selectRaw("lt.Tipe_Tahap_Kode as Kode, mtt.Nama as TipeNama, mtt.Ikon as Ikon,
                         mtt.Perilaku_Kode as Perilaku, COUNT(*) as J,
                         MAX({$umur}) as TerlamaHari")
            ->orderByDesc('J')
            ->get();

        $baris = $dasar()
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->selectRaw("l.Id_Lamaran, u.Nama as Pelamar, l.Created_By as FallbackNama,
                         p.Nama as ProgramNama, pos.Posisi as PosisiNama,
                         lt.Urutan as TahapUrutan, lt.Label as TahapLabel,
                         lt.Tipe_Tahap_Kode as Kode, mtt.Perilaku_Kode as Perilaku,
                         lt.Penjadwalan_Tahap_Id as JadwalId, {$umur} as UmurHari")
            ->orderByDesc(DB::raw($umur))
            ->limit(self::AKSI_MAKS)
            ->get();

        return [
            'total' => (int) $ringkas->sum('J'),
            'ringkas' => $ringkas->map(fn ($r) => [
                'kode' => $r->Kode ?: 'LAIN',
                'nama' => $r->TipeNama ?: 'Tahap lain',
                'ikon' => $r->Ikon ?: 'bi-three-dots',
                'aksi' => self::AKSI_TIPE[$r->Kode] ?? ('Tindak lanjuti ' . mb_strtolower($r->TipeNama ?: 'tahap ini')),
                'jumlah' => (int) $r->J,
                'terlamaHari' => max(0, (int) $r->TerlamaHari),
            ])->all(),
            'baris' => $baris->map(fn ($r) => [
                'id' => Hashids::encode((int) $r->Id_Lamaran),
                'nama' => $r->Pelamar ?: ($r->FallbackNama ?: 'Tanpa nama'),
                'program' => $r->ProgramNama,
                'posisi' => $r->PosisiNama,
                'tahap' => trim(($r->TahapUrutan ? $r->TahapUrutan . '. ' : '') . ($r->TahapLabel ?: '—')),
                'kode' => $r->Kode ?: 'LAIN',
                'aksi' => self::AKSI_TIPE[$r->Kode] ?? 'Tindak lanjuti',
                'umurHari' => max(0, (int) $r->UmurHari),
                'tautan' => $this->tautanTindakan($kategori, $r),
            ])->all(),
        ];
    }

    /**
     * PROSES ADMINISTRASI — kandidat sudah DITERIMA (Lamaran.Status='LULUS')
     * tapi masih ada tahap administratif (kontrak, onboarding) yang BERJALAN.
     *
     * Terpisah dari antreanAksi() seleksi: mencampurnya membuat admin mengira
     * ada kandidat yang "belum diputus", padahal justru sudah diterima dan
     * cuma menunggu berkas administratif.
     */
    private function pascaPenerimaan(string $kategori, array $ids): array
    {
        $dasar = fn () => DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->where('l.Status', 'LULUS')->where('lt.Status', 'BERJALAN')
            ->whereIn('l.Program_Id', $ids);

        $total = (int) $dasar()->count();
        if ($total === 0) {
            return ['baris' => [], 'total' => 0];
        }

        $umur = MetrikRekrutmen::sqlUmurTahap('lt');
        $rows = $dasar()
            ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'l.Program_Id')
            ->orderByDesc(DB::raw($umur))
            ->limit(self::AKSI_MAKS)
            ->selectRaw("l.Id_Lamaran, u.Nama as Pelamar, l.Created_By as FallbackNama,
                         p.Nama as ProgramNama, lt.Label as TahapLabel, {$umur} as UmurHari")
            ->get();

        return [
            'total' => $total,
            'baris' => $rows->map(fn ($r) => [
                'id' => Hashids::encode((int) $r->Id_Lamaran),
                'nama' => $r->Pelamar ?: ($r->FallbackNama ?: 'Tanpa nama'),
                'program' => $r->ProgramNama,
                'tahap' => $r->TahapLabel ?: '—',
                'umurHari' => max(0, (int) $r->UmurHari),
                'tautan' => $this->tautanWorklist($kategori, $r->ProgramNama),
            ])->all(),
        ];
    }

    /**
     * Tujuan tombol "Kerjakan" — halaman tempat pekerjaannya benar-benar ada.
     *
     * Ujian online yang belum punya jadwal berakhir di Penjadwalan (di situ
     * sesi dibuat dan token dikirim); sisanya di Worklist, tempat hasil dicatat
     * dan tahap didorong maju. Mengirim keduanya ke Worklist akan membuat admin
     * mencari menu Penjadwalan sendiri — dan itulah langkah yang selama ini
     * hilang.
     */
    private function tautanTindakan(string $kategori, object $r): string
    {
        $belumTerjadwal = ($r->Perilaku ?? 'MANUAL') === 'CAT' && $r->JadwalId === null;

        return $belumTerjadwal ? '/karir/penjadwalan' : $this->tautanWorklist($kategori, $r->ProgramNama);
    }

    /**
     * Pembukaan yang tutup ≤7 hari, plus peringatan KANDIDAT KURANG.
     *
     * Yang kedua itu inti panelnya: tanggal tutup saja hanya informasi kalender,
     * sedangkan "tinggal 3 hari dan pelamar aktifnya masih di bawah kursi yang
     * belum terisi" adalah satu-satunya saat admin masih bisa bertindak —
     * memperpanjang, menambah kanal, atau menurunkan target.
     */
    private function pembukaanTutupSegera(string $kategori, array $ids): array
    {
        $rows = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('p.Kategori', $kategori)
            ->where('pb.Status_Publish', 'TERBIT')
            ->where('pb.Masa_Berlaku', 'BERBATAS')
            ->whereNotNull('pb.Tanggal_Tutup')
            ->whereRaw('pb.Tanggal_Tutup >= GETDATE()')
            ->whereRaw('pb.Tanggal_Tutup <= DATEADD(day, 7, GETDATE())')
            ->orderBy('pb.Tanggal_Tutup')
            ->selectRaw('pb.Id_Pembukaan, pb.Kode, pb.Tanggal_Tutup, p.Id_Program, p.Nama as ProgramNama,
                         DATEDIFF(day, GETDATE(), pb.Tanggal_Tutup) as SisaHari')
            ->get();

        if ($rows->isEmpty()) {
            return ['baris' => [], 'total' => 0];
        }

        $kuota = $this->jumlahPer('N_WEB_CAREERS_Program_Posisi', $ids, 'SUM(Kuota)');
        $terisi = $this->jumlahLamaranPer($ids, 'LULUS');
        $aktif = $this->jumlahLamaranPer($ids, 'BERJALAN');

        return [
            'total' => $rows->count(),
            'baris' => $rows->map(function ($r) use ($kuota, $terisi, $aktif) {
                $k = (int) ($kuota[$r->Id_Program] ?? 0);
                $t = (int) ($terisi[$r->Id_Program] ?? 0);
                $a = (int) ($aktif[$r->Id_Program] ?? 0);
                $sisa = max(0, $k - $t);

                return [
                    'id' => Hashids::encode((int) $r->Id_Program),
                    'program' => $r->ProgramNama,
                    'kode' => $r->Kode,
                    'tutup' => $r->Tanggal_Tutup,
                    'sisaHari' => max(0, (int) $r->SisaHari),
                    'kuota' => $k,
                    'terisi' => $t,
                    'sisaKursi' => $sisa,
                    'pelamarAktif' => $a,
                    // Kursi belum penuh DAN kandidat yang masih berproses tidak
                    // cukup untuk mengisinya, sekalipun semuanya lolos.
                    'kandidatKurang' => $sisa > 0 && $a < $sisa,
                ];
            })->all(),
        ];
    }

    /**
     * Lamaran yang GAGAL masuk (Apply_Payload). Ini pelamar yang benar-benar
     * hilang: dia sudah menekan "Lamar", dapat balasan 202, lalu job-nya gagal
     * dan tidak ada satu pun halaman yang menampilkannya. Tanpa panel ini,
     * satu-satunya jejaknya adalah baris log.
     */
    private function lamaranGagalMasuk(string $kategori): array
    {
        $dasar = fn () => DB::table('N_WEB_CAREERS_Apply_Payload as ap')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as pb', 'pb.Id_Pembukaan', '=', 'ap.Pembukaan_Id')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('ap.Status', 'GAGAL')
            ->where('p.Kategori', $kategori);

        $total = (int) $dasar()->count();
        if ($total === 0) {
            return ['baris' => [], 'total' => 0];
        }

        $rows = $dasar()
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'ap.Program_Posisi_Id')
            ->orderByDesc('ap.Created_At')
            ->limit(self::AKSI_MAKS)
            ->select('ap.Process_Id', 'ap.Nama_Kandidat', 'ap.Pesan_Error', 'ap.Percobaan',
                'ap.Created_At', 'p.Nama as ProgramNama', 'pos.Posisi as PosisiNama')
            ->get();

        return [
            'total' => $total,
            'baris' => $rows->map(fn ($r) => [
                'processId' => $r->Process_Id,
                'nama' => $r->Nama_Kandidat ?: 'Tanpa nama',
                'program' => $r->ProgramNama,
                'posisi' => $r->PosisiNama,
                'pesan' => $r->Pesan_Error,
                'percobaan' => (int) $r->Percobaan,
                'waktu' => $r->Created_At,
            ])->all(),
        ];
    }

    // ═══════════════════════ ZONA C — ANALITIK ═══════════════════════

    /** GET /api/v1/karir/dashboard/analitik — funnel, tren, kesehatan, kuota, agenda. */
    public function analitik(Request $request)
    {
        [$kategori, $galat] = $this->kategoriDiminta($request);
        if ($galat) {
            return $galat;
        }

        try {
            $periode = $this->periode($request);

            $data = DB::transaction(function () use ($kategori, $periode) {
                $programs = $this->programs($kategori);
                $ids = $programs->pluck('Id_Program')->map(fn ($v) => (int) $v)->all();

                return [
                    'funnel' => $this->funnel($programs, $ids),
                    'tren' => $this->tren($ids, $periode),
                    'kesehatan' => $this->kesehatanProgram($programs, $ids),
                ];
            });

            return ResponseHelper::success($data + [
                'periode' => $periode['hari'],
                'checkpoint' => MetrikRekrutmen::checkpoint(),
            ], 'Analitik dashboard');
        } catch (\Throwable $e) {
            return $this->gagal('analitik', $kategori, $e);
        }
    }

    /**
     * GET /api/v1/karir/dashboard/kalender
     *
     * Kalender memakai rentang yang sedang terlihat di FullCalendar. `akhir`
     * bersifat eksklusif, sama seperti kontrak FullCalendar, agar event
     * sepanjang hari tidak bergeser satu hari ketika melintasi bulan.
     */
    public function kalender(Request $request)
    {
        [$kategori, $galat] = $this->kategoriDiminta($request);
        if ($galat) {
            return $galat;
        }

        try {
            $mulai = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', (string) $request->query('mulai'))->startOfDay();
            $akhir = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', (string) $request->query('akhir'))->startOfDay();
        } catch (\Throwable $e) {
            return ResponseHelper::error('Rentang kalender tidak valid. Gunakan format YYYY-MM-DD.', 422);
        }

        if ($akhir->lte($mulai) || $mulai->diffInDays($akhir) > 92) {
            return ResponseHelper::error('Rentang kalender harus 1 sampai 92 hari.', 422);
        }

        try {
            $data = DB::transaction(function () use ($kategori, $mulai, $akhir) {
                $programs = $this->programs($kategori);
                $ids = $programs->pluck('Id_Program')->map(fn ($v) => (int) $v)->all();
                $events = $this->eventKalender($kategori, $ids, $mulai, $akhir);

                return [
                    'events' => $events,
                    'summary' => $this->ringkasanKalender($events),
                ];
            });

            return ResponseHelper::success($data + [
                'checkpoint' => MetrikRekrutmen::checkpoint(),
            ], 'Kalender dashboard');
        } catch (\Throwable $e) {
            return $this->gagal('kalender', $kategori, $e);
        }
    }

    /**
     * Detail peserta satu sesi tes. Dipisah dari payload kalender agar membuka
     * kalender bulanan tidak mengirim ratusan nama kandidat yang belum dilihat.
     */
    public function pesertaKalender(Request $request, string $id)
    {
        [$kategori, $galat] = $this->kategoriDiminta($request);
        if ($galat) {
            return $galat;
        }

        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Sesi kalender tidak ditemukan.', 404);
        }

        try {
            $sesi = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                ->join('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pt.Penjadwalan_Id')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pj.Program_Id')
                ->where('pt.Id_Penjadwalan_Tahap', $realId)
                ->where('p.Kategori', $kategori)
                ->first(['pt.Id_Penjadwalan_Tahap', 'pt.Label', 'pt.Nama_Ujian', 'pt.Waktu_Mulai',
                    'pt.Waktu_Akhir', 'p.Nama as ProgramNama']);

            if (! $sesi) {
                return ResponseHelper::error('Sesi tidak ditemukan atau berada di luar hak akses Anda.', 404);
            }

            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Penjadwalan_Tahap_Id', $realId)
                ->orderByRaw("CASE
                    WHEN Status_Kirim = 'GAGAL' THEN 1
                    WHEN Status_Kirim IS NULL OR Status_Kirim <> 'TERKIRIM' THEN 2
                    WHEN Status_Pengerjaan = 'SELESAI' THEN 4
                    ELSE 3 END")
                ->orderBy('Nama')
                ->get(['Kode_Peserta', 'Nama', 'Posisi_Dilamar', 'Status_Kirim', 'Status_Pengerjaan'])
                ->map(fn ($p) => [
                    'kode' => $p->Kode_Peserta,
                    'nama' => $p->Nama ?: 'Tanpa nama',
                    'posisi' => $p->Posisi_Dilamar,
                    'statusKirim' => $p->Status_Kirim ?: 'MENUNGGU',
                    'statusPengerjaan' => $p->Status_Pengerjaan ?: 'BELUM',
                ])->values()->all();

            return ResponseHelper::success([
                'sesi' => [
                    'judul' => $sesi->Nama_Ujian ?: ($sesi->Label ?: 'Sesi tes'),
                    'program' => $sesi->ProgramNama,
                    'mulai' => $sesi->Waktu_Mulai,
                    'akhir' => $sesi->Waktu_Akhir,
                ],
                'peserta' => $peserta,
                'total' => count($peserta),
            ], 'Peserta sesi kalender');
        } catch (\Throwable $e) {
            return $this->gagal('kalender-peserta', $kategori, $e);
        }
    }

    /** Gabungkan sesi tes, agenda program, dan deadline pendaftaran. */
    private function eventKalender(string $kategori, array $ids, $mulai, $akhir): array
    {
        $events = [];
        $bolehTes = AksesService::boleh('penjadwalanPage', 'VIEW');
        $bolehAgenda = AksesService::boleh('masterJadwalPage', 'VIEW');
        $bolehPembukaan = AksesService::boleh('pembukaanPage', 'VIEW');

        if ($ids) {
            $tes = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                ->join('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pt.Penjadwalan_Id')
                ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pj.Program_Id')
                ->whereIn('pj.Program_Id', $ids)
                ->whereNotNull('pt.Waktu_Mulai')
                ->where('pt.Waktu_Mulai', '<', $akhir)
                ->where(function ($q) use ($mulai) {
                    $q->whereNull('pt.Waktu_Akhir')->where('pt.Waktu_Mulai', '>=', $mulai)
                        ->orWhere('pt.Waktu_Akhir', '>', $mulai);
                })
                ->orderBy('pt.Waktu_Mulai')
                ->get(['pt.Id_Penjadwalan_Tahap', 'pt.Penjadwalan_Id', 'pt.Label', 'pt.Nama_Ujian',
                    'pt.Waktu_Mulai', 'pt.Waktu_Akhir', 'pt.Durasi_Menit', 'pt.Status',
                    'pj.Nama as PenjadwalanNama', 'p.Id_Program', 'p.Nama as ProgramNama']);

            $peserta = $tes->isEmpty()
                ? collect()
                : DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                    ->whereIn('Penjadwalan_Tahap_Id', $tes->pluck('Id_Penjadwalan_Tahap')->all())
                    ->groupBy('Penjadwalan_Tahap_Id')
                    ->selectRaw("Penjadwalan_Tahap_Id, COUNT(*) as total,
                        SUM(CASE WHEN Status_Kirim = 'TERKIRIM' THEN 1 ELSE 0 END) as terkirim,
                        SUM(CASE WHEN Status_Kirim = 'GAGAL' THEN 1 ELSE 0 END) as gagal,
                        SUM(CASE WHEN Status_Kirim IS NULL OR Status_Kirim NOT IN ('TERKIRIM','GAGAL') THEN 1 ELSE 0 END) as menunggu")
                    ->get()->keyBy('Penjadwalan_Tahap_Id');

            foreach ($tes as $r) {
                $p = $peserta->get($r->Id_Penjadwalan_Tahap);
                $total = (int) ($p->total ?? 0);
                $terkirim = (int) ($p->terkirim ?? 0);
                $gagal = (int) ($p->gagal ?? 0);
                $menunggu = (int) ($p->menunggu ?? 0);
                $waktuMulai = \Illuminate\Support\Carbon::parse($r->Waktu_Mulai);
                $waktuAkhir = $r->Waktu_Akhir ? \Illuminate\Support\Carbon::parse($r->Waktu_Akhir) : null;

                if (($waktuAkhir && $waktuAkhir->isPast()) || strtoupper((string) $r->Status) === 'SELESAI') {
                    $kesiapan = 'SELESAI';
                } elseif ($gagal > 0 || ($waktuMulai->between(now(), now()->copy()->addDay()) && ($total === 0 || $terkirim < $total))) {
                    $kesiapan = 'PERLU_PERHATIAN';
                } elseif ($total > 0 && $terkirim === $total) {
                    $kesiapan = 'SIAP';
                } else {
                    $kesiapan = 'MENUNGGU';
                }

                $events[] = [
                    'id' => 'TES-' . Hashids::encode($r->Id_Penjadwalan_Tahap),
                    'jenis' => 'TES',
                    'judul' => $r->Nama_Ujian ?: ($r->Label ?: ($r->PenjadwalanNama ?: 'Sesi tes')),
                    'program' => $r->ProgramNama,
                    'programId' => Hashids::encode($r->Id_Program),
                    'mulai' => (string) $r->Waktu_Mulai,
                    'akhir' => $r->Waktu_Akhir ? (string) $r->Waktu_Akhir : null,
                    'allDay' => false,
                    'status' => $r->Status,
                    'kesiapan' => $kesiapan,
                    'jumlahPeserta' => $total,
                    'pesertaTerkirim' => $terkirim,
                    'pesertaMenunggu' => $menunggu,
                    'pesertaGagal' => $gagal,
                    'ket' => $r->Durasi_Menit ? $r->Durasi_Menit . ' menit' : null,
                    'pesertaUrl' => '/api/v1/karir/dashboard/kalender/tes/' . Hashids::encode($r->Id_Penjadwalan_Tahap) . '/peserta',
                    'sourceUrl' => $bolehTes ? '/karir/penjadwalan?fokus=' . Hashids::encode($r->Penjadwalan_Id) : null,
                    'konflik' => false,
                    'hariPadat' => false,
                ];
            }

            $agenda = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as ag')
                ->join('N_WEB_CAREERS_Master_Jadwal as j', 'j.Id_Master_Jadwal', '=', 'ag.Master_Jadwal_Id')
                ->join('N_WEB_CAREERS_Program as p', 'p.Jadwal_Kode', '=', 'j.Kode')
                ->whereIn('p.Id_Program', $ids)
                ->whereNotNull('ag.Tanggal_Mulai')
                ->where('ag.Tanggal_Mulai', '<', $akhir->toDateString())
                ->where(function ($q) use ($mulai) {
                    $q->whereNull('ag.Tanggal_Selesai')->where('ag.Tanggal_Mulai', '>=', $mulai->toDateString())
                        ->orWhere('ag.Tanggal_Selesai', '>=', $mulai->toDateString());
                })
                ->orderBy('ag.Tanggal_Mulai')
                ->get(['ag.Id_Master_Jadwal_Agenda', 'ag.Master_Jadwal_Id', 'ag.Label', 'ag.Jenis',
                    'ag.Tanggal_Mulai', 'ag.Tanggal_Selesai', 'p.Id_Program', 'p.Nama as ProgramNama']);

            foreach ($agenda as $r) {
                // FullCalendar memakai akhir eksklusif untuk event sepanjang hari.
                $akhirEksklusif = $r->Tanggal_Selesai
                    ? \Illuminate\Support\Carbon::parse($r->Tanggal_Selesai)->addDay()->toDateString()
                    : \Illuminate\Support\Carbon::parse($r->Tanggal_Mulai)->addDay()->toDateString();
                $events[] = [
                    'id' => 'AGENDA-' . Hashids::encode($r->Id_Master_Jadwal_Agenda) . '-' . Hashids::encode($r->Id_Program),
                    'jenis' => 'AGENDA',
                    'judul' => $r->Label ?: 'Agenda program',
                    'program' => $r->ProgramNama,
                    'programId' => Hashids::encode($r->Id_Program),
                    'mulai' => substr((string) $r->Tanggal_Mulai, 0, 10),
                    'akhir' => $akhirEksklusif,
                    'allDay' => true,
                    'status' => null,
                    'kesiapan' => null,
                    'jumlahPeserta' => null,
                    'ket' => $r->Jenis,
                    'sourceUrl' => $bolehAgenda ? '/master-jadwal?fokus=' . Hashids::encode($r->Master_Jadwal_Id) : null,
                    'konflik' => false,
                    'hariPadat' => false,
                ];
            }
        }

        $tutup = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('p.Kategori', $kategori)
            ->where('pb.Status_Publish', 'TERBIT')
            ->whereNotNull('pb.Tanggal_Tutup')
            ->where('pb.Tanggal_Tutup', '>=', $mulai)
            ->where('pb.Tanggal_Tutup', '<', $akhir)
            ->orderBy('pb.Tanggal_Tutup')
            ->get(['pb.Id_Pembukaan', 'pb.Kode', 'pb.Tanggal_Tutup', 'p.Id_Program', 'p.Nama as ProgramNama']);

        foreach ($tutup as $r) {
            $events[] = [
                'id' => 'TUTUP-' . Hashids::encode($r->Id_Pembukaan),
                'jenis' => 'TUTUP',
                'judul' => 'Pendaftaran ditutup',
                'program' => $r->ProgramNama,
                'programId' => Hashids::encode($r->Id_Program),
                'mulai' => (string) $r->Tanggal_Tutup,
                'akhir' => null,
                'allDay' => false,
                'status' => null,
                'kesiapan' => null,
                'jumlahPeserta' => null,
                'ket' => $r->Kode,
                'sourceUrl' => $bolehPembukaan ? '/karir/pembukaan?fokus=' . Hashids::encode($r->Id_Pembukaan) : null,
                'konflik' => false,
                'hariPadat' => false,
            ];
        }

        $this->tandaiKonflik($events);
        $this->tandaiHariPadat($events);
        usort($events, fn ($a, $b) => strcmp((string) $a['mulai'], (string) $b['mulai']));

        return $events;
    }

    /** Bentrok hanya bermakna untuk sesi berjam pada program yang sama. */
    private function tandaiKonflik(array &$events): int
    {
        $pasangan = 0;
        $indeks = array_keys(array_filter($events, fn ($e) => $e['jenis'] === 'TES' && ! empty($e['akhir'])));
        for ($a = 0; $a < count($indeks); $a++) {
            for ($b = $a + 1; $b < count($indeks); $b++) {
                $i = $indeks[$a];
                $j = $indeks[$b];
                if ($events[$i]['programId'] !== $events[$j]['programId']) {
                    continue;
                }
                if ($events[$i]['mulai'] < $events[$j]['akhir'] && $events[$j]['mulai'] < $events[$i]['akhir']) {
                    $events[$i]['konflik'] = $events[$j]['konflik'] = true;
                    $pasangan++;
                }
            }
        }

        return $pasangan;
    }

    private function tandaiHariPadat(array &$events): void
    {
        $jumlah = [];
        foreach ($events as $event) {
            $hari = substr((string) $event['mulai'], 0, 10);
            $jumlah[$hari] = ($jumlah[$hari] ?? 0) + 1;
        }
        foreach ($events as &$event) {
            $event['hariPadat'] = ($jumlah[substr((string) $event['mulai'], 0, 10)] ?? 0) >= 3;
        }
        unset($event);
    }

    private function ringkasanKalender(array $events): array
    {
        $hariIni = now()->toDateString();
        $mendatang = array_filter($events, fn ($e) => (string) $e['mulai'] >= now()->toDateTimeString());
        $deadline = array_values(array_filter($mendatang, fn ($e) => $e['jenis'] === 'TUTUP'));
        $hariPadat = array_unique(array_map(
            fn ($e) => substr((string) $e['mulai'], 0, 10),
            array_filter($events, fn ($e) => $e['hariPadat'])
        ));

        return [
            'hariIni' => count(array_filter($events, fn ($e) => substr((string) $e['mulai'], 0, 10) === $hariIni)),
            'perluPerhatian' => count(array_filter($mendatang, fn ($e) => ($e['kesiapan'] ?? null) === 'PERLU_PERHATIAN')),
            'bentrok' => count(array_filter($events, fn ($e) => $e['konflik'])),
            'hariPadat' => count($hariPadat),
            'deadlineTerdekat' => $deadline[0] ?? null,
        ];
    }

    /**
     * FUNNEL KONVERSI, DIKELOMPOKKAN PER ALUR.
     *
     * Menggabungkan semua program satu kategori ke satu funnel akan salah:
     * program dalam kategori yang sama boleh memakai alur berbeda, jadi
     * "tahap 3" pada satu program bisa Psikotes sementara di program lain
     * Wawancara HR. Menjumlahkannya menghasilkan kolom bernama satu hal yang
     * isinya campuran dua hal. Karena itu funnel dipecah per Master_Alur —
     * layar memilih salah satu, bawaannya alur dengan pelamar terbanyak.
     *
     * "Capai tahap i" = jumlah lamaran ber-UrutanDisplay >= i: kalau seseorang
     * sekarang di tahap 5, dia pasti sudah melewati 1-4; kalau dia gugur di
     * tahap 3, dia mencapai 3 dan tidak pernah mencapai 4. Dari situ konversi
     * antar tahap dan penyempitan terbesar dihitung.
     */
    private function funnel($programs, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $in = implode(',', $ids);
        $penempatan = DB::table(DB::raw('(' . MetrikRekrutmen::sqlUrutanDisplayBerkode("l.Program_Id IN ({$in})") . ') d'))
            ->groupBy('d.Program_Id', 'd.UrutanDisplay', 'd.KodeDisplay')
            ->select('d.Program_Id', 'd.UrutanDisplay', 'd.KodeDisplay',
                DB::raw("SUM(CASE WHEN d.Status = 'BERJALAN' THEN 1 ELSE 0 END) as aktif"),
                DB::raw("SUM(CASE WHEN d.Status = 'GUGUR' THEN 1 ELSE 0 END) as gugur"),
                DB::raw("SUM(CASE WHEN d.Status = 'LULUS' THEN 1 ELSE 0 END) as lulus"),
                DB::raw("SUM(CASE WHEN d.Status = 'TALENT_POOL' THEN 1 ELSE 0 END) as talent"))
            ->get()
            ->groupBy('Program_Id');

        $alurIds = $programs->pluck('Id_Master_Alur')->filter()->unique()->values()->all();
        $tahapMaster = $alurIds
            ? DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->whereIn('Master_Alur_Id', $alurIds)
                ->orderBy('Urutan')
                ->get(['Master_Alur_Id', 'Urutan', 'Kode', 'Label', 'Provider'])
                ->groupBy('Master_Alur_Id')
            : collect();

        $keluar = [];
        foreach ($programs->groupBy('Id_Master_Alur') as $alurId => $grup) {
            $kolom = collect($tahapMaster->get($alurId, []));
            if ($kolom->isEmpty()) {
                continue;   // program tanpa alur tidak punya kolom funnel
            }
            $maxUrutan = (int) $kolom->max('Urutan');

            // Jumlahkan seluruh program pemakai alur ini, DIPASANGKAN LEWAT KODE
            // tahap. Kandidat yang alurnya berbeda tetap dihitung di tahap yang
            // benar selama namanya sama — dan itu kasus yang paling sering,
            // karena alur baru biasanya menambah/menggeser tahap, bukan
            // mengganti seluruh namanya.
            //
            // Nomor urut (dengan pengikatan ke kolom terakhir) tetap dipakai
            // bila kodenya tak dikenal alur ini — supaya tak ada yang hilang
            // dari hitungan, sekalipun tahapnya sudah tak ada lagi.
            $urutanPerKode = $kolom->filter(fn ($t) => (string) ($t->Kode ?? '') !== '')
                ->mapWithKeys(fn ($t) => [$t->Kode => (int) $t->Urutan]);

            $per = [];
            foreach ($grup as $p) {
                foreach ($penempatan->get($p->Id_Program, []) as $c) {
                    $kode = (string) ($c->KodeDisplay ?? '');
                    $u = $kode !== '' && $urutanPerKode->has($kode)
                        ? $urutanPerKode->get($kode)
                        : (int) $c->UrutanDisplay;
                    $u = ($maxUrutan > 0 && $u > $maxUrutan) ? $maxUrutan : max(1, $u);
                    $per[$u] ??= ['aktif' => 0, 'gugur' => 0, 'lulus' => 0, 'talent' => 0];
                    foreach ($per[$u] as $k => $v) {
                        $per[$u][$k] += (int) $c->{$k};
                    }
                }
            }

            $urutanList = $kolom->pluck('Urutan')->map(fn ($v) => (int) $v)->values()->all();
            $totalPer = [];
            foreach ($urutanList as $u) {
                $c = $per[$u] ?? ['aktif' => 0, 'gugur' => 0, 'lulus' => 0, 'talent' => 0];
                $totalPer[$u] = $c['aktif'] + $c['gugur'] + $c['lulus'] + $c['talent'];
            }

            // capai(i) = Σ total(u) untuk u >= i.
            $capai = [];
            $akumulasi = 0;
            foreach (array_reverse($urutanList) as $u) {
                $akumulasi += $totalPer[$u];
                $capai[$u] = $akumulasi;
            }

            $tahapKeluar = [];
            $sempitTerbesar = null;
            $sebelumnya = null;
            foreach ($kolom as $t) {
                $u = (int) $t->Urutan;
                $c = $per[$u] ?? ['aktif' => 0, 'gugur' => 0, 'lulus' => 0, 'talent' => 0];
                $konversi = null;
                if ($sebelumnya !== null && ($capai[$sebelumnya] ?? 0) > 0) {
                    $konversi = (int) round($capai[$u] * 100 / $capai[$sebelumnya]);
                    if ($sempitTerbesar === null || $konversi < $sempitTerbesar['persen']) {
                        $sempitTerbesar = [
                            'dari' => $kolom->firstWhere('Urutan', $sebelumnya)->Label ?? ('Tahap ' . $sebelumnya),
                            'ke' => $t->Label ?: ('Tahap ' . $u),
                            'persen' => $konversi,
                            'hilang' => max(0, ($capai[$sebelumnya] ?? 0) - $capai[$u]),
                        ];
                    }
                }
                $tahapKeluar[] = [
                    'urutan' => $u,
                    'label' => $t->Label ?: ('Tahap ' . $u),
                    'kode' => $t->Kode,
                    'provider' => $t->Provider,
                    'capai' => $capai[$u] ?? 0,
                    'konversi' => $konversi,
                ] + $c;
                $sebelumnya = $u;
            }

            $nama = $grup->first()->Alur_Nama ?: 'Alur tanpa nama';
            $keluar[] = [
                'alurId' => (int) $alurId,
                'alur' => $nama,
                'program' => $grup->count(),
                'programNama' => $grup->pluck('Nama')->values()->all(),
                'pelamar' => $capai[$urutanList[0] ?? 1] ?? 0,
                'tahap' => $tahapKeluar,
                'penyempitan' => $sempitTerbesar,
            ];
        }

        // Alur dengan pelamar terbanyak dulu — itu yang paling mewakili kategori.
        usort($keluar, fn ($a, $b) => $b['pelamar'] <=> $a['pelamar']);

        return $keluar;
    }

    /**
     * TREN — dua seri dengan SUMBU WAKTU YANG BERBEDA MAKNANYA, jadi keduanya
     * dihitung terpisah lalu digabung per tanggal:
     *  - "masuk"    : dihitung pada tanggal MELAMAR;
     *  - "diterima" : dihitung pada tanggal KEPUTUSAN (Waktu_Selesai).
     * Kalau "diterima" ikut dikelompokkan pada tanggal melamar, grafiknya
     * berubah arti jadi kohort ("dari pelamar hari itu, berapa akhirnya lolos")
     * dan tidak lagi sebanding dengan garis di sebelahnya.
     *
     * Hari tanpa data diisi nol, bukan dilewati — garis yang menyambung
     * melewati lubang membuat kekosongan seminggu terlihat seperti tren naik.
     */
    private function tren(array $ids, array $periode): array
    {
        if (! $ids) {
            return ['titik' => [], 'totalMasuk' => 0, 'totalDiterima' => 0];
        }

        $lamar = 'COALESCE(l.Waktu_Lamar, l.Created_At)';

        $masuk = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->whereIn('l.Program_Id', $ids)
            ->when($periode['dari'], fn ($q) => $q->whereRaw("{$lamar} >= ?", [$periode['dari']]))
            ->groupByRaw("CAST({$lamar} AS date)")
            ->selectRaw("CAST({$lamar} AS date) as Tgl, COUNT(*) as J")
            ->pluck('J', 'Tgl');

        $diterima = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->whereIn('l.Program_Id', $ids)
            ->where('l.Status', 'LULUS')
            ->whereNotNull('l.Waktu_Selesai')
            ->when($periode['dari'], fn ($q) => $q->where('l.Waktu_Selesai', '>=', $periode['dari']))
            ->groupByRaw('CAST(l.Waktu_Selesai AS date)')
            ->selectRaw('CAST(l.Waktu_Selesai AS date) as Tgl, COUNT(*) as J')
            ->pluck('J', 'Tgl');

        $kunci = fn ($t) => substr((string) $t, 0, 10);
        $masukPeta = collect($masuk)->mapWithKeys(fn ($v, $k) => [$kunci($k) => (int) $v]);
        $terimaPeta = collect($diterima)->mapWithKeys(fn ($v, $k) => [$kunci($k) => (int) $v]);

        // Rentang sumbu: periode yang dipilih, atau dari data terawal saat "semua".
        $akhir = now()->copy()->startOfDay();
        if ($periode['dari']) {
            $mulai = $periode['dari']->copy()->startOfDay();
        } else {
            $semua = $masukPeta->keys()->merge($terimaPeta->keys())->sort()->values();
            $mulai = $semua->isEmpty() ? $akhir->copy() : \Carbon\Carbon::parse($semua->first())->startOfDay();
        }

        // Batas kewarasan: rentang "semua" pada data bertahun bisa jadi ribuan
        // titik dan membuat grafik tak terbaca sekaligus payload membengkak.
        if ($mulai->diffInDays($akhir) > 365) {
            $mulai = $akhir->copy()->subDays(365);
        }

        $titik = [];
        for ($t = $mulai->copy(); $t->lte($akhir); $t->addDay()) {
            $k = $t->format('Y-m-d');
            $titik[] = ['tgl' => $k, 'masuk' => $masukPeta[$k] ?? 0, 'diterima' => $terimaPeta[$k] ?? 0];
        }

        return [
            'titik' => $titik,
            'totalMasuk' => (int) $masukPeta->sum(),
            'totalDiterima' => (int) $terimaPeta->sum(),
        ];
    }

    /** Kesehatan + kuota per program, diurut yang paling perlu ditengok dulu. */
    private function kesehatanProgram($programs, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $sehatPer = MetrikRekrutmen::agregatSehat($ids);
        $totalPer = $this->jumlahLamaranPer($ids, null);
        $kuotaPer = $this->jumlahPer('N_WEB_CAREERS_Program_Posisi', $ids, 'SUM(Kuota)');
        $terisiPer = $this->jumlahLamaranPer($ids, 'LULUS');
        $aktifPer = $this->jumlahLamaranPer($ids, 'BERJALAN');

        $baris = $programs->map(function ($p) use ($sehatPer, $totalPer, $kuotaPer, $terisiPer, $aktifPer) {
            $total = (int) ($totalPer[$p->Id_Program] ?? 0);
            $kuota = (int) ($kuotaPer[$p->Id_Program] ?? 0);
            $terisi = (int) ($terisiPer[$p->Id_Program] ?? 0);
            $aktif = (int) ($aktifPer[$p->Id_Program] ?? 0);
            $sisa = max(0, $kuota - $terisi);

            return [
                'id' => Hashids::encode((int) $p->Id_Program),
                'kode' => $p->Kode,
                'nama' => $p->Nama,
                'status' => $p->Status,
                'warna' => $p->Warna,
                'alur' => $p->Alur_Nama,
                'penyelenggara' => $p->Penyelenggara,
                'pelamar' => $total,
                'kuota' => $kuota,
                'terisi' => $terisi,
                'sisaKursi' => $sisa,
                'pelamarAktif' => $aktif,
                // Kandidat yang masih berproses per kursi yang belum terisi.
                // < 1 berarti kursi itu tidak mungkin penuh dari kandidat yang
                // ada sekarang, sekalipun semuanya lolos. null = kursi sudah penuh.
                'kandidatPerKursi' => $sisa > 0 ? round($aktif / $sisa, 2) : null,
                'sehat' => MetrikRekrutmen::skorSehat($sehatPer->get($p->Id_Program), $total),
            ];
        })->values()->all();

        // Skor null (kosong/selesai) ditaruh paling bawah: bukan masalah,
        // cuma tidak ada yang berjalan.
        usort($baris, function ($a, $b) {
            $sa = $a['sehat']['skor'];
            $sb = $b['sehat']['skor'];
            if ($sa === null && $sb === null) {
                return strcmp($a['nama'], $b['nama']);
            }
            if ($sa === null) {
                return 1;
            }
            if ($sb === null) {
                return -1;
            }

            return $sa <=> $sb;
        });

        return $baris;
    }

    /**
     * AGENDA 14 HARI dari tiga sumber yang berbeda sifatnya, digabung
     * kronologis dan dibedakan `jenis` supaya tetap bisa dibaca asalnya:
     *  TES     — Penjadwalan_Tahap (sesi ujian yang benar-benar terjadwal)
     *  AGENDA  — Master_Jadwal_Agenda (rencana kegiatan program)
     *  TUTUP   — Pembukaan.Tanggal_Tutup (pendaftaran berakhir)
     */
    private function agenda(string $kategori, array $ids): array
    {
        $keluar = [];

        if ($ids) {
            $tes = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
                ->join('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pt.Penjadwalan_Id')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pj.Program_Id')
                ->whereIn('pj.Program_Id', $ids)
                ->whereNotNull('pt.Waktu_Mulai')
                ->whereRaw('pt.Waktu_Mulai >= GETDATE()')
                ->whereRaw('pt.Waktu_Mulai <= DATEADD(day, 14, GETDATE())')
                ->orderBy('pt.Waktu_Mulai')
                ->select('pt.Id_Penjadwalan_Tahap', 'pt.Label', 'pt.Waktu_Mulai', 'pt.Waktu_Akhir',
                    'pt.Durasi_Menit', 'pt.Status', 'p.Nama as ProgramNama', 'pj.Nama as PenjadwalanNama')
                ->get();

            foreach ($tes as $r) {
                $keluar[] = [
                    'jenis' => 'TES',
                    'judul' => $r->Label ?: ($r->PenjadwalanNama ?: 'Sesi tes'),
                    'program' => $r->ProgramNama,
                    'mulai' => $r->Waktu_Mulai,
                    'akhir' => $r->Waktu_Akhir,
                    'ket' => $r->Durasi_Menit ? $r->Durasi_Menit . ' menit' : null,
                    'status' => $r->Status,
                ];
            }

            // Agenda program: Program.Jadwal_Kode → Master_Jadwal.Kode.
            $agenda = DB::table('N_WEB_CAREERS_Master_Jadwal_Agenda as ag')
                ->join('N_WEB_CAREERS_Master_Jadwal as j', 'j.Id_Master_Jadwal', '=', 'ag.Master_Jadwal_Id')
                ->join('N_WEB_CAREERS_Program as p', 'p.Jadwal_Kode', '=', 'j.Kode')
                ->whereIn('p.Id_Program', $ids)
                ->whereNotNull('ag.Tanggal_Mulai')
                ->whereRaw('ag.Tanggal_Mulai >= CAST(GETDATE() AS date)')
                ->whereRaw('ag.Tanggal_Mulai <= DATEADD(day, 14, CAST(GETDATE() AS date))')
                ->orderBy('ag.Tanggal_Mulai')
                ->select('ag.Id_Master_Jadwal_Agenda', 'ag.Label', 'ag.Jenis', 'ag.Tanggal_Mulai',
                    'ag.Tanggal_Selesai', 'p.Nama as ProgramNama')
                ->get();

            foreach ($agenda as $r) {
                $keluar[] = [
                    'jenis' => 'AGENDA',
                    'judul' => $r->Label ?: 'Agenda',
                    'program' => $r->ProgramNama,
                    'mulai' => $r->Tanggal_Mulai,
                    'akhir' => $r->Tanggal_Selesai,
                    'ket' => $r->Jenis,
                    'status' => null,
                ];
            }
        }

        $tutup = DB::table('N_WEB_CAREERS_Pembukaan as pb')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('p.Kategori', $kategori)
            ->where('pb.Status_Publish', 'TERBIT')
            ->whereNotNull('pb.Tanggal_Tutup')
            ->whereRaw('pb.Tanggal_Tutup >= GETDATE()')
            ->whereRaw('pb.Tanggal_Tutup <= DATEADD(day, 14, GETDATE())')
            ->orderBy('pb.Tanggal_Tutup')
            ->select('pb.Kode', 'pb.Tanggal_Tutup', 'p.Nama as ProgramNama')
            ->get();

        foreach ($tutup as $r) {
            $keluar[] = [
                'jenis' => 'TUTUP',
                'judul' => 'Pendaftaran ditutup',
                'program' => $r->ProgramNama,
                'mulai' => $r->Tanggal_Tutup,
                'akhir' => null,
                'ket' => $r->Kode,
                'status' => null,
            ];
        }

        usort($keluar, fn ($a, $b) => strcmp((string) $a['mulai'], (string) $b['mulai']));

        return $keluar;
    }

    // ═══════════════════ ZONA D + E — KHAS & EKSTRA ═══════════════════

    /** GET /api/v1/karir/dashboard/khas — seksi khusus kategori + 4 panel ekstra. */
    public function khas(Request $request)
    {
        [$kategori, $galat] = $this->kategoriDiminta($request);
        if ($galat) {
            return $galat;
        }

        try {
            $data = DB::transaction(function () use ($kategori) {
                $programs = $this->programs($kategori);
                $ids = $programs->pluck('Id_Program')->map(fn ($v) => (int) $v)->all();

                $khas = match ($kategori) {
                    'MT' => $this->khasMt($ids),
                    'INTERNSHIP' => $this->khasMagang($ids),
                    default => $this->khasRekrutmen($ids),
                };

                return [
                    'bentuk' => match ($kategori) { 'MT' => 'MT', 'INTERNSHIP' => 'MAGANG', default => 'REKRUTMEN' },
                    'khas' => $khas,
                    'ekstra' => [
                        'tes' => $this->ekstraTes($ids),
                        'operasional' => $this->ekstraOperasional($kategori, $ids),
                        'feedback' => $this->ekstraFeedback($ids),
                        'talent' => $this->ekstraTalentPool($ids),
                    ],
                ];
            });

            return ResponseHelper::success($data + ['checkpoint' => MetrikRekrutmen::checkpoint()], 'Detail dashboard');
        } catch (\Throwable $e) {
            return $this->gagal('khas', $kategori, $e);
        }
    }

    /**
     * REKRUTMEN — pemenuhan MPP: posisi mana yang belum terisi, sudah berapa
     * lama terbuka, dan berapa lama biasanya satu kursi terisi.
     */
    private function khasRekrutmen(array $ids): array
    {
        if (! $ids) {
            return ['posisi' => [], 'timeToFill' => null, 'sumber' => [], 'departemen' => []];
        }

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi as pos')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pos.Program_Id')
            ->whereIn('pos.Program_Id', $ids)
            ->orderBy('pos.Posisi')
            ->select('pos.Id_Program_Posisi', 'pos.Posisi', 'pos.Departemen', 'pos.Lokasi', 'pos.Level',
                'pos.Kuota', 'pos.Status', 'pos.Mpp_Ref', 'pos.Program_Id', 'p.Nama as ProgramNama')
            ->get();

        $rekap = $this->rekapPerPosisi($ids);

        // Umur pembukaan per program (pembukaan TERBIT paling awal) — "posisi
        // ini sudah terbuka N hari" adalah pertanyaan pertama atasan soal MPP.
        $umurBuka = DB::table('N_WEB_CAREERS_Pembukaan')
            ->whereIn('Program_Id', $ids)
            ->where('Status_Publish', 'TERBIT')
            ->groupBy('Program_Id')
            ->selectRaw('Program_Id, DATEDIFF(day, MIN(Tanggal_Buka), GETDATE()) as Hari')
            ->pluck('Hari', 'Program_Id');

        $timeToFill = DB::table('N_WEB_CAREERS_Lamaran')
            ->whereIn('Program_Id', $ids)
            ->where('Status', 'LULUS')
            ->whereNotNull('Waktu_Selesai')
            ->whereNotNull('Waktu_Lamar')
            ->selectRaw('COUNT(*) as jml,
                         AVG(CAST(DATEDIFF(day, Waktu_Lamar, Waktu_Selesai) AS float)) as rata,
                         MIN(DATEDIFF(day, Waktu_Lamar, Waktu_Selesai)) as tercepat,
                         MAX(DATEDIFF(day, Waktu_Lamar, Waktu_Selesai)) as terlama')
            ->first();

        $sumber = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->leftJoin('N_WEB_CAREERS_Master_Sumber_Kandidat as s', 's.Kode', '=', 'l.Sumber_Kandidat_Kode')
            ->whereIn('l.Program_Id', $ids)
            ->groupBy('l.Sumber_Kandidat_Kode', 's.Nama')
            ->selectRaw('l.Sumber_Kandidat_Kode as kode, s.Nama as nama, COUNT(*) as jml')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->map(fn ($r) => ['kode' => $r->kode, 'nama' => $r->nama ?: ($r->kode ?: 'Tidak tercatat'), 'jml' => (int) $r->jml])
            ->all();

        $departemen = DB::table('N_WEB_CAREERS_Program_Posisi as pos')
            ->leftJoin('N_WEB_CAREERS_Lamaran as l', function ($j) {
                $j->on('l.Program_Posisi_Id', '=', 'pos.Id_Program_Posisi')->where('l.Status', '=', 'BERJALAN');
            })
            ->whereIn('pos.Program_Id', $ids)
            ->groupBy('pos.Departemen')
            ->selectRaw('pos.Departemen as departemen, SUM(pos.Kuota) as kuota, COUNT(l.Id_Lamaran) as aktif')
            ->orderByDesc(DB::raw('SUM(pos.Kuota)'))
            ->get()
            ->map(fn ($r) => ['departemen' => $r->departemen ?: 'Tanpa departemen',
                'kuota' => (int) $r->kuota, 'aktif' => (int) $r->aktif])
            ->all();

        return [
            'posisi' => $posisi->map(function ($r) use ($rekap, $umurBuka) {
                $k = $rekap->get($r->Id_Program_Posisi);
                $kuota = (int) $r->Kuota;
                $lulus = (int) ($k->lulus ?? 0);

                return [
                    'posisi' => $r->Posisi,
                    'program' => $r->ProgramNama,
                    'departemen' => $r->Departemen,
                    'lokasi' => $r->Lokasi,
                    'level' => $r->Level,
                    'mppRef' => $r->Mpp_Ref,
                    'status' => $r->Status,
                    'kuota' => $kuota,
                    'terisi' => $lulus,
                    'berjalan' => (int) ($k->berjalan ?? 0),
                    'pelamar' => (int) ($k->total ?? 0),
                    'sisaKursi' => max(0, $kuota - $lulus),
                    'umurBukaHari' => isset($umurBuka[$r->Program_Id]) ? max(0, (int) $umurBuka[$r->Program_Id]) : null,
                ];
            })->all(),
            // jml = 0 → belum pernah ada kursi terisi, jadi angkanya null,
            // bukan 0 hari (yang akan terbaca "instan").
            'timeToFill' => ((int) ($timeToFill->jml ?? 0)) > 0 ? [
                'jml' => (int) $timeToFill->jml,
                'rata' => round((float) $timeToFill->rata, 1),
                'tercepat' => (int) $timeToFill->tercepat,
                'terlama' => (int) $timeToFill->terlama,
            ] : null,
            'sumber' => $sumber,
            'departemen' => $departemen,
        ];
    }

    /** MAGANG — kampus & kemitraan, sebaran pendidikan, progres batch. */
    private function khasMagang(array $ids): array
    {
        // Kemitraan TIDAK tergantung $ids: MoU hidup terlepas dari ada/tidaknya
        // program magang berjalan, dan justru paling perlu dilihat saat belum
        // ada program (mana MoU yang belum dipakai dan hampir kedaluwarsa).
        $kemitraan = DB::table('N_WEB_CAREERS_Master_Kemitraan as m')
            ->leftJoin('N_WEB_CAREERS_Master_Kampus as k', 'k.Id_Master_Kampus', '=', 'm.Master_Kampus_Id')
            ->orderBy('m.Tanggal_Selesai')
            ->selectRaw('m.Id_Master_Kemitraan, m.Nomor, m.Mitra, m.Jenis, m.Kuota, m.Terisi, m.Status,
                         m.Tanggal_Mulai, m.Tanggal_Selesai, k.Nama as KampusNama, k.Kota as KampusKota,
                         CASE WHEN m.Tanggal_Selesai IS NULL THEN NULL
                              ELSE DATEDIFF(day, CAST(GETDATE() AS date), m.Tanggal_Selesai) END as SisaHari')
            ->get()
            ->map(fn ($r) => [
                'nomor' => $r->Nomor,
                'mitra' => $r->Mitra,
                'kampus' => $r->KampusNama,
                'kota' => $r->KampusKota,
                'jenis' => $r->Jenis,
                'status' => $r->Status,
                'kuota' => (int) $r->Kuota,
                'terisi' => (int) $r->Terisi,
                'mulai' => $r->Tanggal_Mulai,
                'selesai' => $r->Tanggal_Selesai,
                'sisaHari' => $r->SisaHari === null ? null : (int) $r->SisaHari,
            ])->all();

        $batch = $ids ? DB::table('N_WEB_CAREERS_Program_Batch as b')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'b.Program_Id')
            ->whereIn('b.Program_Id', $ids)
            ->orderBy('b.Nama')
            ->select('b.Nama', 'b.Kuota', 'b.Terisi', 'b.Status', 'p.Nama as ProgramNama')
            ->get()
            ->map(fn ($r) => ['nama' => $r->Nama, 'program' => $r->ProgramNama, 'status' => $r->Status,
                'kuota' => (int) $r->Kuota, 'terisi' => (int) $r->Terisi])
            ->all() : [];

        return [
            'kemitraan' => $kemitraan,
            'batch' => $batch,
            'pendidikan' => $this->sebaranPendidikan($ids),
        ];
    }

    /**
     * SEBARAN PENDIDIKAN pelamar dari Formulir_Jawaban_Index.
     *
     * Index ini SENGAJA tidak memuat semua jawaban — hanya field yang dipakai
     * aturan syarat plus field turunan. Jadi kampus/jurusan bisa saja belum
     * terindeks pada program yang tidak memakainya sebagai syarat; itu bukan
     * kesalahan baca, dan panelnya cukup menampilkan yang ada.
     *
     * Nama field dinormalkan lewat peta di bawah karena formulir lama memakai
     * kunci berbeda untuk hal yang sama (perguruan_tinggi / kampus /
     * nama_kampus). Tanpa penyeragaman, pengisian lama diam-diam tidak terhitung.
     */
    private function sebaranPendidikan(array $ids): array
    {
        if (! $ids) {
            return ['jenjang' => [], 'kampus' => [], 'jurusan' => [], 'ipk' => null];
        }

        $samakan = [
            'jenjang' => 'jenjang', 'jenjang_pendidikan' => 'jenjang', 'jenjang_politeknik' => 'jenjang',
            'nama_kampus' => 'kampus', 'kampus' => 'kampus', 'perguruan_tinggi' => 'kampus',
            'jurusan_gabungan' => 'jurusan', 'jurusan' => 'jurusan', 'program_studi' => 'jurusan', 'prodi' => 'jurusan',
        ];

        $teks = DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')
            ->whereIn('Program_Id', $ids)
            ->whereIn('Field_Key', array_keys($samakan))
            ->whereNotNull('Nilai_Teks')
            ->where('Nilai_Teks', '<>', '')
            ->groupBy('Field_Key', 'Nilai_Teks')
            ->selectRaw('Field_Key, Nilai_Teks, COUNT(*) as J')
            ->get();

        $kumpul = ['jenjang' => [], 'kampus' => [], 'jurusan' => []];
        foreach ($teks as $r) {
            $grup = $samakan[$r->Field_Key] ?? null;
            if (! $grup) {
                continue;
            }
            $nilai = trim((string) $r->Nilai_Teks);
            $kumpul[$grup][$nilai] = ($kumpul[$grup][$nilai] ?? 0) + (int) $r->J;
        }

        $rapikan = function (array $peta, int $maks) {
            arsort($peta);
            $out = [];
            foreach (array_slice($peta, 0, $maks, true) as $nama => $jml) {
                $out[] = ['nama' => $nama, 'jml' => $jml];
            }

            return $out;
        };

        $ipk = DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')
            ->whereIn('Program_Id', $ids)
            ->where('Field_Key', 'ipk')
            ->whereNotNull('Nilai_Angka')
            ->selectRaw('COUNT(*) as jml, AVG(Nilai_Angka) as rata, MIN(Nilai_Angka) as terendah, MAX(Nilai_Angka) as tertinggi,
                         SUM(CASE WHEN Nilai_Angka >= 3.5 THEN 1 ELSE 0 END) as atas35,
                         SUM(CASE WHEN Nilai_Angka >= 3.0 AND Nilai_Angka < 3.5 THEN 1 ELSE 0 END) as tiga,
                         SUM(CASE WHEN Nilai_Angka < 3.0 THEN 1 ELSE 0 END) as bawah3')
            ->first();

        return [
            'jenjang' => $rapikan($kumpul['jenjang'], 12),
            'kampus' => $rapikan($kumpul['kampus'], 12),
            'jurusan' => $rapikan($kumpul['jurusan'], 12),
            'ipk' => ((int) ($ipk->jml ?? 0)) > 0 ? [
                'jml' => (int) $ipk->jml,
                'rata' => round((float) $ipk->rata, 2),
                'terendah' => round((float) $ipk->terendah, 2),
                'tertinggi' => round((float) $ipk->tertinggi, 2),
                'atas35' => (int) $ipk->atas35,
                'tiga' => (int) $ipk->tiga,
                'bawah3' => (int) $ipk->bawah3,
            ] : null,
        ];
    }

    /**
     * MT — persaingan ANTAR POSISI di dalam satu program.
     *
     * Inilah yang tidak terlihat di mana pun sekarang: satu program MT berisi
     * banyak posisi dengan kursi masing-masing, tapi funnel program hanya
     * memperlihatkan totalnya. Matriks posisi × tahap memperlihatkan posisi
     * mana yang kehabisan kandidat di tahap mana.
     */
    private function khasMt(array $ids): array
    {
        if (! $ids) {
            return ['matriks' => [], 'tahap' => [], 'skor' => []];
        }

        $in = implode(',', $ids);

        // sqlUrutanDisplay + kolom tambahan Program_Posisi_Id: penempatan
        // dihitung dengan aturan yang sama seperti funnel, hanya dipecah posisi.
        // Berkode: angka dipasangkan ke kolom lewat IDENTITAS tahap, bukan
        // nomor urutnya — lihat MetrikRekrutmen::sqlUrutanDisplayBerkode().
        $sub = MetrikRekrutmen::sqlUrutanDisplayBerkode("l.Program_Id IN ({$in})", ['l.Program_Posisi_Id']);
        $sel = DB::table(DB::raw("({$sub}) d"))
            ->groupBy('d.Program_Posisi_Id', 'd.UrutanDisplay', 'd.KodeDisplay')
            ->selectRaw("d.Program_Posisi_Id, d.UrutanDisplay, d.KodeDisplay,
                         SUM(CASE WHEN d.Status = 'BERJALAN' THEN 1 ELSE 0 END) as aktif,
                         SUM(CASE WHEN d.Status = 'GUGUR' THEN 1 ELSE 0 END) as gugur,
                         SUM(CASE WHEN d.Status = 'LULUS' THEN 1 ELSE 0 END) as lulus,
                         SUM(CASE WHEN d.Status = 'TALENT_POOL' THEN 1 ELSE 0 END) as talent")
            ->get()
            ->groupBy('Program_Posisi_Id');

        // Kolom matriks = gabungan tahap dari alur yang BENAR-BENAR DIPAKAI
        // lamaran di program-program ini.
        //
        // Dulu join-nya lewat Program.Alur_Kode, yaitu alur yang SEKARANG
        // menempel di program. Begitu admin mengarahkan program ke alur baru,
        // rombongan yang masih berjalan di alur lama kehilangan kolomnya —
        // angkanya menempel di tahap yang salah, atau hilang sama sekali dari
        // rekap yang justru paling sering dilaporkan ke atas.
        $alurPerProgram = AlurKolom::alurDipakaiBanyak(
            array_map('intval', $ids),
            DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->whereIn('p.Id_Program', $ids)
                ->pluck('a.Id_Master_Alur', 'p.Id_Program')
                ->map(fn ($v) => $v ? (int) $v : null)
                ->all(),
        );

        $tahap = collect(AlurKolom::susun(
            collect($alurPerProgram)->flatten()->unique()->values()->all(),
        ))->map(fn ($k) => ['urutan' => $k['urutan'], 'kode' => $k['kode'], 'label' => $k['label']])->all();

        $posisi = DB::table('N_WEB_CAREERS_Program_Posisi as pos')
            ->join('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pos.Program_Id')
            ->whereIn('pos.Program_Id', $ids)
            ->orderBy('p.Nama')->orderBy('pos.Posisi')
            ->select('pos.Id_Program_Posisi', 'pos.Posisi', 'pos.Departemen', 'pos.Kuota',
                'pos.Status', 'pos.Mpp_Ref', 'p.Nama as ProgramNama')
            ->get();

        $skorPer = DB::table('N_WEB_CAREERS_Lamaran_Tahap as lt')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'lt.Lamaran_Id')
            ->whereIn('l.Program_Id', $ids)
            ->whereNotNull('lt.Skor')
            ->whereNotNull('l.Program_Posisi_Id')
            ->groupBy('l.Program_Posisi_Id')
            ->selectRaw('l.Program_Posisi_Id, COUNT(*) as jml, AVG(lt.Skor) as rata,
                         MIN(lt.Skor) as terendah, MAX(lt.Skor) as tertinggi')
            ->get()
            ->keyBy('Program_Posisi_Id');

        $matriks = $posisi->map(function ($r) use ($sel, $tahap, $skorPer) {
            $baris = collect($sel->get($r->Id_Program_Posisi, []));
            // Per KODE dulu; nomor urut hanya untuk baris lama tanpa Kode.
            $perKode = $baris->filter(fn ($c) => (string) ($c->KodeDisplay ?? '') !== '')->keyBy('KodeDisplay');
            $perUrutan = $baris->filter(fn ($c) => (string) ($c->KodeDisplay ?? '') === '')->keyBy('UrutanDisplay');
            $kuota = (int) $r->Kuota;

            $sel_ = [];
            $total = 0;
            $lulus = 0;
            foreach ($tahap as $t) {
                $c = $perKode->get($t['kode']) ?? $perUrutan->get($t['urutan']);
                $isi = [
                    'aktif' => (int) ($c->aktif ?? 0),
                    'gugur' => (int) ($c->gugur ?? 0),
                    'lulus' => (int) ($c->lulus ?? 0),
                    'talent' => (int) ($c->talent ?? 0),
                ];
                $isi['total'] = array_sum($isi);
                $total += $isi['total'];
                $lulus += $isi['lulus'];
                $sel_[] = $isi;
            }

            $s = $skorPer->get($r->Id_Program_Posisi);

            return [
                'posisi' => $r->Posisi,
                'program' => $r->ProgramNama,
                'departemen' => $r->Departemen,
                'mppRef' => $r->Mpp_Ref,
                'status' => $r->Status,
                'kuota' => $kuota,
                'pelamar' => $total,
                'terisi' => $lulus,
                // Pelamar per kursi — angka persaingan yang paling langsung
                // dipakai: > 1 diminati, < 1 sepi dan berisiko tidak terisi.
                'rasio' => $kuota > 0 ? round($total / $kuota, 2) : null,
                'sel' => $sel_,
                'skor' => $s ? [
                    'jml' => (int) $s->jml,
                    'rata' => round((float) $s->rata, 2),
                    'terendah' => round((float) $s->terendah, 2),
                    'tertinggi' => round((float) $s->tertinggi, 2),
                ] : null,
            ];
        })->values()->all();

        return ['matriks' => $matriks, 'tahap' => $tahap];
    }

    /** EKSTRA 1 — kehadiran & nilai tes CAT + sesi 14 hari ke depan. */
    private function ekstraTes(array $ids): array
    {
        if (! $ids) {
            return ['ringkas' => null, 'perSesi' => []];
        }

        $dasar = fn () => DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as pp')
            ->join('N_WEB_CAREERS_Penjadwalan as pj', 'pj.Id_Penjadwalan', '=', 'pp.Penjadwalan_Id')
            ->whereIn('pj.Program_Id', $ids);

        $ringkas = $dasar()
            ->selectRaw("COUNT(*) as peserta,
                         SUM(CASE WHEN pp.Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai,
                         SUM(CASE WHEN pp.Waktu_Mulai_Akses IS NULL THEN 1 ELSE 0 END) as belumAkses,
                         SUM(CASE WHEN LOWER(pp.Status_Pengerjaan) = 'timeout' THEN 1 ELSE 0 END) as timeout,
                         SUM(CASE WHEN pp.Status_Kirim = 'GAGAL' THEN 1 ELSE 0 END) as gagalKirim,
                         AVG(pp.Total_Nilai) as rataNilai,
                         MIN(pp.Total_Nilai) as nilaiMin,
                         MAX(pp.Total_Nilai) as nilaiMax")
            ->first();

        $perSesi = $dasar()
            ->leftJoin('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'pp.Penjadwalan_Tahap_Id')
            ->groupBy('pp.Penjadwalan_Tahap_Id', 'pt.Label', 'pt.Waktu_Mulai', 'pt.Ambang_Batas_Nilai')
            ->orderByDesc('pt.Waktu_Mulai')
            ->selectRaw("pp.Penjadwalan_Tahap_Id, pt.Label, pt.Waktu_Mulai, pt.Ambang_Batas_Nilai,
                         COUNT(*) as peserta,
                         SUM(CASE WHEN pp.Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as selesai,
                         SUM(CASE WHEN pp.Waktu_Mulai_Akses IS NULL THEN 1 ELSE 0 END) as belumAkses,
                         AVG(pp.Total_Nilai) as rataNilai")
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->Label ?: 'Sesi tanpa label',
                'mulai' => $r->Waktu_Mulai,
                'ambang' => $r->Ambang_Batas_Nilai === null ? null : (float) $r->Ambang_Batas_Nilai,
                'peserta' => (int) $r->peserta,
                'selesai' => (int) $r->selesai,
                'belumAkses' => (int) $r->belumAkses,
                'rataNilai' => $r->rataNilai === null ? null : round((float) $r->rataNilai, 2),
            ])->all();

        return [
            'ringkas' => ((int) ($ringkas->peserta ?? 0)) > 0 ? [
                'peserta' => (int) $ringkas->peserta,
                'selesai' => (int) $ringkas->selesai,
                'belumAkses' => (int) $ringkas->belumAkses,
                'timeout' => (int) $ringkas->timeout,
                'gagalKirim' => (int) $ringkas->gagalKirim,
                'rataNilai' => $ringkas->rataNilai === null ? null : round((float) $ringkas->rataNilai, 2),
                'nilaiMin' => $ringkas->nilaiMin === null ? null : round((float) $ringkas->nilaiMin, 2),
                'nilaiMax' => $ringkas->nilaiMax === null ? null : round((float) $ringkas->nilaiMax, 2),
            ] : null,
            'perSesi' => $perSesi,
        ];
    }

    /** EKSTRA 2 — operasional yang tidak terlihat di halaman mana pun. */
    private function ekstraOperasional(string $kategori, array $ids): array
    {
        $payload = DB::table('N_WEB_CAREERS_Apply_Payload as ap')
            ->leftJoin('N_WEB_CAREERS_Pembukaan as pb', 'pb.Id_Pembukaan', '=', 'ap.Pembukaan_Id')
            ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'pb.Program_Id')
            ->where('p.Kategori', $kategori)
            ->groupBy('ap.Status')
            ->selectRaw('ap.Status, COUNT(*) as J')
            ->pluck('J', 'Status');

        // Status_Verifikasi ditulis 'BELUM' oleh WcApplyFormJob sementara DDL
        // memberi bawaan 'MENUNGGU'. Keduanya berarti hal yang sama, jadi
        // keduanya dihitung — memilih satu saja akan melaporkan angka separuh.
        $berkas = $ids
            ? (int) DB::table('N_WEB_CAREERS_Formulir_Berkas as fb')
                ->join('N_WEB_CAREERS_Formulir_Pengisian as fp', 'fp.Id_Formulir_Pengisian', '=', 'fb.Formulir_Pengisian_Id')
                ->whereIn('fp.Program_Id', $ids)
                ->whereIn('fb.Status_Verifikasi', ['MENUNGGU', 'BELUM'])
                ->count()
            : 0;

        return [
            'applyMenunggu' => (int) ($payload['MENUNGGU'] ?? 0),
            'applyGagal' => (int) ($payload['GAGAL'] ?? 0),
            'applySelesai' => (int) ($payload['SELESAI'] ?? 0),
            'berkasMenunggu' => $berkas,
        ];
    }

    /**
     * EKSTRA 3 — feedback kandidat.
     *
     * Dihitung lokal, tidak lewat FeedbackService::aggregate(): fungsi itu
     * menerima SATU programId, jadi memakainya berarti memanggilnya sekali per
     * program lalu menjumlahkan rata-rata (yang menghasilkan rata-rata dari
     * rata-rata — bukan angka yang sama). Mengubah tanda tangannya berisiko ke
     * Feedback Dashboard yang sudah jalan, sementara yang dibutuhkan di sini
     * cuma empat angka.
     */
    private function ekstraFeedback(array $ids): array
    {
        if (! $ids) {
            return ['dibuat' => 0, 'terisi' => 0, 'responseRate' => null, 'rataRating' => null];
        }

        $agg = DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fj.Lamaran_Id')
            ->whereIn('l.Program_Id', $ids)
            ->selectRaw("COUNT(*) as dibuat,
                         SUM(CASE WHEN fj.Status_Pengisian = 'TERISI' THEN 1 ELSE 0 END) as terisi")
            ->first();

        $dibuat = (int) ($agg->dibuat ?? 0);
        $terisi = (int) ($agg->terisi ?? 0);

        // Rating dinormalkan ke 0-100 memakai skala snapshot masing-masing
        // pertanyaan, karena satu form bisa memakai 1-5 dan lainnya 1-10.
        $rating = DB::table('N_WEB_CAREERS_Feedback_Jawaban_Detail as d')
            ->join('N_WEB_CAREERS_Feedback_Jawaban as fj', 'fj.Id_Feedback_Jawaban', '=', 'd.Feedback_Jawaban_Id')
            ->join('N_WEB_CAREERS_Lamaran as l', 'l.Id_Lamaran', '=', 'fj.Lamaran_Id')
            ->whereIn('l.Program_Id', $ids)
            ->where('d.Tipe_Snapshot', 'RATING')
            ->whereNotNull('d.Jawaban')
            ->whereRaw('ISNUMERIC(d.Jawaban) = 1')
            ->whereNotNull('d.Skala_Max_Snapshot')
            ->where('d.Skala_Max_Snapshot', '>', 0)
            ->selectRaw('AVG(CAST(d.Jawaban AS float) * 100.0 / d.Skala_Max_Snapshot) as skor, COUNT(*) as jml')
            ->first();

        return [
            'dibuat' => $dibuat,
            'terisi' => $terisi,
            'responseRate' => $dibuat > 0 ? (int) round($terisi * 100 / $dibuat) : null,
            'rataRating' => ((int) ($rating->jml ?? 0)) > 0 ? (int) round((float) $rating->skor) : null,
        ];
    }

    /**
     * EKSTRA 4 — Talent Pool.
     *
     * Status efektif, bukan kolom mentah: baris AKTIF yang sudah melewati
     * Tanggal_Kedaluwarsa dihitung KEDALUWARSA (aturan yang sama dipakai
     * TalentPoolController). Membaca kolomnya saja akan melaporkan kandidat
     * kedaluwarsa sebagai stok yang masih bisa ditarik.
     */
    private function ekstraTalentPool(array $ids): array
    {
        if (! $ids) {
            return ['aktif' => 0, 'kedaluwarsa' => 0, 'ditarik' => 0, 'arsip' => 0, 'segeraHabis' => 0, 'total' => 0];
        }

        $agg = DB::table('N_WEB_CAREERS_Talent_Pool')
            ->whereIn('Program_Id', $ids)
            ->selectRaw("COUNT(*) as total,
                         SUM(CASE WHEN Status = 'AKTIF' AND (Tanggal_Kedaluwarsa IS NULL OR Tanggal_Kedaluwarsa >= CAST(GETDATE() AS date)) THEN 1 ELSE 0 END) as aktif,
                         SUM(CASE WHEN Status = 'KEDALUWARSA' OR (Status = 'AKTIF' AND Tanggal_Kedaluwarsa < CAST(GETDATE() AS date)) THEN 1 ELSE 0 END) as kedaluwarsa,
                         SUM(CASE WHEN Status = 'DITARIK' THEN 1 ELSE 0 END) as ditarik,
                         SUM(CASE WHEN Status = 'ARSIP' THEN 1 ELSE 0 END) as arsip,
                         SUM(CASE WHEN Status = 'AKTIF' AND Tanggal_Kedaluwarsa >= CAST(GETDATE() AS date)
                                   AND Tanggal_Kedaluwarsa <= DATEADD(day, 30, CAST(GETDATE() AS date)) THEN 1 ELSE 0 END) as segeraHabis")
            ->first();

        return [
            'total' => (int) ($agg->total ?? 0),
            'aktif' => (int) ($agg->aktif ?? 0),
            'kedaluwarsa' => (int) ($agg->kedaluwarsa ?? 0),
            'ditarik' => (int) ($agg->ditarik ?? 0),
            'arsip' => (int) ($agg->arsip ?? 0),
            'segeraHabis' => (int) ($agg->segeraHabis ?? 0),
        ];
    }

    // ═══════════════════════════ PEMBANTU ═══════════════════════════

    /** Tab yang boleh dilihat pengguna ini, sudah urut & berupa. */
    private function tabs(): array
    {
        $izin = AksesService::kategoriDiizinkan(self::PAGE);   // null = tak dibatasi

        $rows = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition as ta')
            ->leftJoin('N_WEB_CAREERS_Master_Kategori as mk', 'mk.Kode', '=', 'ta.Kode')
            ->where('ta.Flag_Aktif', 'Y')
            ->when($izin, fn ($q) => $q->whereIn('ta.Kode', $izin))
            ->select('ta.Kode', 'ta.Nama', 'mk.Warna')
            ->get();

        $tabs = $rows->map(fn ($r) => [
            'kode' => $r->Kode,
            'nama' => $r->Nama,
            'ikon' => self::RUPA_TAB[$r->Kode]['ikon'] ?? 'bi bi-collection-fill',
            'warna' => $r->Warna ?: (self::RUPA_TAB[$r->Kode]['warna'] ?? '#6366f1'),
        ])->values()->all();

        usort($tabs, function ($a, $b) {
            $ua = self::URUTAN_TAB[$a['kode']] ?? 99;
            $ub = self::URUTAN_TAB[$b['kode']] ?? 99;

            return $ua === $ub ? strcmp($a['nama'], $b['nama']) : $ua <=> $ub;
        });

        return $tabs;
    }

    /**
     * Kategori yang diminta, DIVALIDASI terhadap hak akses.
     *
     * Gerbangnya di sini, bukan di layar: tab yang tidak dirender masih bisa
     * diminta lewat ?kategori=, jadi kategori di luar izin harus ditolak
     * server — kalau tidak, pembatasan Hak Akses cuma kosmetik.
     */
    private function kategoriDiminta(Request $request): array
    {
        $sah = collect($this->tabs())->pluck('kode')->all();
        if (! $sah) {
            return [null, ResponseHelper::error('Tidak ada kategori program yang bisa Anda lihat.', 403)];
        }

        $kode = strtoupper(trim((string) $request->query('kategori', '')));
        if ($kode === '') {
            return [$sah[0], null];
        }
        if (! in_array($kode, $sah, true)) {
            return [null, ResponseHelper::error('Kategori di luar hak akses Anda.', 403)];
        }

        return [$kode, null];
    }

    /** Periode widget bertren: 7/30/90 hari, atau 'all' tanpa batas. */
    private function periode(Request $request): array
    {
        $p = strtolower(trim((string) $request->query('periode', '30')));
        if ($p === 'all' || $p === 'semua') {
            return ['hari' => null, 'dari' => null, 'dariSebelum' => null];
        }

        $hari = in_array((int) $p, self::PERIODE_SAH, true) ? (int) $p : 30;
        $dari = now()->copy()->subDays($hari)->startOfDay();

        return ['hari' => $hari, 'dari' => $dari, 'dariSebelum' => $dari->copy()->subDays($hari)];
    }

    /** Program satu kategori + alurnya. */
    private function programs(string $kategori)
    {
        return DB::table('N_WEB_CAREERS_Program as p')
            ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
            ->where('p.Kategori', $kategori)
            ->orderBy('p.Nama')
            ->select('p.Id_Program', 'p.Kode', 'p.Nama', 'p.Kategori', 'p.Warna', 'p.Status',
                'p.Penyelenggara', 'p.Alur_Kode', 'a.Id_Master_Alur', 'a.Nama as Alur_Nama')
            ->get();
    }

    /** SUM/COUNT sederhana per Program_Id. */
    private function jumlahPer(string $tabel, array $ids, string $agregat)
    {
        if (! $ids) {
            return collect();
        }

        return DB::table($tabel)->whereIn('Program_Id', $ids)
            ->groupBy('Program_Id')
            ->select('Program_Id', DB::raw("{$agregat} as J"))
            ->pluck('J', 'Program_Id');
    }

    /** Jumlah lamaran per program, opsional disaring status. */
    private function jumlahLamaranPer(array $ids, ?string $status)
    {
        if (! $ids) {
            return collect();
        }

        return DB::table('N_WEB_CAREERS_Lamaran')->whereIn('Program_Id', $ids)
            ->when($status, fn ($q) => $q->where('Status', $status))
            ->groupBy('Program_Id')
            ->select('Program_Id', DB::raw('COUNT(*) as J'))
            ->pluck('J', 'Program_Id');
    }

    /** Rekap lamaran per posisi (pola sama dengan MonitoringController::papan). */
    private function rekapPerPosisi(array $ids)
    {
        if (! $ids) {
            return collect();
        }

        return DB::table('N_WEB_CAREERS_Lamaran')
            ->whereIn('Program_Id', $ids)
            ->whereNotNull('Program_Posisi_Id')
            ->groupBy('Program_Posisi_Id')
            ->selectRaw("Program_Posisi_Id,
                         SUM(CASE WHEN Status = 'LULUS' THEN 1 ELSE 0 END) as lulus,
                         SUM(CASE WHEN Status = 'BERJALAN' THEN 1 ELSE 0 END) as berjalan,
                         COUNT(*) as total")
            ->get()
            ->keyBy('Program_Posisi_Id');
    }

    /**
     * Tautan ke Worklist yang sudah tersaring ke PROGRAM orang tersebut.
     *
     * Sengaja program, bukan nama pelamar: kotak cari di Worklist menyaring
     * daftar PROGRAM di panel kiri ("Cari program…"), bukan kandidat. Mengisinya
     * dengan nama orang akan mendaratkan admin di daftar kosong — lebih buruk
     * daripada tidak menautkan sama sekali. Dengan program + jenis, panel kiri
     * langsung menyisakan satu kartu dan papan kandidatnya terbuka di sebelahnya.
     */
    private function tautanWorklist(string $kategori, ?string $program): string
    {
        $q = trim((string) $program);

        return '/karir/pelamar?jenis=' . urlencode($kategori) . ($q !== '' ? '&q=' . urlencode($q) : '');
    }

    /** Satu tempat penanganan galat: dicatat lengkap, dibalas singkat. */
    private function gagal(string $bagian, ?string $kategori, \Throwable $e)
    {
        Log::channel('web_career')->error("Dashboard {$bagian} gagal (kategori: {$kategori}): " . $e->getMessage(), [
            'berkas' => $e->getFile() . ':' . $e->getLine(),
        ]);

        return ResponseHelper::error('Gagal memuat data dashboard.', 500);
    }
}
