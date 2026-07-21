<?php

namespace App\Http\Controllers\Career\MasterAlur;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function list()
    {
        try {
            $alur = DB::table('N_WEB_CAREERS_Master_Alur as a')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'a.Created_By_Id')
                ->orderBy('a.Id_Master_Alur')
                ->select('a.*', 'u.Nama as Pembuat')
                ->get();

            $tahap = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->orderBy('Urutan')->get()->groupBy('Master_Alur_Id');

            $rows = $alur->map(function ($a) use ($tahap) {
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
                        'tesId' => $t->Jenis_Tes_Kode,
                        'sla' => $t->SLA,
                        // Aturan pengumuman hasil tahap ini (lihat Batch 6).
                        'pengumuman' => $t->Mode_Pengumuman ?? 'OTOMATIS',
                        'jedaHari' => isset($t->Jeda_Pengumuman_Hari) ? $t->Jeda_Pengumuman_Hari : null,
                        'notifikasi' => ($t->Flag_Notifikasi ?? 'Y') === 'Y',
                    ])->values(),
                ];
            })->values();

            return ResponseHelper::success($rows, 'Data alur dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat alur: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data alur', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:120',
            'kategori' => 'required|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'stages' => 'nullable|array',
            'stages.*.label' => 'required|string|max:120',
            'stages.*.tipe' => 'required|string|max:30',
            'stages.*.provider' => 'nullable|string|max:20',
            'stages.*.formulirId' => 'nullable|string|max:30',
            'stages.*.tesId' => 'nullable|string|max:30',
            'stages.*.sla' => 'nullable|string|max:30',
            // Pengumuman hasil tahap. Jeda hari hanya bermakna untuk TERJADWAL.
            'stages.*.pengumuman' => 'nullable|in:OTOMATIS,TERJADWAL,MANUAL',
            'stages.*.jedaHari' => 'nullable|integer|min:0|max:3650',
            'stages.*.notifikasi' => 'nullable|boolean',
        ];
    }

    private function simpanTahap(int $alurId, array $stages, ?int $userId, string $userName): void
    {
        $now = now();
        foreach (array_values($stages) as $i => $s) {
            $provider = $s['provider'] ?? 'INTERNAL';
            $kode = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($s['label'])), '_') ?: ('TAHAP_' . ($i + 1));

            // Jeda hari hanya relevan untuk TERJADWAL — mode lain disimpan NULL
            // supaya tidak ada angka menggantung yang menyesatkan saat dibaca.
            $pengumuman = $s['pengumuman'] ?? 'OTOMATIS';
            $jeda = $pengumuman === 'TERJADWAL' ? ($s['jedaHari'] ?? null) : null;

            DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->insert([
                'Master_Alur_Id' => $alurId,
                'Urutan' => $i + 1,
                'Kode' => substr($kode, 0, 30),
                'Label' => $s['label'],
                'Tipe_Tahap_Kode' => $s['tipe'],
                'Provider' => $provider,
                'Keputusan' => $provider === 'THIRD_PARTY' ? 'SYSTEM' : 'MANUAL',
                'Formulir_Kode' => $s['formulirId'] ?? null,
                'Jenis_Tes_Kode' => $provider === 'THIRD_PARTY' ? ($s['tesId'] ?? null) : null,
                'SLA' => $s['sla'] ?? null,
                'Mode_Pengumuman' => $pengumuman,
                'Jeda_Pengumuman_Hari' => $jeda,
                // Konvensi proyek: 'Y' = ya, 'T' = tidak.
                'Flag_Notifikasi' => ($s['notifikasi'] ?? true) ? 'Y' : 'T',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($data['nama'])), '_') ?: 'ALUR';
            $base = substr($base, 0, 26); // Kode varchar(30): sisakan ruang untuk sufiks _N
            $kode = $base;
            $n = 2;
            while (DB::table('N_WEB_CAREERS_Master_Alur')->where('Kode', $kode)->exists()) {
                $kode = $base . '_' . $n++;
            }

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
                DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $realId)->delete();
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
                DB::table('N_WEB_CAREERS_Master_Alur_Tahap')->where('Master_Alur_Id', $realId)->delete();
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
