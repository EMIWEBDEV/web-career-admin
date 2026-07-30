<?php

namespace App\Http\Controllers\Career\Penjadwalan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\WebCareers\HclClient;
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
    public function __construct(private HclClient $hcl)
    {
    }

    public function index()
    {
        return Inertia::render('Career/admin/penjadwalan/penjadwalan', CareerShell::props('/karir/penjadwalan', 'Penjadwalan'));
    }

    /** Tab kategori & program — dari DB. Jenis tes TIDAK lagi dikirim (lihat tesAlur). */
    public function opsi()
    {
        try {
            $talent = DB::table('N_WEB_CAREERS_Master_Talent_Acquisition')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Master_Talent_Acquisition')
                ->get(['Kode as kode', 'Nama as nama']);

            $program = DB::table('N_WEB_CAREERS_Program as p')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Kode', '=', 'p.Alur_Kode')
                ->orderBy('p.Nama')
                ->get([
                    'p.Id_Program as id', 'p.Kode as kode', 'p.Nama as nama', 'p.Kategori as kategori',
                    'p.Warna as warna', 'p.Status as status', 'p.Alur_Kode as alurKode',
                    'a.Id_Master_Alur as alurId', 'a.Nama as alurNama',
                ]);

            return ResponseHelper::success(compact('talent', 'program'), 'Opsi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi penjadwalan: ' . $e->getMessage());

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

                    $daftar[] = [
                        'tahapUrutan' => (int) $t->Urutan,
                        'tahapLabel' => $t->Label,
                        'tipe' => $tipeTes,
                        'tipeNama' => $namaTipe[$tipeTes] ?? null,
                        'tesUrutan' => (int) $s->Urutan,
                        'tesLabel' => $s->Label,
                        'peran' => $s->Peran,
                        // Tahap dengan >1 aktivitas dijadwalkan satu per satu.
                        'multi' => $anak->count() > 1,
                        'menunggu' => (int) ($menunggu[$t->Urutan . '-' . $s->Urutan] ?? 0),
                    ];
                }
            }

            return ResponseHelper::success(
                $daftar,
                $daftar
                    ? 'Tes alur dimuat'
                    : "Alur '{$alur->Nama}' belum punya tahap tes online. Tambahkan tahap bertipe Tes Online di Master Alur."
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat tes alur: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat tes pada alur program', 500);
        }
    }

    /** Jumlah kandidat menunggu jadwal per tahap+sub-tes: ['{tahap}-{tes}' => n]. */
    private function hitungMenunggu(int $programId): array
    {
        return $this->kueriKandidat($programId, null, null)
            ->groupBy('t.Urutan', 'st.Urutan')
            ->select('t.Urutan as tahapUrutan', 'st.Urutan as tesUrutan', DB::raw('COUNT(*) as jml'))
            ->get()
            ->mapWithKeys(fn ($r) => [$r->tahapUrutan . '-' . $r->tesUrutan => (int) $r->jml])
            ->all();
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
                    Log::channel('web_career')->warning("Paket {$kategori} gagal dimuat: " . $hasil['message']);

                    continue;
                }

                foreach ($hasil['result'] ?? [] as $p) {
                    $p['Kategori_Hclearn'] = $kategori;
                    $paket[] = $p;
                }
            }

            return ResponseHelper::success($paket, 'Paket tes dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat paket tes: ' . $e->getMessage());

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
            $cari = trim((string) $request->query('q', ''));

            if (! $programId) {
                return ResponseHelper::success([], 'Pilih program terlebih dahulu.');
            }
            if (! $tahapUrutan) {
                return ResponseHelper::success([], 'Pilih tes/tahap yang mau dijadwalkan.');
            }

            $rows = $this->kueriKandidat($programId, $tahapUrutan, $tesUrutan, $cari)
                ->orderBy('u.Nama')
                ->limit(500)
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
                $rows->isEmpty() ? $this->alasanKosong($programId, $tahapUrutan, $tesUrutan) : 'Kandidat dimuat'
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kandidat: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat kandidat', 500);
        }
    }

    /** Kueri dasar kandidat: sub-tes yang butuh jadwal pihak ke-3 & belum punya. */
    private function kueriKandidat(int $programId, ?int $tahapUrutan, ?int $tesUrutan, string $cari = '')
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
            ->when($tahapUrutan, fn ($q) => $q->where('t.Urutan', $tahapUrutan))
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
    private function alasanKosong(int $programId, ?int $tahapUrutan, ?int $tesUrutan): string
    {
        $berjalan = DB::table('N_WEB_CAREERS_Lamaran')
            ->where('Program_Id', $programId)->where('Status', 'BERJALAN')->count();
        if ($berjalan === 0) {
            return 'Belum ada pelamar berjalan di program ini.';
        }

        // Abaikan saringan tahap → apakah ada yang menunggu jadwal sama sekali?
        $tanpaTahap = $this->kueriKandidat($programId, null, null)->count();
        if ($tanpaTahap > 0 && $tahapUrutan) {
            return "Ada {$tanpaTahap} kandidat menunggu jadwal, tetapi bukan di tahap ini. Pilih tes/tahap lain di daftar sebelah.";
        }

        // Sudah dijadwalkan semua?
        $terjadwal = DB::table('N_WEB_CAREERS_Lamaran as l')
            ->join('N_WEB_CAREERS_Lamaran_Tahap as t', fn ($j) => $j
                ->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')->on('t.Urutan', '=', 'l.Urutan_Tahap'))
            ->join('N_WEB_CAREERS_Lamaran_Tahap_Tes as st', 'st.Lamaran_Tahap_Id', '=', 't.Id_Lamaran_Tahap')
            ->where('l.Program_Id', $programId)->where('l.Status', 'BERJALAN')
            ->whereNotNull('st.Penjadwalan_Tahap_Id')
            ->when($tahapUrutan, fn ($q) => $q->where('t.Urutan', $tahapUrutan))
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

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Penjadwalan as j')
                ->leftJoin('N_WEB_CAREERS_Program as p', 'p.Id_Program', '=', 'j.Program_Id')
                ->leftJoin('N_WEB_CAREERS_Master_Alur as a', 'a.Id_Master_Alur', '=', 'j.Master_Alur_Id')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'j.Created_By_Id')
                ->orderByDesc('j.Id_Penjadwalan')
                ->select('j.*', 'p.Nama as ProgramNama', 'p.Kategori as ProgramKategori', 'a.Nama as AlurNama', 'u.Nama as Pembuat')
                ->get();

            $tahap = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->orderBy('Urutan')->get()->groupBy('Penjadwalan_Id');
            $peserta = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->orderBy('Id_Penjadwalan_Peserta')->get()->groupBy('Penjadwalan_Id');

            $data = $rows->map(fn ($j) => [
                'id' => Hashids::encode($j->Id_Penjadwalan),
                'kode' => $j->Kode,
                'nama' => $j->Nama,
                'program' => $j->ProgramNama,
                'kategori' => $j->ProgramKategori,
                'alur' => $j->AlurNama,
                'status' => $j->Status,
                'jumlahTahap' => (int) $j->Jumlah_Tahap,
                'jumlahTahapHclearn' => (int) $j->Jumlah_Tahap_Hclearn,
                'jumlahPeserta' => (int) $j->Jumlah_Peserta,
                'tahap' => collect($tahap->get($j->Id_Penjadwalan, []))->map(fn ($t) => [
                    'urutan' => (int) $t->Urutan,
                    'kode' => $t->Kode,
                    'label' => $t->Label,
                    'provider' => $t->Provider,
                    'kirimHclearn' => $t->Flag_Kirim_Hclearn === 'Y',
                    'namaUjian' => $t->Nama_Ujian,
                    'waktuMulai' => $t->Waktu_Mulai,
                    'waktuAkhir' => $t->Waktu_Akhir,
                    'status' => $t->Status,
                ])->values(),
                'peserta' => collect($peserta->get($j->Id_Penjadwalan, []))->map(fn ($p) => [
                    'kode' => $p->Kode_Peserta,
                    'nama' => $p->Nama,
                    'posisi' => $p->Posisi_Dilamar,
                    'statusKirim' => $p->Status_Kirim,
                    'statusPengerjaan' => $p->Status_Pengerjaan,
                    'linkUjian' => $p->Link_Ujian,
                    'shortToken' => $p->Short_Token,
                    'nilai' => $p->Total_Nilai,
                    'kelulusan' => $p->Status_Kelulusan,
                    'pesanError' => $p->Pesan_Error,
                ])->values(),
                'createdBy' => $j->Pembuat ?: $j->Created_By,
                'createdAt' => $j->Created_At,
            ])->values();

            return ResponseHelper::success($data, 'Data penjadwalan dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat penjadwalan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data penjadwalan', 500);
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
                'tesUrutan' => 'nullable|integer|min:1',
                'idMasterUjian' => 'required|string',
                'namaUjian' => 'required|string|max:255',
                'waktuMulai' => 'required|date',
                'waktuAkhir' => 'required|date|after:waktuMulai',
                'peserta' => 'required|array|min:1|max:500',
                'peserta.*' => 'required|string|max:20',
            ], [
                'tahapUrutan.required' => 'Pilih dulu tes/tahap yang mau dijadwalkan.',
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

            // Tahap yang dijadwalkan dikenali dari URUTANNYA di alur — nilai yang
            // sama dipakai daftar tes, daftar kandidat, dan Lamaran_Tahap.
            $tahapTes = $semuaTahap->firstWhere('Urutan', $data['tahapUrutan']);
            if (! $tahapTes) {
                return ResponseHelper::error("Alur '{$alur->Nama}' tidak punya tahap ke-{$data['tahapUrutan']}. Muat ulang halaman — alurnya mungkin baru berubah.", 422);
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
            $kandidat = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->whereIn('l.Kode', $data['peserta'])
                ->where('l.Program_Id', $program->Id_Program)
                ->get(['l.Kode', 'l.Id_Lamaran', 'u.Id_Users', 'u.Kode_Calon', 'u.Nama', 'u.Email', 'u.No_Hp', 'pos.Posisi'])
                ->keyBy('Kode');
            $tidakDikenal = array_diff($data['peserta'], $kandidat->keys()->all());
            if ($tidakDikenal) {
                return ResponseHelper::error('Kandidat tidak dikenal / bukan pelamar program ini: ' . implode(', ', array_slice($tidakDikenal, 0, 5)), 422);
            }

            // Semua peserta WAJIB punya Kode_Calon (identitas HCLearn dari register).
            $tanpaKode = $kandidat->filter(fn ($k) => empty($k->Kode_Calon))->pluck('Nama')->all();
            if ($tanpaKode) {
                return ResponseHelper::error('Peserta belum punya Kode Calon HCLearn (akun lama): ' . implode(', ', array_slice($tanpaKode, 0, 5)) . '. Minta kandidat memperbarui pendaftaran.', 422);
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $kode = 'JDW-' . str_pad((string) (DB::table('N_WEB_CAREERS_Penjadwalan')->max('Id_Penjadwalan') + 1), 4, '0', STR_PAD_LEFT);

            $ids = DB::transaction(function () use ($data, $program, $alur, $semuaTahap, $tahapTes, $tesUrutan, $kandidat, $kode, $userId, $userName, $now) {
                $penjadwalanId = DB::table('N_WEB_CAREERS_Penjadwalan')->insertGetId([
                    'Kode' => $kode,
                    'Nama' => $data['namaUjian'] . ' — ' . $program->Nama,
                    'Program_Id' => $program->Id_Program,
                    'Master_Alur_Id' => $alur->Id_Master_Alur,
                    'Tanggal_Mulai' => substr($data['waktuMulai'], 0, 10),
                    'Tanggal_Selesai' => substr($data['waktuAkhir'], 0, 10),
                    'Jumlah_Tahap' => $semuaTahap->count(),
                    'Jumlah_Tahap_Hclearn' => $semuaTahap->where('Provider', 'THIRD_PARTY')->count(),
                    'Jumlah_Peserta' => count($data['peserta']),
                    'Status' => 'BERJALAN',
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
                        'Id_Master_Ujian' => $dipilih && is_numeric($data['idMasterUjian']) ? (int) $data['idMasterUjian'] : null,
                        'Flag_Kirim_Hclearn' => $t->Provider === 'THIRD_PARTY' ? 'Y' : 'T',
                        'Nama_Ujian' => $dipilih ? $data['namaUjian'] : null,
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

                foreach ($data['peserta'] as $kodeLamaran) {
                    $k = $kandidat->get($kodeLamaran);
                    DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->insert([
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
                    ]);
                }

                // TAUTKAN tahap lamaran kandidat → penjadwalan tahap ini, supaya
                // portal kandidat (LamaranDetail) langsung menampilkan status
                // "sudah dijadwalkan" + token/OTP/jendela waktu tesnya.
                DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereIn('Lamaran_Id', $kandidat->pluck('Id_Lamaran')->all())
                    ->where('Urutan', $tahapTes->Urutan)
                    ->update(['Penjadwalan_Tahap_Id' => $tahapTerpilihId, 'Updated_At' => $now]);

                // Tandai SUB-TES yang dijadwalkan (baterai multi-tes: hanya sub-tes
                // pada urutan ini yang berubah; yang lain menunggu jadwalnya sendiri).
                // Dikunci lewat Urutan, bukan Jenis_Tes_Kode — jenis tes boleh kosong
                // dan dua sub-tes bisa memakai jenis yang sama.
                $lamaranTahapIds = DB::table('N_WEB_CAREERS_Lamaran_Tahap')
                    ->whereIn('Lamaran_Id', $kandidat->pluck('Id_Lamaran')->all())
                    ->where('Urutan', $tahapTes->Urutan)
                    ->pluck('Id_Lamaran_Tahap')->all();
                if ($lamaranTahapIds) {
                    DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
                        ->whereIn('Lamaran_Tahap_Id', $lamaranTahapIds)
                        ->when($tesUrutan, fn ($q, $u) => $q->where('Urutan', $u))
                        ->where('Flag_Selesai', 'N')
                        ->whereNull('Penjadwalan_Tahap_Id')
                        ->update(['Status' => 'DIJADWALKAN', 'Penjadwalan_Tahap_Id' => $tahapTerpilihId, 'Updated_At' => $now]);
                }

                return ['penjadwalan' => $penjadwalanId, 'tahap' => $tahapTerpilihId];
            });

            $hasil = $this->kirimKeHclearn($ids['penjadwalan'], $ids['tahap'], $data, $tahapTes, $semuaTahap->count(), $program);

            // HCLearn GAGAL TOTAL (request ditolak, atau semua peserta gagal dibuatkan
            // token) → penjadwalan DIBATALKAN & dihapus. Jangan tampil seolah berhasil.
            $gagalTotal = $hasil['mode'] === 'GAGAL'
                || ($hasil['mode'] === 'LANGSUNG' && $hasil['sukses'] === 0 && $hasil['gagal'] > 0);
            if ($gagalTotal) {
                $this->batalkanPenjadwalan($ids['penjadwalan']);
                $pesan = $hasil['pesan'] ?? 'HCLearn menolak permintaan.';
                Log::channel('web_career')->warning("Penjadwalan {$kode} DIBATALKAN — HCLearn gagal: {$pesan}");

                return ResponseHelper::error("Penjadwalan dibatalkan — HCLearn: {$pesan}", 422);
            }

            Log::channel('web_career')->info("Penjadwalan {$kode} dibuat oleh {$userName}");

            return ResponseHelper::success($hasil, "Penjadwalan {$kode} dibuat — {$hasil['sukses']} sesi berhasil, {$hasil['gagal']} gagal", 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat penjadwalan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal membuat penjadwalan: ' . $e->getMessage(), 500);
        }
    }

    private function kirimKeHclearn(int $penjadwalanId, int $tahapId, array $data, object $tahapTes, int $totalTahap, object $program): array
    {
        $penjadwalan = DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $penjadwalanId)->first();
        $pesertaRows = DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Penjadwalan_Tahap_Id', $tahapId)->get();

        $hasil = $this->hcl->post('penjadwalan', [
            'Kode_WC_Penjadwalan' => $penjadwalan->Kode,
            'Nama_Penjadwalan' => $penjadwalan->Nama,
            'Id_WC_Penjadwalan' => $penjadwalanId,
            'Id_WC_Penjadwalan_Tahap' => $tahapId,
            'Id_WC_Program' => $program->Id_Program,
            'Nama_Program' => $program->Nama,
            'Kode_WC_Tahap' => $tahapTes->Kode,
            'Label_Tahap' => $tahapTes->Label,
            'Urutan_WC_Tahap' => (int) $tahapTes->Urutan,
            'Total_WC_Tahap' => $totalTahap,
            'Id_Master_Ujian' => $data['idMasterUjian'],
            'Waktu_Mulai' => $data['waktuMulai'],
            'Waktu_Akhir' => $data['waktuAkhir'],
            // KAMERA WAJIB: dipaksa dari sisi Web Careers agar CAT tak pernah
            // menjalankan sesi tanpa kamera. CAT mengharap 'Flag_Camera' BOOLEAN
            // (lalu diubah jadi 'Y' pada token). Nama field & nilai dari config.
            config('hclearn.kamera_field', 'Flag_Camera') => (bool) config('hclearn.wajib_kamera', true),
            // URL callback hasil tes: CAT memanggilnya saat tes difinalisasi →
            // WC auto gerakkan tahap (lulus/gugur) tanpa admin.
            'Url_Callback' => config('hclearn.public_url') . '/' . ltrim(config('hclearn.callback_path'), '/'),
            // Identitas peserta DIBAWA LENGKAP — kandidat Web Careers tidak terdaftar
            // di HRIS_Rekrutmen_Karyawan, HCLearn membuat token dari data ini.
            'peserta' => $pesertaRows->map(fn ($p) => [
                'Id_WC_Penjadwalan_Peserta' => (int) $p->Id_Penjadwalan_Peserta,
                'Kode_Peserta' => $p->Kode_Peserta,
                'Jenis_User' => 'eksternal',
                'Id_WC_Users' => $p->Users_Id,
                'Id_WC_Lamaran' => $p->Lamaran_Id,
                'Nama' => $p->Nama,
                'Email' => $p->Email,
                'No_Hp' => $p->No_Hp,
                'Posisi_Dilamar' => $p->Posisi_Dilamar,
            ])->values()->all(),
        ], [
            'Jenis_Event' => 'PENJADWALAN_UJIAN',
            'Penjadwalan_Id' => $penjadwalanId,
            'Penjadwalan_Tahap_Id' => $tahapId,
        ]);

        if (! $hasil['sukses']) {
            DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahapId)
                ->update(['Status' => 'GAGAL', 'Pesan_Error' => substr($hasil['message'], 0, 480), 'Updated_At' => now()]);

            return ['sukses' => 0, 'gagal' => $pesertaRows->count(), 'mode' => 'GAGAL', 'pesan' => $hasil['message']];
        }

        $res = $hasil['result'] ?? [];

        if (($res['mode'] ?? null) === 'ANTRIAN') {
            DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahapId)
                ->update(['Status' => 'DIANTRIKAN', 'Waktu_Kirim' => now(), 'Updated_At' => now()]);

            return ['sukses' => 0, 'gagal' => 0, 'mode' => 'ANTRIAN', 'processId' => $res['Process_Id'] ?? null, 'pesan' => $hasil['message']];
        }

        $sukses = 0;
        $gagal = 0;
        $pesanGagal = null;

        foreach ($res['detail'] ?? [] as $d) {
            $ok = in_array($d['status'] ?? '', ['DIBUAT', 'SUDAH_ADA'], true);

            // OTP dari CAT; fallback: ambil dari parameter otp= pada Link_Ujian.
            $otp = $d['Akses_OTP'] ?? null;
            if (! $otp && ! empty($d['Link_Ujian']) && preg_match('/[?&]otp=([^&]+)/', $d['Link_Ujian'], $m)) {
                $otp = urldecode($m[1]);
            }

            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $d['Id_WC_Penjadwalan_Peserta'] ?? 0)
                ->update(array_filter([
                    'Status_Kirim' => $ok ? 'TERKIRIM' : 'GAGAL',
                    'Link_Ujian' => $d['Link_Ujian'] ?? null,
                    'Short_Token' => $d['Short_Token'] ?? null,
                    'Akses_OTP' => $otp,
                    'Pesan_Error' => $ok ? null : substr($d['pesan'] ?? 'Gagal', 0, 480),
                    'Updated_At' => now(),
                ], fn ($v) => $v !== null));

            if ($ok) {
                $sukses++;
            } else {
                $gagal++;
                $pesanGagal ??= $d['pesan'] ?? 'Gagal membuat token peserta.';
            }
        }

        DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahapId)
            ->update(['Status' => $gagal === 0 ? 'TERKIRIM' : 'SEBAGIAN', 'Waktu_Kirim' => now(), 'Updated_At' => now()]);

        return ['sukses' => $sukses, 'gagal' => $gagal, 'mode' => 'LANGSUNG', 'pesan' => $pesanGagal];
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

    /** Rollback penjadwalan yang gagal terkirim total ke HCLearn (dipakai store). */
    private function batalkanPenjadwalan(int $penjadwalanId): void
    {
        try {
            DB::transaction(function () use ($penjadwalanId) {
                $tahapIds = DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')
                    ->where('Penjadwalan_Id', $penjadwalanId)->pluck('Id_Penjadwalan_Tahap')->all();

                $this->lepaskanTautan($tahapIds);

                DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->where('Penjadwalan_Id', $penjadwalanId)->delete();
                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Penjadwalan_Id', $penjadwalanId)->delete();
                DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $penjadwalanId)->delete();
            });
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal rollback penjadwalan #{$penjadwalanId}: " . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/penjadwalan/{id} — EDIT jendela waktu tes yang sudah dijadwalkan.
     * Memperbarui: tahap terjadwal (portal kandidat baca dari sini) + token ujian
     * di CAT (via Short_Token, DB bersama) supaya jendela ujian ikut berubah.
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

            DB::transaction(function () use ($realId, $tahap, $data, $pesertaRows, $now, $userName, $userId) {
                // 1) Tahap terjadwal — DIBACA portal kandidat (jendela + tombol tes).
                DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahap->Id_Penjadwalan_Tahap)
                    ->update(['Waktu_Mulai' => $data['waktuMulai'], 'Waktu_Akhir' => $data['waktuAkhir'], 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId]);

                // 2) Header penjadwalan (tanggal).
                DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)
                    ->update(['Tanggal_Mulai' => substr($data['waktuMulai'], 0, 10), 'Tanggal_Selesai' => substr($data['waktuAkhir'], 0, 10), 'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId]);

                // 3) Token ujian di CAT (DB bersama) — jendela ujian ikut berubah.
                //    Hanya token milik kanal WEB_CAREERS & yang belum dikerjakan.
                $shortTokens = $pesertaRows->pluck('Short_Token')->filter()->all();
                if ($shortTokens) {
                    $mulai = \Illuminate\Support\Carbon::parse($data['waktuMulai'])->format('Y-m-d H:i:s');
                    $akhir = \Illuminate\Support\Carbon::parse($data['waktuAkhir'])->format('Y-m-d H:i:s');
                    DB::table('HRIS_KANDIDAT_Ujian_Token')
                        ->whereIn('Short_Token', $shortTokens)
                        ->where('Sumber_Aplikasi', 'WEB_CAREERS')
                        ->whereNotIn('Status_Pengerjaan', ['mengerjakan', 'selesai', 'timeout'])
                        ->update(['Waktu_Mulai' => $mulai, 'Waktu_Akhir' => $akhir, 'Status_Updated_At' => $now, 'Status_Updated_By' => 'WEB_CAREERS']);
                }
            });

            Log::channel('web_career')->info("Penjadwalan {$jadwal->Kode} jendela waktu diperbarui oleh {$userName}.");

            return ResponseHelper::success(null, 'Jadwal tes diperbarui — jendela waktu kandidat & token ujian ikut berubah.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update penjadwalan #{$id}: " . $e->getMessage());

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
            Log::channel('web_career')->error("Gagal hapus penjadwalan #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus penjadwalan', 500);
        }
    }
}
