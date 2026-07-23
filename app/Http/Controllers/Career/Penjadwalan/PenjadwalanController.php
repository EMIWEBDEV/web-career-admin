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
 * Semua sumber dari DB, TIDAK ADA hardcode: tab kategori dari Master Talent Acquisition,
 * program dari Program, jenis tes dari Master Jenis Tes (Flag_Cat='Y'), paket/nama ujian
 * dari HCLearn lewat HclClient di sisi server.
 *
 * Rantai keterhubungan: Program → Alur_Kode → Master Alur → tahap ber-Provider
 * 'THIRD_PARTY' yang Jenis_Tes_Kode-nya cocok. Tahap itulah yang dikirim ke HCLearn.
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

    /** Tab kategori, program, dan jenis tes — semuanya dari DB. */
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

            $jenisTes = DB::table('N_WEB_CAREERS_Master_Jenis_Tes')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Cat', 'Y')
                ->orderBy('Nama')
                ->get(['Id_Master_Jenis_Tes as id', 'Kode as kode', 'Nama as nama', 'Kategori as kategori', 'Metode as metode', 'Pelaksana as pelaksana']);

            return ResponseHelper::success(compact('talent', 'program', 'jenisTes'), 'Opsi dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat opsi penjadwalan: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat opsi', 500);
        }
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
     * Kandidat NYATA yang layak dijadwalkan tes: pelamar program terpilih yang
     * TAHAP SAAT INI adalah tahap tes pihak ke-3 (Provider THIRD_PARTY) & masih
     * BERJALAN. Bila jenis tes dipilih, disaring ke Jenis_Tes_Kode itu.
     * Tanpa programId, atau tak ada pelamar di tahap tes → daftar KOSONG.
     * Identitas peserta = Kode lamaran (LMR-...).
     */
    public function kandidat(Request $request)
    {
        try {
            $programId = (int) $request->query('programId', 0);
            $jenisTesKode = trim((string) $request->query('jenisTesKode', ''));
            $cari = trim((string) $request->query('q', ''));

            if (! $programId) {
                return ResponseHelper::success([], 'Pilih program terlebih dahulu.');
            }

            $rows = DB::table('N_WEB_CAREERS_Lamaran as l')
                // Tahap SAAT INI kandidat = baris tahap dengan Urutan = Lamaran.Urutan_Tahap.
                ->join('N_WEB_CAREERS_Lamaran_Tahap as t', fn ($j) => $j
                    ->on('t.Lamaran_Id', '=', 'l.Id_Lamaran')
                    ->on('t.Urutan', '=', 'l.Urutan_Tahap'))
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->where('l.Program_Id', $programId)
                ->where('l.Status', 'BERJALAN')
                ->where('t.Status', 'BERJALAN')
                ->where('t.Provider', 'THIRD_PARTY')
                ->when($jenisTesKode !== '', fn ($q) => $q->where('t.Jenis_Tes_Kode', $jenisTesKode))
                ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                    $w->where('u.Nama', 'like', "%{$cari}%")
                        ->orWhere('l.Kode', 'like', "%{$cari}%")
                        ->orWhere('pos.Posisi', 'like', "%{$cari}%");
                }))
                ->orderBy('u.Nama')
                ->limit(500)
                ->get(['l.Kode as kode', 'u.Nama as nama', 'u.No_Hp as hp', 'u.Email as email', 'pos.Posisi as posisi', 't.Label as tahap'])
                ->map(fn ($r) => [
                    'kode' => $r->kode,
                    'nama' => $r->nama,
                    'hp' => $r->hp,
                    'email' => $r->email,
                    'posisi' => $r->posisi,
                    'tahap' => $r->tahap,
                ]);

            return ResponseHelper::success($rows, 'Kandidat dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kandidat: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat kandidat', 500);
        }
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
                'jenisTesKode' => 'required|string|max:30',
                'idMasterUjian' => 'required|string',
                'namaUjian' => 'required|string|max:255',
                'waktuMulai' => 'required|date',
                'waktuAkhir' => 'required|date|after:waktuMulai',
                'peserta' => 'required|array|min:1|max:500',
                'peserta.*' => 'required|string|max:20',
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

            $tahapTes = $semuaTahap->first(fn ($t) => $t->Provider === 'THIRD_PARTY' && $t->Jenis_Tes_Kode === $data['jenisTesKode']);
            if (! $tahapTes) {
                return ResponseHelper::error("Alur '{$alur->Nama}' tidak punya tahap pihak ke-3 untuk jenis tes ini. Tambahkan tahapnya di Master Alur.", 422);
            }

            // Peserta = lamaran NYATA (by Kode) pada program ini. Bukan lagi HRIS dummy.
            $kandidat = DB::table('N_WEB_CAREERS_Lamaran as l')
                ->join('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'l.Id_Users')
                ->leftJoin('N_WEB_CAREERS_Program_Posisi as pos', 'pos.Id_Program_Posisi', '=', 'l.Program_Posisi_Id')
                ->whereIn('l.Kode', $data['peserta'])
                ->where('l.Program_Id', $program->Id_Program)
                ->get(['l.Kode', 'u.Nama', 'u.Email', 'u.No_Hp', 'pos.Posisi'])
                ->keyBy('Kode');
            $tidakDikenal = array_diff($data['peserta'], $kandidat->keys()->all());
            if ($tidakDikenal) {
                return ResponseHelper::error('Kandidat tidak dikenal / bukan pelamar program ini: ' . implode(', ', array_slice($tidakDikenal, 0, 5)), 422);
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $kode = 'JDW-' . str_pad((string) (DB::table('N_WEB_CAREERS_Penjadwalan')->max('Id_Penjadwalan') + 1), 4, '0', STR_PAD_LEFT);

            $ids = DB::transaction(function () use ($data, $program, $alur, $semuaTahap, $tahapTes, $kandidat, $kode, $userId, $userName, $now) {
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
                        'Jenis_Tes_Kode' => $t->Jenis_Tes_Kode,
                        'Formulir_Kode' => $t->Formulir_Kode,
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

                foreach ($data['peserta'] as $kodeCalon) {
                    $k = $kandidat->get($kodeCalon);
                    DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')->insert([
                        'Penjadwalan_Id' => $penjadwalanId,
                        'Penjadwalan_Tahap_Id' => $tahapTerpilihId,
                        'Kode_Peserta' => $kodeCalon,
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

                return ['penjadwalan' => $penjadwalanId, 'tahap' => $tahapTerpilihId];
            });

            $hasil = $this->kirimKeHclearn($ids['penjadwalan'], $ids['tahap'], $data, $tahapTes, $semuaTahap->count(), $program);

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
            'peserta' => $pesertaRows->map(fn ($p) => [
                'Id_WC_Penjadwalan_Peserta' => (int) $p->Id_Penjadwalan_Peserta,
                'Kode_Peserta' => $p->Kode_Peserta,
                'Jenis_User' => 'eksternal',
                'Email' => $p->Email,
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

        foreach ($res['detail'] ?? [] as $d) {
            $ok = in_array($d['status'] ?? '', ['DIBUAT', 'SUDAH_ADA'], true);

            DB::table('N_WEB_CAREERS_Penjadwalan_Peserta')
                ->where('Id_Penjadwalan_Peserta', $d['Id_WC_Penjadwalan_Peserta'] ?? 0)
                ->update(array_filter([
                    'Status_Kirim' => $ok ? 'TERKIRIM' : 'GAGAL',
                    'Link_Ujian' => $d['Link_Ujian'] ?? null,
                    'Short_Token' => $d['Short_Token'] ?? null,
                    'Pesan_Error' => $ok ? null : substr($d['pesan'] ?? 'Gagal', 0, 480),
                    'Updated_At' => now(),
                ], fn ($v) => $v !== null));

            $ok ? $sukses++ : $gagal++;
        }

        DB::table('N_WEB_CAREERS_Penjadwalan_Tahap')->where('Id_Penjadwalan_Tahap', $tahapId)
            ->update(['Status' => $gagal === 0 ? 'TERKIRIM' : 'SEBAGIAN', 'Waktu_Kirim' => now(), 'Updated_At' => now()]);

        return ['sukses' => $sukses, 'gagal' => $gagal, 'mode' => 'LANGSUNG'];
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table('N_WEB_CAREERS_Penjadwalan')->where('Id_Penjadwalan', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            DB::transaction(function () use ($realId) {
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
