<?php

namespace App\Http\Controllers\Career\Penjadwalan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcPenjadwalanJob;
use App\Services\WebCareers\HclClient;
use App\Support\Career\AksesService;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — PENJADWALAN.
 *
 * Semua sumber dari DB, TIDAK ADA hardcode: tab kategori dari Master Talent
 * Acquisition, program dari Program, paket/nama ujian dari HCLearn lewat
 * HclClient di sisi server.
 *
 * YANG DIJADWALKAN = TAHAP DI ALUR PROGRAM, BUKAN "JENIS TES".
 *
 * Dulu admin memilih jenis tes dari Master Jenis Tes — daftar global yang tidak
 * tahu-menahu soal alur program. Dua akibatnya: (1) jenis tes yang dipilih bisa
 * tidak ada di alur program mana pun, sehingga daftar kandidat kosong tanpa
 * sebab yang jelas; (2) tahap "Tes Online" yang Jenis Tes-nya dikosongkan di
 * Master Alur — dan builder alur memang tidak lagi menanyakannya — mustahil
 * dijadwalkan sama sekali.
 *
 * Sekarang: pilih Program → muncul tahap/aktivitas TES ONLINE milik alur program
 * itu (lengkap dengan jumlah kandidat yang menunggu) → pilih paket ujian HCLearn
 * → generate. Jenis tes turun jadi keterangan yang ikut dari alur, bukan kunci
 * pencarian. Acuannya urutan tahap + urutan sub-tes, yang stabil per alur.
 *
 * Rantai keterhubungan: Program → Alur_Kode → Master Alur → tahap yang layak
 * dijadwalkan (Provider 'THIRD_PARTY' ATAU tipe tahap ber-Perilaku 'CAT').
 *
 * Vue TIDAK PERNAH memanggil domain CAT — kredensial HMAC tetap di server.
 */
class PenjadwalanController extends Controller
{
    /** Kunci halaman — dipakai middleware DAN penyaring kategori. */
    private const PAGE = 'penjadwalanPage';

    public function __construct(private HclClient $hcl) {}

    public function index()
    {
        return Inertia::render('Career/admin/penjadwalan/penjadwalan', CareerShell::props('/karir/penjadwalan', 'Penjadwalan'));
    }

    /** Tab kategori & program — dari DB. Jenis tes TIDAK lagi dikirim (lihat tesAlur). */
    public function opsi()
    {
        try {
            // Tab HANYA kategori yang dipegang pengguna ini. Dulu seluruh isi
            // master dikirim, sehingga admin yang dijatah satu kategori tetap
            // melihat tiga tab — dan dua di antaranya selalu kosong.
            $talent = AksesService::tabKategori(self::PAGE);

            // Programnya ikut disaring. Tanpa ini tab boleh disembunyikan, tapi
            // dropdown program masih memuat program kategori lain — dan sekali
            // terpilih, kandidatnya bisa dijadwalkan tes.
            $program = DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->tap(fn ($qb) => AksesService::saringKategori($qb, self::PAGE, 'p.Kategori'))
                ->orderBy('p.Nama')
                ->get([
                    'p.Id_Program as id', 'p.Kode as kode', 'p.Nama as nama', 'p.Kategori as kategori',
                    'p.Warna as warna', 'p.Status as status', 'p.Alur_Kode as alurKode',
                    'a.Id_Master_Alur as alurId', 'a.Nama as alurNama',
                ]);

            return ResponseHelper::success(compact('talent', 'program'), 'Opsi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi penjadwalan: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi', 500);
        }
    }

    /**
     * Kode tipe yang perilakunya CAT (ujian online) — dari master, bukan hardcode.
     *
     * Dipakai untuk menyaring AKTIVITAS, bukan tahap: satu tahap boleh berisi
     * ujian online, tes manual, dan wawancara sekaligus, dan yang dijadwalkan
     * lewat HCLearn hanya yang ujian online.
     */
    /**
     * Kode penjadwalan berikutnya yang DIPASTIKAN belum dipakai.
     *
     * Bukan sekadar `max(id)+1`: id bisa terulang setelah penghapusan, dan dua
     * permintaan bersamaan bisa membaca angka yang sama. Dicari dari nomor
     * tertinggi yang ada lalu dinaikkan sampai benar-benar bebas.
     */
    private function kodeBaru(): string
    {
        $terakhir = DB::table('N_WEB_CAREERS_Penjadwalan')
            ->where('Kode', 'like', 'JDW-%')
            ->orderByDesc('Id_Penjadwalan')
            ->value('Kode');
        $n = (int) preg_replace('/\\D/', '', (string) $terakhir);

        do {
            $n++;
            $kode = 'JDW-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        } while (DB::table('N_WEB_CAREERS_Penjadwalan')->where('Kode', $kode)->exists());

        return $kode;
    }

    private function tipeCat(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->where('Perilaku_Kode', 'CAT')
            ->pluck('Kode')
            ->all();
    }

    /**
     * GET /api/v1/penjadwalan/tes?programId= — tahap/aktivitas yang bisa dijadwalkan.
     *
     * Dibaca dari ALUR SELEKSI program, jadi yang tampil di layar admin persis
     * tahap yang dilihat kandidat di portalnya. Setiap baris membawa jumlah
     * kandidat yang saat ini menunggu jadwal untuk tahap itu, supaya admin tahu
     * mana yang perlu dikerjakan tanpa harus mencoba satu per satu.
     */
    public function tesAlur(Request $request)
    {
        try {
            $programId = (int) $request->query('programId', 0);
            if (! $programId) {
                return ResponseHelper::success([], 'Pilih program terlebih dahulu.');
            }

            $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $programId)->first();
            if (! $program) {
                return ResponseHelper::error('Program tidak ditemukan.', 404);
            }

            $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
            if (! $alur) {
                return ResponseHelper::success([], "Program '{$program->Nama}' belum terhubung ke alur seleksi. Atur Alur di Program Kegiatan.");
            }

            $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alur->Id_Master_Alur)
                ->orderBy('Urutan')
                ->get();

            $subTes = $tahap->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                ->whereIn('Master_Alur_Tahap_Id', $tahap->pluck('Id_Master_Alur_Tahap')->all())
                ->orderBy('Urutan')
                ->get()
                ->groupBy('Master_Alur_Tahap_Id');

            $cat = $this->tipeCat();
            $menunggu = $this->hitungMenunggu($programId);

            $namaTipe = DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')->pluck('Nama', 'Kode');

            $daftar = [];
            // Kunci yang sudah punya baris dari master — sisanya jadi baris
            // "alur lama" di bawah, supaya tak ada rombongan yang tak terlihat.
            $terpakai = [];
            foreach ($tahap as $t) {
                $anak = collect($subTes->get($t->Id_Master_Alur_Tahap, []));
                foreach ($anak as $s) {
                    // Yang dijadwalkan lewat HCLearn hanya aktivitas yang TIPE-NYA
                    // ujian online. Aktivitas lain di tahap yang sama (tes manual,
                    // wawancara) dikerjakan tim dan hasilnya dicatat di worklist.
                    $tipeTes = $s->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode;
                    if (! in_array($tipeTes, $cat, true) && $s->Provider !== 'THIRD_PARTY') {
                        continue;
                    }

                    $kunci = self::kunciTes($t->Kode, (int) $t->Urutan, (int) $s->Urutan);
                    $terpakai[$kunci] = true;

                    $daftar[] = [
                        // KODE lebih dulu, nomor urut menyusul. Kode inilah yang
                        // dikirim balik saat admin memilih baris ini, sehingga
                        // kandidat yang diambil benar-benar yang menunggu tahap
                        // ini — bukan siapa pun yang kebetulan bernomor sama.
                        'tahapKode' => $t->Kode,
                        'tahapUrutan' => (int) $t->Urutan,
                        'tahapLabel' => $t->Label,
                        'tipe' => $tipeTes,
                        'tipeNama' => $namaTipe[$tipeTes] ?? null,
                        'tesUrutan' => (int) $s->Urutan,
                        'tesLabel' => $s->Label,
                        'peran' => $s->Peran,
                        // Tahap dengan >1 aktivitas dijadwalkan satu per satu.
                        'multi' => $anak->count() > 1,
                        'menunggu' => (int) ($menunggu[$kunci]['jml'] ?? 0),
                        'alurLain' => false,
                    ];
                }
            }

            // ── ROMBONGAN ALUR LAMA ──
            //
            // Kandidat yang masih berjalan di alur sebelumnya menunggu tahap
            // yang TIDAK ADA di alur program sekarang. Kalau daftar ini hanya
            // disusun dari master, mereka tidak punya satu baris pun untuk
            // dipilih — jadwalnya tidak akan pernah bisa dibuat, dan satu-
            // satunya petunjuk yang admin punya cuma papan yang tampak sepi.
            //
            // Barisnya disusun dari tahap kandidat itu sendiri, bukan master,
            // justru karena masternya sudah tidak memuatnya lagi.
            foreach ($menunggu as $kunci => $m) {
                if (isset($terpakai[$kunci]) || $m['jml'] < 1) {
                    continue;
                }

                $daftar[] = [
                    'tahapKode' => $m['tahapKode'],
                    'tahapUrutan' => $m['tahapUrutan'],
                    'tahapLabel' => $m['tahapLabel'],
                    'tipe' => null,
                    'tipeNama' => null,
                    'tesUrutan' => $m['tesUrutan'],
                    'tesLabel' => null,
                    'peran' => null,
                    'multi' => false,
                    'menunggu' => $m['jml'],
                    // Layar memakainya untuk menjelaskan kenapa baris ini ada:
                    // sisa kandidat dari alur sebelum program dialihkan.
                    'alurLain' => true,
                ];
            }

            return ResponseHelper::success(
                $daftar,
                $daftar
                    ? 'Tes alur dimuat'
                    : "Alur '{$alur->Nama}' belum punya tahap tes online. Tambahkan tahap bertipe Tes Online di Master Alur."
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat tes alur: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat tes pada alur program', 500);
        }
    }

    /**
     * Kandidat yang menunggu jadwal, dikelompokkan per IDENTITAS tahap.
     *
     * KENAPA PER KODE, BUKAN PER NOMOR URUT
     * Angka ini dipasangkan ke daftar tes yang disusun dari master alur. Selama
     * alur tak pernah berubah, nomor urut memang cukup. Tapi begitu program
     * diarahkan ke alur lain — atau alurnya disunting di tempat, yang memakai
     * ulang baris per urutan — "tahap ke-4" di master dan "tahap ke-4" yang
     * dijalani kandidat bisa dua hal berbeda. Akibatnya jumlah menunggu
     * menempel di baris yang salah, dan admin menjadwalkan tes untuk orang yang
     * sebenarnya menunggu tes lain.
     *
     * @return array<string, array{jml:int, tahapUrutan:int, tahapLabel:?string}>
     */
    private function hitungMenunggu(int $programId): array
    {
        return $this->kueriKandidat($programId, null, null)
            ->groupBy('t.Kode', 't.Urutan', 't.Label', 'st.Urutan')
            ->select('t.Kode as tahapKode', 't.Urutan as tahapUrutan', 't.Label as tahapLabel',
                'st.Urutan as tesUrutan', DB::raw('COUNT(*) as jml'))
            ->get()
            ->mapWithKeys(fn ($r) => [
                self::kunciTes($r->tahapKode, (int) $r->tahapUrutan, (int) $r->tesUrutan) => [
                    'jml' => (int) $r->jml,
                    'tahapUrutan' => (int) $r->tahapUrutan,
                    'tahapKode' => $r->tahapKode,
                    'tahapLabel' => $r->tahapLabel,
                    'tesUrutan' => (int) $r->tesUrutan,
                ],
            ])
            ->all();
    }

    /**
     * Kunci pemasangan daftar-tes ↔ jumlah-menunggu.
     *
     * Kode dipakai bila ada; nomor urut hanya untuk baris lama yang memang
     * tidak punya Kode. Dipisah jadi satu fungsi supaya kedua sisi pasangan
     * tidak mungkin memakai aturan yang berbeda — kalau berbeda, angkanya
     * menempel di baris yang salah dan tidak ada yang menyadarinya.
     */
    private static function kunciTes(?string $kode, int $tahapUrutan, int $tesUrutan): string
    {
        $tahap = trim((string) $kode) !== '' ? 'K:'.$kode : 'U:'.$tahapUrutan;

        return $tahap.'#'.$tesUrutan;
    }

    /** Paket / nama ujian dari HCLearn — ditampilkan sebagai kartu pilihan. */
    public function paketUjian(Request $request)
    {
        try {
            $kategoriList = $request->query('kategori') ? [$request->query('kategori')] : ['ujian', 'training'];
            $cari = $request->query('q');
            $paket = [];

            foreach ($kategoriList as $kategori) {
                $hasil = $this->hcl->get("paket-ujian/{$kategori}", array_filter(['q' => $cari]), ['Jenis_Event' => 'SINKRON_PAKET']);

                if (! $hasil['sukses']) {
                    Log::channel('web_career')->warning("Paket {$kategori} gagal dimuat: ".$hasil['message']);

                    continue;
                }

                foreach ($hasil['result'] ?? [] as $p) {
                    $p['Kategori_Hclearn'] = $kategori;
                    $paket[] = $p;
                }
            }

            return ResponseHelper::success($paket, 'Paket tes dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat paket tes: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat paket tes dari HCLearn', 500);
        }
    }

    /**
     * Kandidat yang layak dijadwalkan untuk SATU TAHAP (dan sub-tes) di alur.
     *
     * Acuannya SUB-TES, bukan tahap. Satu tahap bisa memuat beberapa tes
     * sekaligus — mis. tahap "FGD Dan Wawancara HR" berisi Psikotes 2 + DISC +
     * Wawancara. Ketiganya dijadwalkan SENDIRI-SENDIRI, bisa di hari berbeda,
     * dan kandidat yang sama muncul lagi untuk tes berikutnya selama sub-tes itu
     * belum punya jadwal.
     *
     * Penyaringnya URUTAN tahap + urutan sub-tes, bukan Jenis_Tes_Kode. Jenis tes
     * boleh kosong (tahap "Tes Online" yang paketnya baru dipilih di sini), jadi
     * memakainya sebagai kunci berarti tahap seperti itu tak pernah ketemu.
     *
     * Sub-tes ikut disaring: hanya yang butuh jadwal pihak ke-3, belum selesai,
     * dan belum tertaut jadwal. Yang sudah dijadwalkan sengaja disembunyikan
     * supaya tidak terkirim dua kali ke HCLearn; batalkan jadwalnya untuk mengulang.
     */
    public function kandidat(Request $request)
    {
        try {
            $programId = (int) $request->query('programId', 0);
            $tahapUrutan = (int) $request->query('tahapUrutan', 0) ?: null;
            $tesUrutan = (int) $request->query('tesUrutan', 0) ?: null;
            $tahapKode = trim((string) $request->query('tahapKode', '')) ?: null;
            $cari = trim((string) $request->query('q', ''));

            if (! $programId) {
                return ResponseHelper::success([], 'Pilih program terlebih dahulu.');
            }
            if (! $tahapUrutan && ! $tahapKode) {
                return ResponseHelper::success([], 'Pilih tes/tahap yang mau dijadwalkan.');
            }

            $rows = $this->kueriKandidat($programId, $tahapUrutan, $tesUrutan, $cari, $tahapKode)
                ->orderBy('u.Nama')
                // Angka yang sama dengan batas validasi penjadwalan — daftar
                // tidak boleh menawarkan orang yang nanti ditolak saat dikirim.
                ->limit((int) config('hclearn.maks_peserta', 1000))
                ->get([
                    'l.Kode as kode', 'u.Nama as nama', 'u.No_Hp as hp', 'u.Email as email',
                    'pos.Posisi as posisi', 't.Label as tahap', 't.Urutan as urutanTahap',
                    'st.Label as tes', 'st.Urutan as urutanTes',
                ])
                ->map(fn ($r) => [
                    'kode' => $r->kode,
                    'nama' => $r->nama,
                    'hp' => $r->hp,
                    'email' => $r->email,
                    'posisi' => $r->posisi,
                    'tahap' => $r->tahap,
                    // Tes mana di dalam tahap itu — penting saat satu tahap berisi
                    // beberapa tes, supaya admin tahu yang mana sedang dijadwalkan.
                    'tes' => $r->tes,
                    'urutanTes' => (int) $r->urutanTes,
                ])
                ->values();

            return ResponseHelper::success(
                $rows,
                $rows->isEmpty() ? $this->alasanKosong($programId, $tahapUrutan, $tesUrutan, $tahapKode) : 'Kandidat dimuat'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kandidat: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat kandidat', 500);
        }
    }

    /** Kueri dasar kandidat: sub-tes yang butuh jadwal pihak ke-3 & belum punya. */
    /**
     * @param  string|null  $tahapKode  identitas tahap. Bila diisi, INI yang
     *                                  dipakai menyaring dan $tahapUrutan cuma
     *                                  cadangan untuk baris lama tanpa Kode.
     */
    private function kueriKandidat(int $programId, ?int $tahapUrutan, ?int $tesUrutan, string $cari = '', ?string $tahapKode = null)
    {
        $cat = $this->tipeCat();

        return DB::table('N_WEB_CAREERS_Lamaran as l')
            // Tahap SAAT INI kandidat = baris tahap dengan Urutan = Lamaran.Urutan_Tahap.
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', fn ($j) => $j
                ->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')
                ->on('t.Urutan', '=', 'l.Urutan_Tahap'))
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as st', 'st.Lamaran_Tahap_Id', '=', 't.Id_Lamaran_Tahap')
            ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
            ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
            ->where('l.Program_Id', $programId)
            ->where('l.Status', 'BERJALAN')
            ->where('t.Status', 'BERJALAN')
            // Butuh jadwal HCLearn bila TIPE AKTIVITAS itu ujian online. Aktivitas
            // manual di tahap yang sama (tes tulis, wawancara) sengaja tidak ikut:
            // tak ada ujian CAT yang bisa dikirim untuknya. Provider dipakai
            // sebagai cadangan untuk baris lama yang tipenya belum terisi.
            ->where(fn ($q) => $q
                ->where('st.Provider', 'THIRD_PARTY')
                ->when($cat, fn ($w) => $w->orWhereIn(DB::raw('COALESCE(st.Tipe_Tahap_Kode, t.Tipe_Tahap_Kode)'), $cat)))
            ->where('st.Flag_Selesai', 'N')
            ->whereNull('st.Penjadwalan_Tahap_Id')
            // IDENTITAS DULU. Kode tahap menyaring orang yang benar-benar
            // menunggu tahap ini; nomor urut hanya dipakai bila tak ada Kode
            // (baris pra-mesin), karena nomor bisa menunjuk tahap yang sama
            // sekali lain setelah alur disunting atau diganti.
            ->when($tahapKode, fn ($q) => $q->where('t.Kode', $tahapKode))
            ->when(! $tahapKode && $tahapUrutan, fn ($q) => $q->where('t.Urutan', $tahapUrutan))
            ->when($tesUrutan, fn ($q) => $q->where('st.Urutan', $tesUrutan))
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('u.Nama', 'like', "%{$cari}%")
                    ->orWhere('l.Kode', 'like', "%{$cari}%")
                    ->orWhere('pos.Posisi', 'like', "%{$cari}%");
            }));
    }

    /**
     * Kenapa daftarnya kosong.
     *
     * Daftar kosong tanpa penjelasan adalah keluhan nyata: admin melihat nol
     * kandidat dan tidak tahu apakah datanya belum ada, tahapnya belum sampai,
     * atau semuanya memang sudah dijadwalkan. Ditelusuri bertingkat dari yang
     * paling umum ke paling khusus.
     */
    private function alasanKosong(int $programId, ?int $tahapUrutan, ?int $tesUrutan, ?string $tahapKode = null): string
    {
        $berjalan = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Program_Id', $programId)->where('Status', 'BERJALAN')->count();
        if ($berjalan === 0) {
            return 'Belum ada pelamar berjalan di program ini.';
        }

        // Abaikan saringan tahap → apakah ada yang menunggu jadwal sama sekali?
        $tanpaTahap = $this->kueriKandidat($programId, null, null)->count();
        if ($tanpaTahap > 0 && ($tahapUrutan || $tahapKode)) {
            return "Ada {$tanpaTahap} kandidat menunggu jadwal, tetapi bukan di tahap ini. Pilih tes/tahap lain di daftar sebelah.";
        }

        // Sudah dijadwalkan semua?
        $terjadwal = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', fn ($j) => $j
                ->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')->on('t.Urutan', '=', 'l.Urutan_Tahap'))
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as st', 'st.Lamaran_Tahap_Id', '=', 't.Id_Lamaran_Tahap')
            ->where('l.Program_Id', $programId)->where('l.Status', 'BERJALAN')
            ->whereNotNull('st.Penjadwalan_Tahap_Id')
            ->when($tahapKode, fn ($q) => $q->where('t.Kode', $tahapKode))
            ->when(! $tahapKode && $tahapUrutan, fn ($q) => $q->where('t.Urutan', $tahapUrutan))
            ->when($tesUrutan, fn ($q) => $q->where('st.Urutan', $tesUrutan))
            ->count();
        if ($terjadwal > 0) {
            return "Semua kandidat untuk tes ini sudah dijadwalkan ({$terjadwal}). Batalkan jadwalnya bila ingin mengulang.";
        }

        // Tahap yang sedang dijalani pelamar bukan tahap yang dipilih.
        $tahapSekarang = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', fn ($j) => $j
                ->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')->on('t.Urutan', '=', 'l.Urutan_Tahap'))
            ->where('l.Program_Id', $programId)->where('l.Status', 'BERJALAN')
            ->select('t.Label', DB::raw('COUNT(*) as n'))
            ->groupBy('t.Label')->orderByDesc(DB::raw('COUNT(*)'))->limit(3)->get();

        $ringkas = $tahapSekarang->map(fn ($x) => "{$x->Label} ({$x->n})")->implode(', ');

        return "Belum ada pelamar yang sampai di tahap ini. Saat ini mereka di: {$ringkas}.";
    }

    /**
     * GET /api/v1/penjadwalan — DAFTAR PROGRAM YANG PUNYA JADWAL.
     *
     * ══ SATU PROGRAM = SATU BARIS ═════════════════════════════════════════
     *
     * Dulu satu baris = satu penjadwalan. Menjadwalkan 42 orang dalam tiga
     * gelombang melahirkan tiga kartu berjudul sama persis — "Seleksi MT Tahap
     * 1 — EVO MANAGEMENT TRAINEE" — yang hanya bisa dibedakan lewat kode
     * JDW-00xx di pojok. Untuk menjawab "si A dapat token belum?", admin harus
     * membuka kartu satu per satu sampai ketemu, dan tidak pernah tahu pasti
     * kartu mana yang belum diperiksa.
     *
     * Pengelompokan sekarang mengikuti pertanyaan yang sebenarnya diajukan
     * orang: bukan "gelombang ke berapa", melainkan "siapa saja di program
     * ini". Gelombangnya tidak hilang — ia turun menjadi kolom SESI di dalam,
     * dan bisa disaring lewat tanggal seperti atribut lainnya.
     *
     * Tetap ringan: baris ini cuma agregat. Pesertanya diambil terpisah saat
     * akordion dibuka (lihat `pesertaProgram()`), sudah terpaginasi di server.
     */
    public function list(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $programId = (int) $request->query('programId', 0);
            $status = trim((string) $request->query('status', ''));
            $perPage = min(max((int) $request->query('perPage', 10), 1), 50);
            $page = max((int) $request->query('page', 1), 1);

            $dasar = DB::table('N_WEB_CAREERS_Penjadwalan as j')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'j.Program_Id')
                ->when($programId, fn ($x) => $x->where('j.Program_Id', $programId))
                ->when($status !== '', fn ($x) => $x->where('j.Status', $status))
                ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                    $w->where('j.Kode', 'like', "%{$q}%")
                        ->orWhere('j.Nama', 'like', "%{$q}%")
                        ->orWhere('p.Nama', 'like', "%{$q}%");
                }));

            // Jumlah PROGRAM, bukan jumlah penjadwalan — itu yang dipaginasi.
            $total = (clone $dasar)->distinct()->count('j.Program_Id');

            $rows = (clone $dasar)
                ->groupBy('j.Program_Id', 'p.Nama', 'p.Kategori')
                ->select(
                    'j.Program_Id',
                    'p.Nama as ProgramNama',
                    'p.Kategori as ProgramKategori',
                    DB::raw('COUNT(*) as JumlahSesi'),
                    DB::raw('SUM(j.Jumlah_Peserta) as JumlahPeserta'),
                    // Sesi terbaru menentukan urutan: program yang baru
                    // dijadwalkan naik ke atas, sesuai "terbaru ke terlama".
                    DB::raw('MAX(j.Id_Penjadwalan) as SesiTerbaru'),
                    DB::raw('MAX(j.Created_At) as DibuatTerakhir')
                )
                ->orderByDesc(DB::raw('MAX(j.Id_Penjadwalan)'))
                ->forPage($page, $perPage)
                ->get();

            $programIds = $rows->pluck('Program_Id')->filter()->all();

            // ── SESI di dalam tiap program ───────────────────────────────────
            //
            // Ikut dibawa karena dua hal hanya bisa dilakukan per sesi: "Coba
            // Lagi" dan pembacaan jendela bawaan. Yang dimuat hanya program
            // yang benar-benar tampil di halaman ini.
            $sesi = ! $programIds ? collect() : DB::table('N_WEB_CAREERS_Penjadwalan as j')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'j.Created_By_Id')
                ->whereIn('j.Program_Id', $programIds)
                ->when($status !== '', fn ($x) => $x->where('j.Status', $status))
                ->orderByDesc('j.Id_Penjadwalan')
                ->select('j.*', 'u.Nama as Pembuat')
                ->get();

            $tahap = $sesi->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                ->whereIn('Penjadwalan_Id', $sesi->pluck('Id_Penjadwalan')->all())
                ->whereNotNull('Nama_Ujian')
                ->get()->keyBy('Penjadwalan_Id');

            // Rangkaian tahap alur — diambil dari sesi TERBARU tiap program.
            // Isinya sama di seluruh sesi program yang sama (alurnya satu), jadi
            // memuat semuanya cuma menyalin hal yang sama berkali-kali.
            $sesiTerbaru = $sesi->groupBy('Program_Id')->map(fn ($g) => $g->first()->Id_Penjadwalan);
            $alurTahap = $sesiTerbaru->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                ->whereIn('Penjadwalan_Id', $sesiTerbaru->values()->all())
                ->orderBy('Urutan')->get()->groupBy('Penjadwalan_Id');

            // Berapa peserta yang tokennya BELUM terbit — inilah yang menentukan
            // apakah "Coba Lagi" pantas ditawarkan pada sesi itu.
            $menunggu = $sesi->isEmpty() ? collect() : DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Penjadwalan_Id', $sesi->pluck('Id_Penjadwalan')->all())
                ->whereNull('Short_Token')
                ->groupBy('Penjadwalan_Id')
                ->select('Penjadwalan_Id', DB::raw('COUNT(*) as jml'))
                ->pluck('jml', 'Penjadwalan_Id');

            $sesiPer = $sesi->groupBy('Program_Id');

            $data = $rows->map(function ($r) use ($sesiPer, $tahap, $menunggu, $sesiTerbaru, $alurTahap) {
                $daftarSesi = collect($sesiPer->get($r->Program_Id, []));
                $t = fn ($j) => $tahap->get($j->Id_Penjadwalan);
                $langkah = collect($alurTahap->get($sesiTerbaru->get($r->Program_Id), []));

                return [
                    // Kunci barisnya kini PROGRAM, bukan penjadwalan.
                    'id' => Hashids::encode((int) $r->Program_Id),
                    'programId' => (int) $r->Program_Id,
                    'program' => $r->ProgramNama,
                    'kategori' => $r->ProgramKategori,
                    'jumlahSesi' => (int) $r->JumlahSesi,
                    'jumlahPeserta' => (int) $r->JumlahPeserta,
                    'createdAt' => $r->DibuatTerakhir,
                    // Status program = keadaan paling perlu diketahui di antara
                    // sesinya. GAGAL menang atas apa pun: satu gelombang yang
                    // gagal tidak boleh tersamar oleh dua yang berhasil.
                    'status' => $daftarSesi->contains('Status', 'GAGAL') ? 'GAGAL'
                        : ($daftarSesi->contains('Status', 'DIANTRIKAN') ? 'DIANTRIKAN'
                            : ($daftarSesi->first()->Status ?? 'BERJALAN')),
                    'catatan' => optional($daftarSesi->firstWhere('Catatan', '!=', null))->Catatan,
                    // Rangkaian tahap seleksi program — konteks yang membuat
                    // "Psikotes (Tahap 1)" terbaca sebagai langkah ke-2 dari 7,
                    // bukan sekadar nama aktivitas.
                    'tahap' => $langkah->map(fn ($t) => [
                        'urutan' => (int) $t->Urutan,
                        'kode' => $t->Kode,
                        'label' => $t->Label,
                        'kirimHclearn' => $t->Flag_Kirim_Hclearn === 'Y',
                        'namaUjian' => $t->Nama_Ujian,
                        'status' => $t->Status,
                    ])->values(),
                    'sesi' => $daftarSesi->map(fn ($j) => [
                        'id' => Hashids::encode($j->Id_Penjadwalan),
                        'kode' => $j->Kode,
                        'nama' => $j->Nama,
                        'status' => $j->Status,
                        'jumlahPeserta' => (int) $j->Jumlah_Peserta,
                        'menunggu' => (int) ($menunggu[$j->Id_Penjadwalan] ?? 0),
                        'paketUjian' => optional($t($j))->Nama_Ujian,
                        'aktivitas' => optional($t($j))->Label,
                        'waktuMulai' => optional($t($j))->Waktu_Mulai,
                        'waktuAkhir' => optional($t($j))->Waktu_Akhir,
                        'catatan' => $j->Catatan,
                        'createdBy' => $j->Pembuat ?: $j->Created_By,
                        'createdAt' => $j->Created_At,
                    ])->values(),
                ];
            })->values();

            return ResponseHelper::success([
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPage' => (int) ceil($total / $perPage),
            ], 'Data penjadwalan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat penjadwalan: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data penjadwalan', 500);
        }
    }

    /**
     * GET /api/v1/penjadwalan/{id}/peserta — isi satu baris akordion.
     *
     * Dipisah dari `list()` supaya daftar tetap ringan: peserta baru dikueri
     * ketika barisnya benar-benar dibuka admin. TOKEN & OTP ikut di sini —
     * keduanya yang dipakai kandidat untuk masuk ke ujian, jadi admin harus
     * bisa menyalinnya saat kandidat mengaku tidak menerima email.
     */
    public function peserta(string $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('Penjadwalan tidak valid.', 422);
            }

            $rows = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
                ->leftJoin('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'ps.Penjadwalan_Tahap_Id')
                ->where('ps.Penjadwalan_Id', $realId)
                ->orderBy('ps.Nama')
                ->select('ps.*', 'pt.Waktu_Mulai as Jendela_Tahap_Mulai', 'pt.Waktu_Akhir as Jendela_Tahap_Akhir')
                ->get()
                ->map(fn ($p) => [
                    'id' => Hashids::encode($p->Id_Penjadwalan_Peserta),
                    'kode' => $p->Kode_Peserta,
                    'nama' => $p->Nama,
                    'posisi' => $p->Posisi_Dilamar,
                    'email' => $p->Email,
                    'linkUjian' => $p->Link_Ujian,
                    'shortToken' => $p->Short_Token,
                    'otp' => $p->Akses_OTP,
                    'pesanError' => $p->Pesan_Error,
                    // Jendela yang BERLAKU untuk orang ini: miliknya sendiri bila
                    // pernah digeser, selain itu ikut jendela tahap.
                    'waktuMulai' => $p->Waktu_Mulai ?: $p->Jendela_Tahap_Mulai,
                    'waktuAkhir' => $p->Waktu_Akhir ?: $p->Jendela_Tahap_Akhir,
                    'jadwalSendiri' => (bool) $p->Waktu_Mulai,
                    // Tanpa pengenal sesi HCLearn, jadwalnya tak bisa diubah.
                    'dapatDiubah' => (bool) $p->Ref_Ujian_Token,
                    // SIAPA yang menjadwalkan ORANG INI. Melekat di peserta, bukan
                    // di penjadwalannya: satu angkatan bisa disusun banyak perekrut,
                    // dan jadwal tiap orang bisa digeser belakangan oleh orang lain.
                    // Saat ada jadwal keliru, yang dicari adalah penanggung jawab
                    // baris itu — bukan siapa pun yang kebetulan menekan Generate.
                    'olehNama' => $p->Created_By,
                    'olehPada' => $p->Created_At,
                    // Digeser belakangan → tampilkan siapa yang menggesernya.
                    'ubahNama' => $p->Waktu_Mulai ? $p->Updated_By : null,
                    'ubahPada' => $p->Waktu_Mulai ? $p->Updated_At : null,
                    // Jadwal terkunci begitu ujiannya mulai dikerjakan.
                    'terkunci' => in_array($p->Status_Pengerjaan, ['mengerjakan', 'selesai', 'timeout'], true) || $p->Flag_Selesai === 'Y',
                ])->values();

            return ResponseHelper::success($rows, 'Peserta dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat peserta penjadwalan #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memuat peserta', 500);
        }
    }

    /**
     * KUNCI ISIAN FORMULIR yang berarti "nama kampus / sekolah asal".
     *
     * Lebih dari satu karena formulir berganti nama field beberapa kali dan
     * pengisian lama tidak ikut berubah — di data yang ada sekarang keempatnya
     * benar-benar terpakai. Menyebut satu saja berarti pelamar angkatan
     * sebelumnya tampil tanpa kampus: bukan galat, cuma kolom kosong yang tak
     * seorang pun tahu sebabnya.
     *
     * URUTANNYA BERARTI — yang di depan menang (lihat COALESCE di subKampus).
     *
     * `institusi` / `jenis_institusi` SENGAJA TIDAK di sini: itu JENIS
     * institusinya (Universitas, Politeknik, SMA), bukan namanya. Memasukkannya
     * akan mengisi kolom kampus dengan kata "Universitas" untuk ratusan orang.
     */
    private const KUNCI_KAMPUS = ['nama_kampus', 'kampus', 'perguruan_tinggi', 'nama_institusi', 'asal_sekolah'];

    /**
     * Kampus tiap lamaran, dibaca dari jawaban formulir — BUKAN dari master.
     *
     * ══ KENAPA BUKAN MASTER KAMPUS ═══════════════════════════════════════
     *
     * Master tidak memuat semuanya: sebagian pelamar mengetik sendiri nama
     * kampusnya, dan yang diketik itulah kenyataan yang dipakai merekrut.
     * Menjodohkannya ke master lebih dulu akan membuang persis orang-orang
     * yang paling perlu dilihat.
     *
     * ══ KENAPA BUKAN Formulir_Jawaban_Index ══════════════════════════════
     *
     * Tabel itu tampak menggoda — sudah berbentuk kolom, tinggal difilter.
     * Tapi `LamaranService::proyeksikan()` hanya menuliskan field yang DIRUJUK
     * ATURAN SYARAT plus field turunan. Program yang syaratnya tidak menyebut
     * kampus tidak akan punya barisnya sama sekali. Hasilnya bukan galat,
     * melainkan kolom kosong yang menyesatkan: tampak seperti pelamar tidak
     * mengisi, padahal datanya ada di formulir.
     *
     * Jadi dibaca dari sumber aslinya. SQL Server di sini versi 16 (compat
     * 160), JSON_VALUE tersedia sejak 2016 — jadi penyaringan tetap terjadi
     * di database, bukan setelah ribuan baris ditarik ke PHP.
     *
     * Satu lamaran bisa mengisi beberapa formulir. Yang menang adalah
     * PENGISIAN TERBARU, aturan yang sama dengan MonitoringController.
     */
    private function subKampus()
    {
        $nilai = 'COALESCE('.implode(', ', array_map(
            fn ($k) => "JSON_VALUE(fp.Jawaban_Json, '$.".$k."')",
            self::KUNCI_KAMPUS
        )).')';

        return DB::table(DB::raw(
            '(SELECT fp.Lamaran_Id, '.$nilai.' AS Kampus,
                     ROW_NUMBER() OVER (PARTITION BY fp.Lamaran_Id
                                        ORDER BY fp.Id_Formulir_Pengisian DESC) AS Urut
                FROM N_WEB_CAREERS_Formulir_Pengisian fp
               WHERE fp.Jawaban_Json IS NOT NULL
                 AND LTRIM(RTRIM(COALESCE('.$nilai.", ''))) <> '') AS kx"
        ))
            ->where('kx.Urut', 1)
            ->select('kx.Lamaran_Id', 'kx.Kampus');
    }

    /**
     * GET /api/v1/penjadwalan/program/{id}/peserta — isi akordion satu program.
     *
     * SELURUH peserta program ini, lintas gelombang, dengan penyaring di
     * SERVER. Dulu daftarnya dimuat utuh lalu dipotong di browser: untuk satu
     * program berisi seribu pelamar, itu berarti seribu baris dikirim setiap
     * kali akordion dibuka, hanya untuk menampilkan dua puluh.
     *
     * Penyaringnya tiga, dan ketiganya menjawab pertanyaan nyata:
     *   tanggal   gelombang mana — menggantikan kartu terpisah yang dihapus;
     *   kampus    dari mana orangnya, diambil dari isian formulir apa adanya;
     *   cari      nama / kode / posisi / kampus sekaligus.
     */
    public function pesertaProgram(Request $request, string $id)
    {
        try {
            $programId = Hashids::decode($id)[0] ?? null;
            if (! $programId) {
                return ResponseHelper::error('Program tidak valid.', 422);
            }

            $q = trim((string) $request->query('q', ''));
            $kampus = trim((string) $request->query('kampus', ''));
            $sesi = trim((string) $request->query('sesi', ''));
            $dari = trim((string) $request->query('dari', ''));
            $sampai = trim((string) $request->query('sampai', ''));
            $perPage = min(max((int) $request->query('perPage', 20), 1), 100);
            $page = max((int) $request->query('page', 1), 1);

            // Jendela yang BERLAKU untuk orang ini: miliknya sendiri bila pernah
            // digeser, selain itu ikut jendela tahap. Penyaring tanggal harus
            // menilai yang berlaku — bukan yang bawaan — kalau tidak, peserta
            // yang jadwalnya digeser akan hilang dari rentang yang benar.
            $jendelaMulai = 'COALESCE(ps.Waktu_Mulai, pt.Waktu_Mulai)';

            $dasar = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
                ->join('N_WEB_CAREERS_Penjadwalan as j', 'j.Id_Penjadwalan', '=', 'ps.Penjadwalan_Id')
                ->leftJoin('N_WEB_CAREERS_Penjadwalan_Tahap as pt', 'pt.Id_Penjadwalan_Tahap', '=', 'ps.Penjadwalan_Tahap_Id')
                ->leftJoinSub($this->subKampus(), 'kp', 'kp.Lamaran_Id', '=', 'ps.Lamaran_Id')
                ->where('j.Program_Id', $programId)
                ->when($sesi !== '', fn ($x) => $x->where('j.Kode', $sesi))
                ->when($kampus !== '', fn ($x) => $x->where('kp.Kampus', $kampus))
                ->when($dari !== '', fn ($x) => $x->whereRaw("{$jendelaMulai} >= ?", [$dari.' 00:00:00']))
                ->when($sampai !== '', fn ($x) => $x->whereRaw("{$jendelaMulai} <= ?", [$sampai.' 23:59:59']))
                ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                    $w->where('ps.Nama', 'like', "%{$q}%")
                        ->orWhere('ps.Kode_Peserta', 'like', "%{$q}%")
                        ->orWhere('ps.Email', 'like', "%{$q}%")
                        ->orWhere('ps.Posisi_Dilamar', 'like', "%{$q}%")
                        ->orWhere('kp.Kampus', 'like', "%{$q}%")
                        ->orWhere('j.Kode', 'like', "%{$q}%");
                }));

            $total = (clone $dasar)->count();

            $rows = (clone $dasar)
                // Terbaru ke terlama: gelombang paling akhir di atas, dan di
                // dalam satu gelombang diurutkan nama supaya orang bisa dicari
                // dengan mata, bukan hanya dengan kotak pencarian.
                ->orderByDesc('j.Id_Penjadwalan')
                ->orderBy('ps.Nama')
                ->forPage($page, $perPage)
                ->select(
                    'ps.*',
                    'j.Kode as SesiKode',
                    'j.Id_Penjadwalan as SesiId',
                    'pt.Label as AktivitasLabel',
                    'pt.Nama_Ujian as PaketUjian',
                    'pt.Waktu_Mulai as Jendela_Tahap_Mulai',
                    'pt.Waktu_Akhir as Jendela_Tahap_Akhir',
                    'kp.Kampus as Kampus'
                )
                ->get()
                ->map(fn ($p) => [
                    'id' => Hashids::encode($p->Id_Penjadwalan_Peserta),
                    'kode' => $p->Kode_Peserta,
                    'nama' => $p->Nama,
                    'posisi' => $p->Posisi_Dilamar,
                    'email' => $p->Email,
                    // Apa adanya dari isian pelamar; kosong bila memang tak diisi.
                    'kampus' => $p->Kampus,
                    'sesi' => $p->SesiKode,
                    'sesiId' => Hashids::encode($p->SesiId),
                    'aktivitas' => $p->AktivitasLabel,
                    'paketUjian' => $p->PaketUjian,
                    'linkUjian' => $p->Link_Ujian,
                    'shortToken' => $p->Short_Token,
                    'otp' => $p->Akses_OTP,
                    'pesanError' => $p->Pesan_Error,
                    'waktuMulai' => $p->Waktu_Mulai ?: $p->Jendela_Tahap_Mulai,
                    'waktuAkhir' => $p->Waktu_Akhir ?: $p->Jendela_Tahap_Akhir,
                    'jadwalSendiri' => (bool) $p->Waktu_Mulai,
                    'dapatDiubah' => (bool) $p->Ref_Ujian_Token,
                    'olehNama' => $p->Created_By,
                    'olehPada' => $p->Created_At,
                    'ubahNama' => $p->Waktu_Mulai ? $p->Updated_By : null,
                    'ubahPada' => $p->Waktu_Mulai ? $p->Updated_At : null,
                    'terkunci' => in_array($p->Status_Pengerjaan, ['mengerjakan', 'selesai', 'timeout'], true) || $p->Flag_Selesai === 'Y',
                ])->values();

            // ── PILIHAN PENYARING, diturunkan dari data yang benar-benar ada ──
            //
            // Bukan dari master, dan bukan daftar tetap. Program yang pelamarnya
            // dari tujuh kampus menawarkan tujuh — tidak lebih, tidak kurang.
            // Diambil dari SELURUH program (tanpa penyaring aktif) supaya
            // pilihannya tidak menyusut jadi hanya yang sedang tersaring.
            $kampusOpsi = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta as ps')
                ->join('N_WEB_CAREERS_Penjadwalan as j', 'j.Id_Penjadwalan', '=', 'ps.Penjadwalan_Id')
                ->joinSub($this->subKampus(), 'kp', 'kp.Lamaran_Id', '=', 'ps.Lamaran_Id')
                ->where('j.Program_Id', $programId)
                ->groupBy('kp.Kampus')
                ->orderBy('kp.Kampus')
                ->pluck('kp.Kampus')
                ->filter()
                ->values();

            return ResponseHelper::success([
                'data' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPage' => (int) ceil($total / $perPage),
                'kampusOpsi' => $kampusOpsi,
            ], 'Peserta dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memuat peserta program #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memuat peserta', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'programId' => 'required|integer',
                // Tahap yang dijadwalkan — acuan urutan di alur program, bukan
                // jenis tes. tesUrutan hanya perlu bila tahapnya multi-aktivitas.
                'tahapUrutan' => 'required|integer|min:1',
                // Identitas tahap — dikirim layar bersama nomornya. Nullable
                // supaya permintaan lama (tanpa kode) tetap dilayani lewat
                // nomor urut, bukan ditolak mentah.
                'tahapKode' => 'nullable|string|max:30',
                'tesUrutan' => 'nullable|integer|min:1',
                // HCLearn memberi pengenal paket berupa STRING TERENKRIPSI 64
                // karakter, bukan angka — jadi jangan divalidasi numeric, dan
                // jangan dijejalkan ke kolom int (lihat Ref_Master_Ujian).
                'idMasterUjian' => 'required|string|max:200',
                'namaUjian' => 'required|string|max:255',
                'waktuMulai' => 'required|date',
                'waktuAkhir' => 'required|date|after:waktuMulai',
                // Batasnya satu angka, dipakai bersama daftar kandidat, supaya
                // layar tidak pernah menawarkan lebih banyak orang daripada
                // yang boleh dikirim.
                'peserta' => 'required|array|min:1|max:'.(int) config('hclearn.maks_peserta', 1000),
                'peserta.*' => 'required|string|max:20',
            ], [
                'tahapUrutan.required' => 'Pilih dulu tes/tahap yang mau dijadwalkan.',
                // Pesan bawaan Laravel menyebut nama field mentah ("waktuAkhir
                // harus setelah waktuMulai") — bahasa mesin di layar admin.
                // Dua endpoint pengubahan jadwal sudah punya pesan ini; yang
                // pembuatan tertinggal, padahal justru di sinilah jendela
                // terbalik paling sering lahir.
                'waktuAkhir.after' => 'Waktu berakhir harus setelah waktu mulai.',
            ]);

            $program = DB::table('N_WEB_CAREERS_Program')->where('Id_Program', $data['programId'])->first();
            if (! $program) {
                return ResponseHelper::error('Program tidak ditemukan.', 422);
            }

            $alur = DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $program->Alur_Kode)->first();
            if (! $alur) {
                return ResponseHelper::error("Program '{$program->Nama}' belum terhubung ke alur seleksi. Atur Alur di Program Kegiatan.", 422);
            }

            $semuaTahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Master_Alur_Id', $alur->Id_Master_Alur)
                ->orderBy('Urutan')
                ->get();

            // Tahap yang dijadwalkan dikenali dari KODE-nya lebih dulu.
            //
            // Dulu hanya nomor urut yang dipakai, dan itu memercayai satu hal
            // yang tidak selalu benar: bahwa "tahap ke-N di master" sama dengan
            // "tahap ke-N yang dipilih admin di layar". Begitu alur disunting —
            // MasterAlurController sengaja memakai ULANG baris per urutan agar
            // lamaran berjalan tak kehilangan rujukan — nomor yang sama bisa
            // menunjuk tahap yang lain, dan jadwal terkirim untuk tes yang
            // bukan itu.
            $tahapKode = trim((string) ($data['tahapKode'] ?? ''));
            $tahapTes = $tahapKode !== ''
                ? $semuaTahap->firstWhere('Kode', $tahapKode)
                : null;
            $tahapTes ??= $semuaTahap->firstWhere('Urutan', $data['tahapUrutan']);

            if (! $tahapTes) {
                $sebut = $tahapKode !== '' ? "tahap '{$tahapKode}'" : "tahap ke-{$data['tahapUrutan']}";

                return ResponseHelper::error("Alur '{$alur->Nama}' tidak punya {$sebut}. Muat ulang halaman — alurnya mungkin baru berubah.", 422);
            }

            // Sub-tes yang dijadwalkan (tahap multi-aktivitas dijadwalkan satu per satu).
            $subTes = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')
                ->where('Master_Alur_Tahap_Id', $tahapTes->Id_Master_Alur_Tahap)
                ->when($data['tesUrutan'] ?? null, fn ($q, $u) => $q->where('Urutan', $u))
                ->orderBy('Urutan')
                ->first();

            // Layak dijadwalkan = TIPE AKTIVITAS itu ujian online. Menolak di sini
            // penting: tanpa gerbang ini, wawancara di dalam tahap tes ikut
            // dikirim ke HCLearn dan kandidat menerima token untuk sesi tatap muka.
            $tipeTes = ($subTes->Tipe_Tahap_Kode ?? null) ?: $tahapTes->Tipe_Tahap_Kode;
            $layak = in_array($tipeTes, $this->tipeCat(), true)
                || ($subTes && $subTes->Provider === 'THIRD_PARTY');
            if (! $layak) {
                $nama = $subTes->Label ?? $tahapTes->Label;

                return ResponseHelper::error("Aktivitas '{$nama}' bukan ujian online — dilaksanakan tim rekrutmen dan hasilnya dicatat di Worklist, bukan dijadwalkan lewat HCLearn.", 422);
            }

            $tesUrutan = $subTes->Urutan ?? null;

            // Peserta = lamaran NYATA (by Kode) pada program ini. Bukan lagi HRIS dummy.
            // Kode_Calon (WCyymmdd-xxxxxx) = identitas peserta di HCLearn (HRIS_Rekrutmen).
            //
            // Dipecah per 500 kode: SQL Server hanya menerima 2100 parameter
            // dalam satu perintah, dan `whereIn` menghabiskan satu parameter
            // per kode. Sekali penjadwalan menyentuh angka itu, yang muncul
            // bukan hasil yang salah melainkan lemparan mentah dari driver.
            $kandidat = collect($data['peserta'])->chunk(500)
                ->flatMap(fn ($sepotong) => DB::table('N_WEB_CAREERS_Lamaran as l')
                    ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                    ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                    ->whereIn('l.Kode', $sepotong->values()->all())
                    ->where('l.Program_Id', $program->Id_Program)
                    ->get(['l.Kode', 'l.Id_Lamaran', 'u.Id_Users', 'u.Kode_Calon', 'u.Nama', 'u.Email', 'u.No_Hp', 'pos.Posisi']))
                ->keyBy('Kode');
            $tidakDikenal = array_diff($data['peserta'], $kandidat->keys()->all());
            if ($tidakDikenal) {
                return ResponseHelper::error('Kandidat tidak dikenal / bukan pelamar program ini: '.implode(', ', array_slice($tidakDikenal, 0, 5)), 422);
            }

            // Semua peserta WAJIB punya Kode_Calon (identitas HCLearn dari register).
            $tanpaKode = $kandidat->filter(fn ($k) => empty($k->Kode_Calon))->pluck('Nama')->all();
            if ($tanpaKode) {
                return ResponseHelper::error('Peserta belum punya Kode Calon HCLearn (akun lama): '.implode(', ', array_slice($tanpaKode, 0, 5)).'. Minta kandidat memperbarui pendaftaran.', 422);
            }

            // ── PERIKSA ULANG KELAYAKAN, JANGAN PERCAYA LAYAR ───────────────────
            //
            // Daftar kandidat memang sudah menyaring yang belum terjadwal, tapi
            // layar bisa basi: admin lain menjadwalkan orang yang sama semenit lalu,
            // atau tab dibiarkan terbuka setengah jam. Tanpa pemeriksaan ini, orang
            // yang sudah punya token diterbitkan tokennya lagi — dua sesi ujian
            // untuk satu aktivitas, dan nilainya saling menimpa.
            // Disaring memakai KODE tahap yang benar-benar dipakai (dari master
            // yang sudah diresolusi di atas), bukan nomor yang dikirim layar —
            // supaya gerbang terakhir ini menilai orang yang sama dengan yang
            // dijadwalkan, bahkan bila layarnya sudah basi.
            $layakKode = $this->kueriKandidat(
                $program->Id_Program,
                (int) $data['tahapUrutan'],
                $tesUrutan,
                '',
                $tahapTes->Kode ?: null,
            )->pluck('l.Kode')->all();
            $tidakLayak = array_values(array_diff($data['peserta'], $layakKode));
            if ($tidakLayak) {
                return ResponseHelper::error(
                    'Sebagian kandidat sudah tidak bisa dijadwalkan untuk aktivitas ini (mungkin baru dijadwalkan admin lain, atau tahapnya sudah berpindah): '
                    .implode(', ', array_slice($tidakLayak, 0, 5))
                    .'. Muat ulang daftar kandidatnya.',
                    409
                );
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $antrean = WcPenjadwalanJob::QUEUE;
            // KODE UNIK. Dulu diturunkan dari `max(Id_Penjadwalan) + 1` — dua admin
            // yang menekan Generate bersamaan mendapat kode yang sama, dan setelah
            // ada penghapusan, max+1 bisa menabrak kode yang sudah dipakai. Sekarang
            // dicari nomor bebas berikutnya dan diverifikasi belum ada.
            $kode = $this->kodeBaru();

            $ids = DB::transaction(function () use ($data, $program, $alur, $semuaTahap, $tahapTes, $tesUrutan, $kandidat, $kode, $userId, $userName, $now) {
                $penjadwalanId = DB::table('N_WEB_CAREERS_Penjadwalan')->insertGetId([
                    'Kode' => $kode,
                    'Nama' => $data['namaUjian'].' — '.$program->Nama,
                    'Program_Id' => $program->Id_Program,
                    'Master_Alur_Id' => $alur->Id_Master_Alur,
                    'Tanggal_Mulai' => substr($data['waktuMulai'], 0, 10),
                    'Tanggal_Selesai' => substr($data['waktuAkhir'], 0, 10),
                    'Jumlah_Tahap' => $semuaTahap->count(),
                    'Jumlah_Tahap_Hclearn' => $semuaTahap->where('Provider', 'THIRD_PARTY')->count(),
                    'Jumlah_Peserta' => count($data['peserta']),
                    // Token belum terbit — job antrean yang menaikkannya ke BERJALAN.
                    'Status' => 'DIANTRIKAN',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Penjadwalan');

                $tahapTerpilihId = null;
                foreach ($semuaTahap as $t) {
                    $dipilih = $t->Id_Master_Alur_Tahap === $tahapTes->Id_Master_Alur_Tahap;

                    $id = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->insertGetId([
                        'Penjadwalan_Id' => $penjadwalanId,
                        'Master_Alur_Tahap_Id' => $t->Id_Master_Alur_Tahap,
                        'Urutan' => $t->Urutan,
                        'Kode' => $t->Kode,
                        'Label' => $t->Label,
                        'Tipe_Tahap_Kode' => $t->Tipe_Tahap_Kode,
                        'Provider' => $t->Provider,
                        'Keputusan' => $t->Keputusan,
                        // Jenis tes sudah tidak dipakai — yang menerangkan ujian ini
                        // adalah Nama_Ujian + Id_Master_Ujian dari paket HCLearn.
                        'Jenis_Tes_Kode' => null,
                        'Formulir_Kode' => $t->Formulir_Kode,
                        // Kolom int lama hanya terisi bila pengenalnya memang angka;
                        // bentuk aslinya dari HCLearn disimpan di Ref_Master_Ujian.
                        'Id_Master_Ujian' => $dipilih && is_numeric($data['idMasterUjian']) ? (int) $data['idMasterUjian'] : null,
                        'Ref_Master_Ujian' => $dipilih ? $data['idMasterUjian'] : null,
                        'Flag_Kirim_Hclearn' => $t->Provider === 'THIRD_PARTY' ? 'Y' : 'T',
                        'Nama_Ujian' => $dipilih ? $data['namaUjian'] : null,
                        // AKTIVITAS KE BERAPA di dalam tahap ini yang dijadwalkan.
                        // Disimpan supaya tautan ke tahap lamaran kandidat bisa
                        // DIPULIHKAN kapan pun — bukan hanya pada detik ini.
                        // Tanpa ini, penjadwalan yang gagal lalu berhasil saat
                        // dicoba ulang menerbitkan token tetapi meninggalkan
                        // kandidatnya berbunyi "menunggu dijadwalkan" selamanya.
                        'Tes_Urutan' => $dipilih ? $tesUrutan : null,
                        'Waktu_Mulai' => $dipilih ? $data['waktuMulai'] : null,
                        'Waktu_Akhir' => $dipilih ? $data['waktuAkhir'] : null,
                        'Status' => $dipilih ? 'MENUNGGU' : 'BELUM',
                        'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                        'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                    ], 'Id_Penjadwalan_Tahap');

                    if ($dipilih) {
                        $tahapTerpilihId = $id;
                    }
                }

                // PESERTA DISISIPKAN BERGEROMBOL, bukan satu per satu.
                //
                // Satu INSERT per orang berarti 500 bolak-balik ke database di
                // dalam satu transaksi — admin menunggu, kuncinya ditahan
                // selama itu, dan penjadwalan besar terasa menggantung padahal
                // tak ada yang salah.
                //
                // 100 baris per perintah, bukan sebanyak-banyaknya: SQL Server
                // membatasi 2100 parameter per perintah, dan baris ini punya
                // 17 kolom. 100 × 17 = 1700, aman dengan jarak yang jelas.
                $barisPeserta = collect($data['peserta'])->map(function ($kodeLamaran) use ($kandidat, $penjadwalanId, $tahapTerpilihId, $now, $userName, $userId) {
                    $k = $kandidat->get($kodeLamaran);

                    return [
                        'Penjadwalan_Id' => $penjadwalanId,
                        'Penjadwalan_Tahap_Id' => $tahapTerpilihId,
                        // Kode_Peserta = Kode_Calon HCLearn (bukan kode lamaran).
                        'Kode_Peserta' => $k->Kode_Calon,
                        'Users_Id' => $k->Id_Users,
                        'Lamaran_Id' => $k->Id_Lamaran,
                        'Jenis_User' => 'eksternal',
                        'Nama' => $k->Nama,
                        'Email' => $k->Email ?? null,
                        'No_Hp' => $k->No_Hp ?? null,
                        'Posisi_Dilamar' => $k->Posisi ?? null,
                        'Status_Kirim' => 'MENUNGGU',
                        'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                        'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                    ];
                });

                foreach ($barisPeserta->chunk(100) as $sepotong) {
                    DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->insert($sepotong->values()->all());
                }

                // TAUTKAN tahap lamaran kandidat → penjadwalan tahap ini, supaya
                // portal kandidat (LamaranDetail) langsung menampilkan status
                // "sudah dijadwalkan" + token/OTP/jendela waktu tesnya.
                //
                // Dipecah per 500 id — alasan yang sama dengan pencarian
                // kandidat di atas: satu id = satu parameter, dan SQL Server
                // menolak perintah yang melewati 2100 parameter.
                $lamaranIds = $kandidat->pluck('Id_Lamaran')->filter()->values();

                foreach ($lamaranIds->chunk(500) as $sepotong) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->whereIn('Lamaran_Id', $sepotong->all())
                        ->where('Urutan', $tahapTes->Urutan)
                        ->update(['Penjadwalan_Tahap_Id' => $tahapTerpilihId, 'Updated_At' => $now]);
                }

                // Tandai SUB-TES yang dijadwalkan (baterai multi-tes: hanya sub-tes
                // pada urutan ini yang berubah; yang lain menunggu jadwalnya sendiri).
                // Dikunci lewat Urutan, bukan Jenis_Tes_Kode — jenis tes boleh kosong
                // dan dua sub-tes bisa memakai jenis yang sama.
                foreach ($lamaranIds->chunk(500) as $sepotong) {
                    $lamaranTahapIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                        ->whereIn('Lamaran_Id', $sepotong->all())
                        ->where('Urutan', $tahapTes->Urutan)
                        ->pluck('Id_Lamaran_Tahap')->all();

                    if (! $lamaranTahapIds) {
                        continue;
                    }

                    foreach (collect($lamaranTahapIds)->chunk(500) as $sepotongTahap) {
                        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                            ->whereIn('Lamaran_Tahap_Id', $sepotongTahap->all())
                            ->when($tesUrutan, fn ($q, $u) => $q->where('Urutan', $u))
                            ->where('Flag_Selesai', 'N')
                            ->whereNull('Penjadwalan_Tahap_Id')
                            ->update(['Status' => 'DIJADWALKAN', 'Penjadwalan_Tahap_Id' => $tahapTerpilihId, 'Updated_At' => $now]);
                    }
                }

                return ['penjadwalan' => $penjadwalanId, 'tahap' => $tahapTerpilihId];
            });

            // ── PENERBITAN TOKEN DIANTREKAN ─────────────────────────────────────
            //
            // Menerbitkan token untuk ratusan kandidat bisa memakan menit. Bila
            // dikerjakan di dalam permintaan ini, permintaannya habis waktu, admin
            // menekan Generate lagi, dan lahirlah penjadwalan ganda. Barisnya sudah
            // tersimpan (status DIANTRIKAN) dan kandidat sudah tertaut, jadi tak
            // ada yang hilang bila worker baru sempat menjalankannya nanti.
            WcPenjadwalanJob::dispatch(
                $ids['penjadwalan'],
                $ids['tahap'],
                (string) $data['idMasterUjian'],
                $data['waktuMulai'],
                $data['waktuAkhir'],
                // Siapa yang meminta — job berjalan tanpa sesi, dan HCLearn
                // menuntut identitas orangnya untuk menentukan ujian mana yang
                // boleh dijadwalkan.
                (int) session('career_auth.id'),
            );

            $jml = count($data['peserta']);
            Log::channel('web_career')->info("Penjadwalan {$kode} dibuat oleh {$userName} — {$jml} peserta diantrekan ke {$antrean}.");

            return ResponseHelper::success(
                ['kode' => $kode, 'jumlahPeserta' => $jml, 'status' => 'DIANTRIKAN'],
                "Penjadwalan {$kode} dibuat — token untuk {$jml} kandidat sedang diterbitkan di latar belakang. Segarkan daftar sebentar lagi untuk melihat hasilnya.",
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat penjadwalan: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat penjadwalan: '.$e->getMessage(), 500);
        }
    }

    /**
     * SERAP BALASAN HCLEARN → simpan kredensial tiap peserta.
     *
     * Dipisah dari pengirimannya supaya bisa dipakai job antrean
     * (`WcPenjadwalanJob`) tanpa menduplikasi aturan pemeriksaannya — dan
     * aturan itulah bagian yang paling tidak boleh berbeda antar pemanggil.
     *
     * @param  array  $res  isi `result` dari HclClient
     * @param  \Illuminate\Support\Collection  $pesertaRows  baris peserta yang dikirim
     * @return array{0:int,1:int,2:?string} [sukses, gagal, pesanGagalPertama]
     */
    public function serapBalasanHclearn(array $res, $pesertaRows): array
    {
        $detail = self::normalkanDetail($res['detail'] ?? []);

        // BALASAN TANPA RINCIAN = GAGAL, bukan sukses hampa.
        //
        // Dulu `detail` kosong membuat sukses & gagal sama-sama 0, sehingga
        // penjadwalan dianggap berhasil padahal tak satu pun token terbit dan
        // pesertanya tergeletak berstatus MENUNGGU tanpa keterangan apa pun.
        if (! $detail) {
            $jml = $pesertaRows->count();
            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->whereIn('Id_Penjadwalan_Peserta', $pesertaRows->pluck('Id_Penjadwalan_Peserta')->all() ?: [0])
                ->update([
                    'Status_Kirim' => 'GAGAL',
                    'Pesan_Error' => 'HCLearn membalas tanpa rincian peserta — tidak ada token yang terbit.',
                    'Updated_At' => now(),
                ]);

            return [0, $jml, 'HCLearn membalas tanpa rincian peserta — tidak ada token yang terbit.'];
        }

        // ── PEMERIKSAAN KEPEMILIKAN — HANYA BILA KONEKSINYA DISEBUT ──────────
        //
        // `HRIS_KANDIDAT_Ujian_Token` adalah tabel MILIK CAT. Dulu ia dikueri
        // lewat `DB::table()` polos, yang jatuh ke koneksi default — database
        // Web Careers. Selama CAT berjalan lokal tabelnya kebetulan sedatabase
        // dan pemeriksaan ini lolos; begitu CAT punya databasenya sendiri, dan
        // di production memang begitu, yang muncul adalah "Invalid object name".
        //
        // Sekarang koneksinya WAJIB disebut lewat HCLEARN_DB_TOKEN_CONNECTION.
        // Kosong berarti jangan dikueri sama sekali — bukan "coba dulu, tangkap
        // galatnya". Kueri yang tak pernah dikirim tak bisa mendarat di
        // database yang salah, dan itu satu kelas kesalahan yang hilang
        // seluruhnya, bukan sekadar tertangani.
        //
        // Melewatkannya aman: sejak pengenal peserta diambil dari SEQUENCE —
        // yang tidak pernah mundur walau tabel di-reset — tabrakan id yang dulu
        // dijaga di sini sudah tercegah di hulu, bukan ditangkap di hilir.
        $shortSemua = collect($detail)->pluck('Short_Token')->filter()->unique()->values()->all();
        $koneksiToken = config('hclearn.db_token_connection');
        $tokenCat = collect();

        if ($shortSemua && $koneksiToken) {
            try {
                $tokenCat = DB::connection($koneksiToken)->table('HRIS_KANDIDAT_Ujian_Token')
                    ->whereIn('Short_Token', $shortSemua)
                    ->where('Sumber_Aplikasi', 'WEB_CAREERS')
                    ->get(['Id_Ujian_Token', 'Short_Token', 'Id_Calon_Karyawan'])
                    ->keyBy('Short_Token');
            } catch (\Throwable $e) {
                // Tetap ditangkap: pada titik ini CAT SUDAH menerbitkan
                // tokennya. Yang gagal cuma pencatatan balik — tapi bila
                // lemparannya lolos, peserta ditandai gagal, Short_Token tak
                // pernah tersimpan, dan karena penyaring percobaan ulang justru
                // `whereNull('Short_Token')`, setiap ulangan mengirim orang yang
                // sama ke CAT lagi. Tokennya beranak, yang lama jadi yatim.
                Log::channel('web_career')->warning(
                    "[PENJADWALAN] pemeriksaan kepemilikan token dilewati — koneksi '{$koneksiToken}' "
                    .'tidak bisa membaca tabel token CAT: '.$e->getMessage()
                );
            }
        }

        // Satu sesi ujian hanya boleh dipegang SATU peserta. Kalau CAT membalas
        // token yang sama untuk dua orang, keduanya akan mengerjakan sesi yang
        // sama dan nilainya saling menimpa — ditangkap di sini, bukan nanti.
        $sudahDipakai = [];
        $sukses = 0;
        $gagal = 0;
        $pesanGagal = null;

        foreach ($detail as $d) {
            $ok = in_array($d['status'] ?? '', ['DIBUAT', 'SUDAH_ADA'], true);
            // Yang dikembalikan CAT adalah pengenal yang KITA kirim, dan itu
            // kini nomor SEQUENCE (`Ref_Cat_Peserta`), bukan IDENTITY kita.
            // Pencarian lewat IDENTITY dipertahankan sebagai cadangan untuk
            // balasan atas kiriman lama yang masih dalam perjalanan.
            $refCat = (int) ($d['Id_WC_Penjadwalan_Peserta'] ?? 0);
            // Cadangan IDENTITY dibatasi pada baris yang belum punya pengenal
            // baru — sama seperti di hasilUjianCallback(). Tanpa batasan itu,
            // balasan bernomor lama bisa mendarat pada peserta lain yang
            // kebetulan ber-IDENTITY sama.
            $barisPeserta = $pesertaRows->firstWhere('Ref_Cat_Peserta', $refCat)
                ?? $pesertaRows->first(fn ($p) => (int) $p->Id_Penjadwalan_Peserta === $refCat && $p->Ref_Cat_Peserta === null);
            $idPeserta = (int) ($barisPeserta->Id_Penjadwalan_Peserta ?? 0);
            $shortToken = $d['Short_Token'] ?? null;
            $seharusnya = $barisPeserta->Kode_Peserta ?? null;
            $baris = $shortToken ? $tokenCat->get($shortToken) : null;
            $idUjianToken = $baris->Id_Ujian_Token ?? null;
            // Pengenal token versi CAT (Hashids). INILAH kunci untuk mengubah /
            // membatalkan jadwal lewat API nanti — tanpa ini, satu-satunya cara
            // adalah menulis tabel CAT langsung, yang mustahil saat CAT remote.
            $refUjian = $d['Id_Ujian_Token'] ?? null;
            $tolak = null;

            if (! $barisPeserta) {
                // Balasan untuk peserta yang tidak kita kenali. Dicatat, bukan
                // didiamkan: tanpa ini `$idPeserta` bernilai 0, kolom tak ada
                // yang terisi, dan token yang sudah terbit di CAT hilang tanpa
                // satu pun jejak di sisi kita.
                $gagal++;
                $pesanGagal ??= "HCLearn membalas untuk peserta tak dikenal (ref {$refCat}).";
                Log::channel('web_career')->error("[PENJADWALAN] balasan untuk peserta tak dikenal — ref {$refCat}, token ".($shortToken ?: '-'));

                continue;
            }

            if ($ok && ! $shortToken) {
                $tolak = 'HCLearn tidak mengembalikan token untuk peserta ini.';
            } elseif ($ok && ! $refUjian) {
                // Tanpa pengenal token, jadwalnya tak akan bisa diubah nanti.
                $tolak = "HCLearn tidak mengembalikan Id_Ujian_Token untuk {$shortToken}.";
            } elseif ($ok && $baris && $seharusnya && $baris->Id_Calon_Karyawan !== $seharusnya) {
                // TOKEN MILIK ORANG LAIN.
                //
                // CAT mendeduplikasi lewat indeks unik pada Id_WC_Penjadwalan_Peserta
                // lalu membalas `SUDAH_ADA` bila id itu sudah terpakai. Id peserta di
                // sisi kita adalah IDENTITY yang bisa TERULANG setelah tabelnya
                // di-reset — dan pernah terjadi: peserta baru ber-id 4 menerima token
                // milik angkatan lama yang statusnya sudah 'selesai'.
                $tolak = "Token {$shortToken} terdaftar atas {$baris->Id_Calon_Karyawan}, bukan {$seharusnya}.";
            } elseif ($ok && isset($sudahDipakai[$refUjian])) {
                // TOKEN KEMBAR DALAM SATU ANGKATAN.
                $tolak = "Token {$shortToken} dibalas untuk dua peserta sekaligus (bentrok dengan peserta #{$sudahDipakai[$refUjian]}).";
            }

            if ($tolak) {
                $ok = false;
                $d['pesan'] = $tolak.' Penjadwalan peserta ini dibatalkan agar kredensial tidak tertukar.';
                Log::channel('web_career')->error("[PENJADWALAN] peserta #{$idPeserta} ({$seharusnya}): {$tolak}");
            } elseif ($ok) {
                $sudahDipakai[$refUjian] = $idPeserta;
            }

            // Sudah dilengkapi normalkanDetail() — di sini tinggal dipakai.
            $otp = $d['Akses_OTP'] ?? null;

            // Kolom disetel EKSPLISIT (tanpa array_filter): peserta yang gagal
            // harus benar-benar kosong kredensialnya, bukan menyisakan nilai lama.
            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $idPeserta)
                ->update([
                    'Status_Kirim' => $ok ? 'TERKIRIM' : 'GAGAL',
                    // Id_Ujian_Token: tautan PASTI ke sesi ujian di CAT. Sebelumnya
                    // kolom ini tak pernah diisi, sehingga satu-satunya penghubung
                    // adalah Short_Token berupa teks.
                    'Id_Ujian_Token' => $ok ? $idUjianToken : null,
                    'Ref_Ujian_Token' => $ok ? $refUjian : null,
                    'Link_Ujian' => $ok ? ($d['Link_Ujian'] ?? null) : null,
                    'Short_Token' => $ok ? $shortToken : null,
                    'Akses_OTP' => $ok ? $otp : null,
                    'Pesan_Error' => $ok ? null : substr($d['pesan'] ?? 'Gagal', 0, 480),
                    'Updated_At' => now(),
                ]);

            if ($ok) {
                $sukses++;
            } else {
                $gagal++;
                $pesanGagal ??= $d['pesan'] ?? 'Gagal membuat token peserta.';
            }
        }

        return [$sukses, $gagal, $pesanGagal];
    }

    /**
     * SATU BENTUK BALASAN, apa pun jalur yang dipakai CAT.
     *
     * CAT punya dua jalur dengan dua bentuk `detail` yang berbeda. Jalur
     * sinkron mengirim Short_Token dan Akses_OTP sebagai field tersendiri;
     * jalur antreannya tidak — di sana keduanya cuma menempel di dalam
     * Link_Ujian (`?wo_aut={Short_Token}&otp={Akses_OTP}`).
     *
     * Perbedaan itu diratakan DI SATU TEMPAT, sebelum apa pun membacanya.
     * Alternatifnya adalah setiap pembaca hilir mengingat sendiri jalur mana
     * yang sedang ia hadapi — dan yang lupa tidak melempar galat, ia cuma
     * menyimpan kolom kosong. Kandidatnya tetap tercatat "terkirim", tanpa
     * kredensial yang bisa dipakai masuk.
     *
     * Web Careers kini selalu memotong kirimannya di bawah batas sinkron, jadi
     * jalur antrean semestinya tak pernah terpakai. Normalisasi ini tetap ada
     * sebagai jaring: bentuk balasan ditentukan sistem lain, dan kita tidak
     * memegang kendali atas kapan ia berubah.
     */
    private static function normalkanDetail(array $detail): array
    {
        return array_map(function ($d) {
            if (! is_array($d)) {
                return $d;
            }

            $link = $d['Link_Ujian'] ?? null;

            if (empty($d['Short_Token']) && $link && preg_match('/[?&]wo_aut=([^&]+)/', $link, $m)) {
                $d['Short_Token'] = urldecode($m[1]);
            }

            if (empty($d['Akses_OTP']) && $link && preg_match('/[?&]otp=([^&]+)/', $link, $m)) {
                $d['Akses_OTP'] = urldecode($m[1]);
            }

            return $d;
        }, $detail);
    }

    /**
     * Lepaskan tautan jadwal dari tahap DAN sub-tes kandidat.
     *
     * Harus dua-duanya. Sub-tes yang masih menyimpan Penjadwalan_Tahap_Id akan
     * terus dianggap "sudah dijadwalkan", sehingga kandidatnya tidak pernah
     * muncul lagi di daftar — jadwalnya sendiri sudah dihapus, tapi orangnya
     * terkunci tanpa jalan keluar.
     */
    private function lepaskanTautan(array $tahapIds): void
    {
        if (! $tahapIds) {
            return;
        }

        DB::table('N_WEB_CAREERS_Lamaran_Tahap')
            ->whereIn('Penjadwalan_Tahap_Id', $tahapIds)
            ->update(['Penjadwalan_Tahap_Id' => null, 'Updated_At' => now()]);

        DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->whereIn('Penjadwalan_Tahap_Id', $tahapIds)
            ->where('Flag_Selesai', 'N') // yang sudah dikerjakan jangan diusik
            ->update(['Penjadwalan_Tahap_Id' => null, 'Status' => 'BELUM', 'Updated_At' => now()]);
    }

    /**
     * POST /api/v1/penjadwalan/{id}/ulang — COBA LAGI penjadwalan yang gagal.
     *
     * KENAPA PERLU TOMBOL SENDIRI
     * Sebelum ini, satu-satunya jalan setelah kegagalan adalah MENGHAPUS
     * penjadwalannya lalu menyusunnya dari nol: pilih program, tahap, paket
     * ujian, jendela waktu, dan mencentang ulang seluruh kandidat. Untuk
     * kegagalan yang penyebabnya sudah diperbaiki — dan sebagian besar memang
     * begitu — itu menghukum admin atas kesalahan sistem, dan setiap penyusunan
     * ulang adalah kesempatan baru salah pilih.
     *
     * AMAN DIULANG. Job hanya memproses peserta yang tokennya BELUM terbit,
     * jadi yang sudah berhasil tidak pernah dikirim dua kali. Peserta yang gagal
     * mendapat pengenal CAT yang BARU (lihat WcPenjadwalanJob), sehingga
     * tabrakan yang menyebabkan kegagalan pertama tidak terulang.
     */
    public function ulang(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Penjadwalan tidak valid.', 422);
        }

        $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap as pt')
            ->join('N_WEB_CAREERS_Penjadwalan as p', 'p.Id_Penjadwalan', '=', 'pt.Penjadwalan_Id')
            ->where('pt.Penjadwalan_Id', $realId)
            ->whereNotNull('pt.Waktu_Mulai')
            ->select('pt.*', 'p.Kode')
            ->first();

        if (! $tahap) {
            return ResponseHelper::error('Penjadwalan tidak ditemukan.', 404);
        }

        // Paket ujiannya harus masih tercatat — tanpa itu tak ada yang bisa
        // dikirim ulang, dan menebaknya berarti menjadwalkan tes yang salah.
        $refUjian = $tahap->Ref_Master_Ujian ?: $tahap->Id_Master_Ujian;
        if (! $refUjian) {
            return ResponseHelper::error('Penjadwalan ini tidak menyimpan paket ujiannya — hapus lalu buat ulang.', 422);
        }

        $menunggu = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $tahap->Id_Penjadwalan_Tahap)
            ->whereNull('Short_Token')
            ->count();

        if (! $menunggu) {
            return ResponseHelper::error('Semua peserta sudah punya token — tidak ada yang perlu diulang.', 409);
        }

        DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
            ->where('Id_Penjadwalan_Tahap', $tahap->Id_Penjadwalan_Tahap)
            ->update([
                'Status' => 'DIANTRIKAN',
                // Pesan galat lama DIHAPUS: membiarkannya membuat percobaan yang
                // sedang berjalan tetap terbaca gagal.
                'Pesan_Error' => null,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

        DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
            ->where('Penjadwalan_Tahap_Id', $tahap->Id_Penjadwalan_Tahap)
            ->whereNull('Short_Token')
            ->update(['Status_Kirim' => 'MENUNGGU', 'Pesan_Error' => null, 'Updated_At' => now()]);

        WcPenjadwalanJob::dispatch(
            (int) $tahap->Penjadwalan_Id,
            (int) $tahap->Id_Penjadwalan_Tahap,
            (string) $refUjian,
            (string) $tahap->Waktu_Mulai,
            (string) $tahap->Waktu_Akhir,
            (int) session('career_auth.id'),
        );

        Log::channel('web_career')->info(
            "[PENJADWALAN] {$tahap->Kode} dicoba ulang oleh ".session('career_auth.nama', 'ADMIN')." — {$menunggu} peserta."
        );

        return ResponseHelper::success(
            ['jumlah' => $menunggu],
            "Penjadwalan diantrekan ulang untuk {$menunggu} kandidat. Segarkan daftar sebentar lagi.",
        );
    }

    /**
     * PUT /api/v1/penjadwalan/peserta/{id} — GESER JADWAL SATU KANDIDAT.
     *
     * Yang perlu digeser hampir selalu satu orang: sakit, salah zona waktu,
     * tokennya telanjur lewat, atau minta diundur. Mengubahnya di level jadwal
     * berarti menggeser seisi angkatan demi satu orang, jadi jendela disimpan
     * di baris pesertanya sendiri (`Penjadwalan_Peserta.Waktu_*`) dan hanya
     * token milik orang itu yang ikut berubah di CAT.
     *
     * Portal kandidat membaca jendela peserta lebih dulu, jatuh ke jendela tahap
     * bila kosong — peserta lain di jadwal yang sama tidak terpengaruh.
     * Ditolak bila ujiannya sudah mulai dikerjakan.
     */
    public function updatePeserta(Request $request, string $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $peserta = $realId ? DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Id_Penjadwalan_Peserta', $realId)->first() : null;
            if (! $peserta) {
                return ResponseHelper::error('Peserta tidak ditemukan.', 404);
            }

            $data = $request->validate([
                'waktuMulai' => 'required|date',
                'waktuAkhir' => 'required|date|after:waktuMulai',
            ], [
                'waktuAkhir.after' => 'Waktu berakhir harus setelah waktu mulai.',
            ]);

            $terkunci = in_array($peserta->Status_Pengerjaan, ['mengerjakan', 'selesai', 'timeout'], true)
                || $peserta->Flag_Selesai === 'Y';
            if ($terkunci) {
                return ResponseHelper::error("Jadwal {$peserta->Nama} tidak dapat diubah — ujiannya sudah dikerjakan.", 409);
            }

            $now = now();
            $userName = session('career_auth.nama', 'ADMIN');
            $userId = session('career_auth.id');

            // ── UBAH DI CAT DULU, BARU CATAT DI SINI ────────────────────────
            //
            // Dulu baris token CAT ditulis LANGSUNG. Itu hanya bekerja saat CAT
            // berjalan lokal dan sedatabase; di staging/production CAT punya
            // databasenya sendiri, sehingga perubahan jadwal tidak pernah sampai
            // — admin melihat "berhasil" padahal kandidat tetap memakai jendela
            // lama. Sekarang lewat API resmi, dan bila CAT menolak, jadwal di
            // sisi kita TIDAK ikut berubah supaya keduanya tak pernah berbeda.
            if (! $peserta->Ref_Ujian_Token) {
                return ResponseHelper::error(
                    "Jadwal {$peserta->Nama} belum punya pengenal sesi dari HCLearn (dijadwalkan sebelum penautan ini ada). Hapus penjadwalannya lalu buat ulang agar bisa diubah.",
                    409
                );
            }

            $balas = $this->hcl->put("penjadwalan/{$peserta->Ref_Ujian_Token}", [
                'Waktu_Mulai' => $data['waktuMulai'],
                'Waktu_Akhir' => $data['waktuAkhir'],
                config('hclearn.kamera_field', 'Flag_Camera') => (bool) config('hclearn.wajib_kamera', true),
            ], [
                'Jenis_Event' => 'UBAH_JADWAL_UJIAN',
                'Penjadwalan_Id' => $peserta->Penjadwalan_Id,
                'Penjadwalan_Tahap_Id' => $peserta->Penjadwalan_Tahap_Id,
                'Penjadwalan_Peserta_Id' => $peserta->Id_Penjadwalan_Peserta,
            ]);

            if (! $balas['sukses']) {
                Log::channel('web_career')->warning("Gagal ubah jadwal peserta #{$peserta->Id_Penjadwalan_Peserta}: ".$balas['message']);

                return ResponseHelper::error("HCLearn menolak perubahan jadwal: {$balas['message']}", (int) ($balas['status'] ?: 422));
            }

            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $peserta->Id_Penjadwalan_Peserta)
                ->update([
                    'Waktu_Mulai' => $data['waktuMulai'],
                    'Waktu_Akhir' => $data['waktuAkhir'],
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);

            Log::channel('web_career')->info("Jadwal peserta #{$peserta->Id_Penjadwalan_Peserta} ({$peserta->Nama}) digeser oleh {$userName} — dikonfirmasi HCLearn.");

            return ResponseHelper::success(null, "Jadwal {$peserta->Nama} diperbarui di HCLearn. Token & OTP tetap sama — yang berubah hanya jendela waktunya.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal memperbarui jadwal peserta #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui jadwal peserta.', 500);
        }
    }

    /**
     * PUT /api/v1/penjadwalan/{id} — EDIT jendela waktu SELURUH jadwal.
     * Tidak lagi dipakai layar Penjadwalan (di sana jadwal digeser per kandidat
     * lewat `updatePeserta`), tetapi dipertahankan untuk penggeseran massal.
     * Ditolak bila peserta sudah 'mengerjakan' / 'selesai' (jadwal terkunci).
     */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $jadwal = $realId ? DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)->first() : null;
            if (! $jadwal) {
                return ResponseHelper::error('Penjadwalan tidak ditemukan.', 404);
            }

            $data = $request->validate([
                'waktuMulai' => 'required|date',
                'waktuAkhir' => 'required|date|after:waktuMulai',
            ], [
                'waktuAkhir.after' => 'Waktu berakhir harus setelah waktu mulai.',
            ]);

            // Tahap yang dijadwalkan = tahap ber-Nama_Ujian (tes pihak ke-3 terpilih).
            $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                ->where('Penjadwalan_Id', $realId)
                ->whereNotNull('Nama_Ujian')
                ->orderByDesc('Id_Penjadwalan_Tahap')
                ->first();
            if (! $tahap) {
                return ResponseHelper::error('Tahap tes terjadwal tidak ditemukan.', 404);
            }

            $pesertaRows = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Penjadwalan_Tahap_Id', $tahap->Id_Penjadwalan_Tahap)->get();

            // Kunci bila ada peserta yang sudah mengerjakan / selesai.
            $terkunci = $pesertaRows->first(fn ($p) => in_array($p->Status_Pengerjaan, ['mengerjakan', 'selesai', 'timeout'], true) || $p->Flag_Selesai === 'Y');
            if ($terkunci) {
                return ResponseHelper::error('Jadwal tidak dapat diubah — sebagian peserta sudah mengerjakan / selesai tes.', 409);
            }

            $now = now();
            $userName = session('career_auth.nama', 'ADMIN');
            $userId = session('career_auth.id');

            // ── UBAH DI CAT DULU, SATU PER SATU ─────────────────────────────
            //
            // Tabel token CAT tidak lagi ditulis langsung: itu hanya bekerja saat
            // CAT sedatabase (mode development). API-nya per sesi ujian, jadi
            // dipanggil per peserta. Yang gagal dilaporkan namanya — lebih baik
            // sebagian tertunda dan terlihat daripada tampak berhasil seluruhnya.
            $berhasil = 0;
            $gagal = [];
            foreach ($pesertaRows as $ps) {
                if (! $ps->Ref_Ujian_Token) {
                    $gagal[] = "{$ps->Nama} (belum punya pengenal sesi HCLearn)";

                    continue;
                }
                $balas = $this->hcl->put("penjadwalan/{$ps->Ref_Ujian_Token}", [
                    'Waktu_Mulai' => $data['waktuMulai'],
                    'Waktu_Akhir' => $data['waktuAkhir'],
                    config('hclearn.kamera_field', 'Flag_Camera') => (bool) config('hclearn.wajib_kamera', true),
                ], [
                    'Jenis_Event' => 'UBAH_JADWAL_UJIAN',
                    'Penjadwalan_Id' => $realId,
                    'Penjadwalan_Tahap_Id' => $tahap->Id_Penjadwalan_Tahap,
                    'Penjadwalan_Peserta_Id' => $ps->Id_Penjadwalan_Peserta,
                ]);

                if ($balas['sukses']) {
                    $berhasil++;
                    DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                        ->where('Id_Penjadwalan_Peserta', $ps->Id_Penjadwalan_Peserta)
                        ->update(['Waktu_Mulai' => null, 'Waktu_Akhir' => null, 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId]);
                } else {
                    $gagal[] = "{$ps->Nama} ({$balas['message']})";
                }
            }

            if (! $berhasil) {
                return ResponseHelper::error('Tidak ada jadwal yang berhasil diubah di HCLearn: '.implode('; ', array_slice($gagal, 0, 3)), 422);
            }

            DB::transaction(function () use ($realId, $tahap, $data, $now, $userName, $userId) {
                // Jendela BAWAAN tahap — dibaca portal kandidat & kepala akordion.
                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahap->Id_Penjadwalan_Tahap)
                    ->update(['Waktu_Mulai' => $data['waktuMulai'], 'Waktu_Akhir' => $data['waktuAkhir'], 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId]);

                DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)
                    ->update(['Tanggal_Mulai' => substr($data['waktuMulai'], 0, 10), 'Tanggal_Selesai' => substr($data['waktuAkhir'], 0, 10), 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId]);
            });

            Log::channel('web_career')->info("Penjadwalan {$jadwal->Kode} jendela waktu diperbarui oleh {$userName} — {$berhasil} sesi diubah di HCLearn.");

            $pesan = "Jadwal tes diperbarui — {$berhasil} sesi ujian diubah di HCLearn.";
            if ($gagal) {
                $pesan .= ' Gagal untuk: '.implode('; ', array_slice($gagal, 0, 3)).'.';
            }

            return ResponseHelper::success(null, $pesan);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update penjadwalan #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui jadwal.', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // JANGAN HAPUS JADWAL YANG SUDAH DIKERJAKAN.
            //
            // Menghapusnya melepas tautan kandidat sehingga mereka muncul lagi di
            // antrean "menunggu jadwal" — padahal ujiannya sudah selesai. Nilainya
            // hilang dari jejak, dan orangnya berpeluang dites dua kali.
            $sudahJalan = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Penjadwalan_Id', $realId)
                ->where(fn ($q) => $q->whereIn('Status_Pengerjaan', ['mengerjakan', 'selesai', 'timeout'])
                    ->orWhere('Flag_Selesai', 'Y'))
                ->count();
            if ($sudahJalan) {
                return ResponseHelper::error(
                    "Tidak bisa dihapus — {$sudahJalan} peserta sudah mengerjakan ujiannya. Menghapus jadwal ini akan menghilangkan jejak hasil mereka.",
                    409
                );
            }

            DB::transaction(function () use ($realId) {
                // Kandidat harus kembali bisa dijadwalkan setelah jadwalnya dihapus.
                $tahapIds = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                    ->where('Penjadwalan_Id', $realId)->pluck('Id_Penjadwalan_Tahap')->all();
                $this->lepaskanTautan($tahapIds);

                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Penjadwalan_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Penjadwalan_Id', $realId)->delete();
                DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)->delete();
            });

            Log::channel('web_career')->info("Penjadwalan #{$realId} dihapus");

            return ResponseHelper::success(null, 'Penjadwalan dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus penjadwalan #{$id}: ".$e->getMessage());

            return ResponseHelper::error('Gagal menghapus penjadwalan', 500);
        }
    }
}
