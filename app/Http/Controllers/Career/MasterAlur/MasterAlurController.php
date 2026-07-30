<?php

namespace App\Http\Controllers\Career\MasterAlur;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER TAHAPAN SELEKSI / ALUR (induk-detail: Alur + Alur_Tahap).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel.
 */
class MasterAlurController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/master-alur/masterAlur', CareerShell::props('/master-alur', 'Master Tahapan Seleksi'));
    }

    public function list(Request $request)
    {
        try {
            // FILTER SERVER-SIDE — dipanggil Filter Panel di halaman (bukan saring
            // di browser): q (nama/kode/deskripsi), kategori, status, rentang tanggal.
            $q = trim((string) $request->query('q', ''));
            $kategori = trim((string) $request->query('kategori', ''));
            $status = strtoupper(trim((string) $request->query('status', '')));
            $dari = $request->query('dari');
            $sampai = $request->query('sampai');

            $alur = DB::table('N_WEB_CAREERS_Master_Alur as a')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'a.Created_By_Id')
                ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                    $x->where('a.Nama', 'like', "%{$q}%")
                        ->orWhere('a.Kode', 'like', "%{$q}%")
                        ->orWhere('a.Deskripsi', 'like', "%{$q}%");
                }))
                ->when($kategori !== '', fn ($w) => $w->where('a.Kategori', $kategori))
                ->when(in_array($status, ['AKTIF', 'NONAKTIF'], true), fn ($w) => $w->where('a.Flag_Aktif', $status === 'AKTIF' ? 'Y' : 'N'))
                ->when($dari, fn ($w) => $w->whereDate('a.Created_At', '>=', $dari))
                ->when($sampai, fn ($w) => $w->whereDate('a.Created_At', '<=', $sampai))
                ->orderBy('a.Id_Master_Alur')
                ->select('a.*', 'u.Nama as Pembuat')
                ->get();

            $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->orderBy('Urutan')->get()->groupBy('Master_Alur_Id');
            // Sub-tes tiap tahap (1 tahap → N tes) — dipakai builder & mesin keputusan.
            $subTes = DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->orderBy('Urutan')->get()->groupBy('Master_Alur_Tahap_Id');

            $rows = $alur->map(function ($a) use ($tahap, $subTes) {
                return [
                    'id' => Hashids::encode($a->Id_Master_Alur),
                    'kode' => $a->Kode,
                    'nama' => $a->Nama,
                    'kategori' => $a->Kategori,
                    'deskripsi' => $a->Deskripsi,
                    'status' => $a->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                    'createdBy' => $a->Pembuat ?: $a->Created_By,
                    'createdAt' => $a->Created_At,
                    'stages' => collect($tahap->get($a->Id_Master_Alur, []))->map(fn ($t) => [
                        'kode' => $t->Kode,
                        'label' => $t->Label,
                        'tipe' => $t->Tipe_Tahap_Kode,
                        'provider' => $t->Provider,
                        'keputusan' => $t->Keputusan,
                        'formulirId' => $t->Formulir_Kode,
                        'sla' => $t->SLA,
                        // Mode keputusan tahap + daftar sub-tes (arsitektur multi-tes).
                        'mode' => $t->Mode_Keputusan_Kode ?? 'MANUAL_REVIEW',
                        'tests' => collect($subTes->get($t->Id_Master_Alur_Tahap, []))->map(fn ($x) => [
                            'label' => $x->Label,
                            // Tipe milik aktivitas ini — boleh beda dari tahapnya.
                            'tipe' => $x->Tipe_Tahap_Kode ?: $t->Tipe_Tahap_Kode,
                            'provider' => $x->Provider,
                            'peran' => $x->Peran,
                            'wajib' => $x->Wajib === 'Y',
                            'ambang' => $x->Ambang_Batas,
                        ])->values(),
                        // Aturan pengumuman hasil tahap ini (lihat Batch 6).
                        'pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                        'jedaHari' => isset($t->Jeda_Pengumuman_Hari) ? $t->Jeda_Pengumuman_Hari : null,
                        'notifikasi' => ($t->Flag_Notifikasi ?? 'Y') === 'Y',
                        // Cut-off Talent Pool: bila 'Y', kandidat yang TIDAK lolos di tahap
                        // ini boleh dialihkan admin ke Talent Pool (bukan sekadar gugur).
                        'talentPool' => ($t->Flag_Talent_Pool ?? 'T') === 'Y',
                        // Upload berkas hasil tahap (MCU/Interview) — aktif & wajib/tidak.
                        'uploadHasil' => ($t->Flag_Upload_Hasil ?? 'T') === 'Y',
                        'wajibUpload' => ($t->Flag_Wajib_Upload ?? 'T') === 'Y',
                    ])->values(),
                ];
            })->values();

            return ResponseHelper::success($rows, 'Data alur dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat alur: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data alur', 500);
        }
    }

    /** Mode pengumuman AKTIF: [Kode => butuhJeda(bool)]. Sumber tunggal master. */
    private function modeAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Pengumuman')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Butuh_Jeda', 'Kode')
            ->map(fn ($v) => $v === 'Y')
            ->all();
    }

    /** Kode mode KEPUTUSAN yang aktif (bagaimana tahap menyimpulkan). */
    private function modeKeputusanAktif(): array
    {
        return DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Flag_Aktif', 'Y')->pluck('Kode')->all();
    }

    /** Tipe tahap AKTIF beserta kolom perilakunya — satu-satunya sumber. */
    private function tipeAktif()
    {
        return DB::table('N_WEB_CAREERS_Master_Tipe_Tahap')
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode', 'Nama', 'Perilaku_Kode', 'Flag_Formulir', 'Flag_Upload_Hasil'])
            ->keyBy('Kode');
    }

    private function rules(): array
    {
        $kodeModeAktif = array_keys($this->modeAktif());
        $kodeKeputusan = $this->modeKeputusanAktif();
        $kodeTipe = $this->tipeAktif()->keys()->all();

        return [
            'nama' => 'required|string|max:120',
            'kategori' => 'required|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'stages' => 'nullable|array',
            'stages.*.label' => 'required|string|max:120',
            // Tipe divalidasi terhadap Master Tipe Tahap yang AKTIF. Bila master
            // kosong (belum di-seed), jangan menolak semuanya — cukup batasi
            // panjangnya, supaya tak ada kode tipe yang dianggap istimewa di sini.
            'stages.*.tipe' => $kodeTipe ? ['required', Rule::in($kodeTipe)] : ['required', 'string', 'max:30'],
            // Provider TIDAK dikirim klien — diturunkan dari TIPE tiap aktivitas.
            'stages.*.formulirId' => 'nullable|string|max:30',
            // Mode keputusan tahap — dari master (aktif saja).
            'stages.*.mode' => ['nullable', Rule::in($kodeKeputusan ?: ['MANUAL_REVIEW'])],
            // Sub-tes tahap (1 tahap → N tes). Kosong = dibuatkan 1 default.
            'stages.*.tests' => 'nullable|array|max:20',
            'stages.*.tests.*.label' => 'required|string|max:120',
            // TIPE PER AKTIVITAS: satu tahap boleh mencampur ujian online, tes
            // manual, dan wawancara. Kosong = ikut tipe tahapnya.
            'stages.*.tests.*.tipe' => $kodeTipe ? ['nullable', Rule::in($kodeTipe)] : ['nullable', 'string', 'max:30'],
            'stages.*.tests.*.peran' => 'nullable|in:PENENTU,INFORMATIF',
            'stages.*.tests.*.ambang' => 'nullable|integer|min:0|max:1000',
            // Pengumuman hasil tahap — hanya mode AKTIF dari master (bukan hardcode).
            'stages.*.pengumuman' => ['nullable', Rule::in($kodeModeAktif ?: ['OTOMATIS'])],
            'stages.*.jedaHari' => 'nullable|integer|min:0|max:3650',
            'stages.*.notifikasi' => 'nullable|boolean',
            // Cut-off Talent Pool per tahap (fleksibel: bisa di tahap mana pun).
            'stages.*.talentPool' => 'nullable|boolean',
            // Upload berkas hasil tahap (MCU/Interview) + wajib/opsional.
            'stages.*.uploadHasil' => 'nullable|boolean',
            'stages.*.wajibUpload' => 'nullable|boolean',
        ];
    }

    /** Hapus tahap sebuah alur BESERTA sub-tesnya (cegah baris yatim). */
    private function hapusTahap(int $alurId): void
    {
        $tahapIds = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alurId)->pluck('Id_Master_Alur_Tahap')->all();
        if ($tahapIds) {
            DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->whereIn('Master_Alur_Tahap_Id', $tahapIds)->delete();
        }
        DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $alurId)->delete();
    }

    private function simpanTahap(int $alurId, array $stages, ?int $userId, string $userName): void
    {
        $now = now();

        // Seluruh perilaku tipe dibaca dari master — tak ada kode tipe yang
        // ditulis di sini, jadi tipe baru cukup ditambah lewat Master Tipe Tahap.
        $tipe = $this->tipeAktif();
        $adalahCat = fn (?string $kode) => ($tipe[$kode]->Perilaku_Kode ?? 'MANUAL') === 'CAT';

        // Peta mode -> butuhJeda: jeda hari hanya disimpan untuk mode ber-flag.
        $modeButuhJeda = $this->modeAktif();
        // Mode keputusan + kolom perilakunya (Auto_Lanjut / Auto_Gugur). Dibaca
        // dari master supaya guard di bawah tak perlu menyebut kode mode apa pun.
        $modeSemua = DB::table('N_WEB_CAREERS_Master_Mode_Keputusan')->where('Flag_Aktif', 'Y')->orderBy('Urutan')->get();
        $autoLanjut = $modeSemua->pluck('Auto_Lanjut', 'Kode');
        $autoGugur = $modeSemua->pluck('Auto_Gugur', 'Kode');

        foreach (array_values($stages) as $i => $s) {
            $kode = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($s['label'])), '_') ?: ('TAHAP_' . ($i + 1));

            // ── TIPE MELEKAT PADA AKTIVITAS, BUKAN PADA TAHAP ──────────────────
            //
            // Satu tahap boleh mencampur sifat: "FGD Dan Wawancara HR" bisa
            // berisi Psikotes (ujian online), Tes Menggambar (manual), dan
            // Wawancara. Kalau tipe hanya ada di level tahap, ketiganya ikut
            // dianggap ujian online — Wawancara pun diminta token & OTP HCLearn
            // yang tak pernah ada, dan kandidat diberi tahu "menunggu token"
            // untuk sesuatu yang sebenarnya sesi tatap muka.
            //
            // Maka tiap aktivitas membawa Tipe-nya sendiri; tipe tahap hanya
            // menjadi bawaan bila aktivitas tak menyebutkannya. Yang menentukan
            // CARA AKTIVITAS DIJALANKAN adalah Perilaku tipe aktivitas itu:
            // 'CAT' → ujian online yang dijadwalkan; selain itu → dikerjakan tim.
            $tests = array_values($s['tests'] ?? []);
            if (! $tests) {
                $tests = [[
                    'label' => $s['label'],
                    'peran' => 'PENENTU',
                    'ambang' => null,
                ]];
            }

            $tests = array_map(function ($t) use ($s, $adalahCat) {
                $t['tipe'] = $t['tipe'] ?? $s['tipe'];
                $t['provider'] = $adalahCat($t['tipe']) ? 'THIRD_PARTY' : 'INTERNAL';

                return $t;
            }, $tests);

            // Provider TAHAP = ada aktivitas ujian online di dalamnya. Dipakai
            // modul yang masih menyaring di level tahap (worklist, monitoring).
            $adaOnline = collect($tests)->contains(fn ($t) => $t['provider'] === 'THIRD_PARTY');
            $provider = $adaOnline ? 'THIRD_PARTY' : 'INTERNAL';

            $mode = $s['mode'] ?? ($adaOnline ? 'AUTO_SEMUA_LULUS' : 'MANUAL_REVIEW');

            // GUARD nol-PENENTU: mode auto-lanjut tanpa satu pun tes PENENTU akan
            // "lulus hampa" (maju tanpa ada yang menilai) — paksa ke MANUAL_REVIEW.
            $adaPenentu = collect($tests)->contains(fn ($t) => ($t['peran'] ?? 'PENENTU') === 'PENENTU');
            if (! $adaPenentu && ($autoLanjut[$mode] ?? 'N') === 'Y') {
                $mode = 'MANUAL_REVIEW';
            }

            // GUARD GAGAL-MENGGANTUNG: tahap yang memuat UJIAN ONLINE PENENTU tak
            // boleh memakai mode yang tidak menggugurkan otomatis. Hasil CAT sudah
            // final dan objektif — kalau gagalnya masih menunggu admin, kandidat
            // yang jelas-jelas tidak lulus menggantung entah berapa lama, dan
            // daftar "perlu keputusan" terisi perkara yang tak perlu ditimbang.
            // Modenya dinaikkan ke mode lain yang AUTO_GUGUR-nya menyala dengan
            // Auto_Lanjut yang sama — jadi keputusan "lulus" tetap milik admin
            // bila memang begitu setelannya. Pilihan diambil dari master, bukan
            // kode mode yang ditulis di sini.
            $onlinePenentu = collect($tests)->contains(
                fn ($t) => $t['provider'] === 'THIRD_PARTY' && ($t['peran'] ?? 'PENENTU') === 'PENENTU'
            );
            if ($onlinePenentu && ($autoGugur[$mode] ?? 'N') !== 'Y') {
                $pengganti = $modeSemua->first(
                    fn ($m) => $m->Auto_Gugur === 'Y' && $m->Auto_Lanjut === ($autoLanjut[$mode] ?? 'N')
                );
                if ($pengganti) {
                    $mode = $pengganti->Kode;
                }
            }

            $pengumuman = $s['pengumuman'] ?? 'OTOMATIS';
            $jeda = ($modeButuhJeda[$pengumuman] ?? false) ? ($s['jedaHari'] ?? null) : null;

            $tahapId = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->insertGetId([
                'Master_Alur_Id' => $alurId,
                'Urutan' => $i + 1,
                'Kode' => substr($kode, 0, 30),
                'Label' => $s['label'],
                'Tipe_Tahap_Kode' => $s['tipe'],
                'Provider' => $provider,
                'Keputusan' => ($autoLanjut[$mode] ?? 'N') === 'Y' ? 'SYSTEM' : 'MANUAL',
                'Formulir_Kode' => $s['formulirId'] ?? null,
                // Jenis_Tes_Kode tidak lagi dipakai — kolomnya dibiarkan ada demi
                // riwayat, tapi alur baru tidak menulisinya. Lihat catatan Provider.
                'Jenis_Tes_Kode' => null,
                'SLA' => null, // field SLA dihapus dari builder
                'Mode_Keputusan_Kode' => $mode,
                'Mode_Pengumuman' => $pengumuman,
                'Jeda_Pengumuman_Hari' => $jeda,
                // Konvensi proyek: 'Y' = ya, 'T' = tidak.
                'Flag_Notifikasi' => ($s['notifikasi'] ?? true) ? 'Y' : 'T',
                // Cut-off Talent Pool: aktif → worklist menampilkan tombol
                // "Masuk Talent Pool" saat memutus tahap ini.
                'Flag_Talent_Pool' => ($s['talentPool'] ?? false) ? 'Y' : 'T',
                // Upload berkas hasil — aktif bila admin menyalakannya, ATAU bila
                // tipe tahapnya memang berbasis berkas (flag dari Master Tipe
                // Tahap; dulu kode 'MCU' ditulis langsung di beberapa file).
                'Flag_Upload_Hasil' => (($s['uploadHasil'] ?? false) || ($tipe[$s['tipe']]->Flag_Upload_Hasil ?? 'T') === 'Y') ? 'Y' : 'T',
                'Flag_Wajib_Upload' => ($s['wajibUpload'] ?? false) ? 'Y' : 'T',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ], 'Id_Master_Alur_Tahap');

            foreach ($tests as $j => $t) {
                DB::table('N_WEB_CAREERS_Master_Alur_Tahap_Tes')->insert([
                    'Master_Alur_Tahap_Id' => $tahapId,
                    'Urutan' => $j + 1,
                    'Jenis_Tes_Kode' => null,
                    // Tipe & cara pelaksanaan MILIK AKTIVITAS INI sendiri.
                    'Tipe_Tahap_Kode' => $t['tipe'],
                    'Provider' => $t['provider'],
                    'Peran' => $t['peran'] ?? 'PENENTU',
                    // Default kebijakan: SEMUA tes wajib selesai. Kolom Wajib tetap
                    // ada di DB — bila nanti butuh opsional, buka lagi dari sini.
                    'Wajib' => 'Y',
                    'Ambang_Batas' => $t['ambang'] ?? null,
                    'Label' => $t['label'],
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
            }
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode dibuat memakai lebar kolom penuh; sufiks _N hanya bila bentrok.
            $kode = KodeUnik::buat('N_WEB_CAREERS_Master_Alur', 'Kode', $data['nama'], 30, 'ALUR');
            DB::transaction(function () use ($data, $kode, $userId, $userName, $now) {
                $id = DB::table('N_WEB_CAREERS_Master_Alur')->insertGetId([
                    'Kode' => $kode,
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Flag_Aktif' => 'Y',
                    'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                    'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ], 'Id_Master_Alur');

                $this->simpanTahap($id, $data['stages'] ?? [], $userId, $userName);
            });

            Log::channel('web_career')->info("Master alur dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(null, 'Alur berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat alur: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::transaction(function () use ($data, $realId, $userId, $userName) {
                DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->update([
                    'Nama' => $data['nama'],
                    'Kategori' => $data['kategori'],
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(), 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
                ]);
                $this->hapusTahap($realId);
                $this->simpanTahap($realId, $data['stages'] ?? [], $userId, $userName);
            });

            Log::channel('web_career')->info("Master alur #{$realId} diperbarui");

            return ResponseHelper::success(null, 'Alur diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update alur #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'N',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            Log::channel('web_career')->info("Master alur #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle alur #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            DB::transaction(function () use ($realId) {
                $this->hapusTahap($realId);
                DB::table('N_WEB_CAREERS_Master_Alur')->where('Id_Master_Alur', $realId)->delete();
            });
            Log::channel('web_career')->info("Master alur #{$realId} dihapus");

            return ResponseHelper::success(null, 'Alur dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus alur #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
