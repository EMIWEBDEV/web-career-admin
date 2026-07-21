<?php

namespace App\Http\Controllers\Career\MasterKampus;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER KAMPUS (SPA + WEB, tanpa API), pola Master Siklus.
 * - index()  : render halaman (shell admin via CareerShell::props).
 * - list()   : data kampus (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Tabel: N_WEB_CAREERS_Master_Kampus (PK Id_Master_Kampus).
 */
class MasterKampusController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-kampus/masterKampus', CareerShell::props('/master-kampus', 'Master Kampus'));
    }

    /** Data list Master Kampus (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Kampus as k')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'k.Created_By_Id')
                ->orderBy('k.Id_Master_Kampus')
                ->select('k.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => Hashids::encode($r->Id_Master_Kampus),
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'singkatan' => $r->Singkatan,
                        'kota' => $r->Kota,
                        'akreditasi' => $r->Akreditasi,
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data kampus dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kampus: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data kampus', 500);
        }
    }

    /** Tambah kampus. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:150',
                'singkatan' => 'nullable|string|max:30',
                'kota' => 'nullable|string|max:80',
                'akreditasi' => 'nullable|string|max:30',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = uppercased dari singkatan (bila ada) atau nama; dijamin unik.
            $sumber = !empty($data['singkatan']) ? $data['singkatan'] : $data['nama'];
            $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($sumber)), '_') ?: 'KAMPUS';
            $base = substr($base, 0, 26); // Kode varchar(30): sisakan ruang untuk sufiks _N
            $kode = $base;
            $n = 2;
            while (DB::table('N_WEB_CAREERS_Master_Kampus')->where('Kode', $kode)->exists()) {
                $kode = $base . '_' . $n++;
            }

            DB::table('N_WEB_CAREERS_Master_Kampus')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Singkatan' => $data['singkatan'] ?? null,
                'Kota' => $data['kota'] ?? null,
                'Akreditasi' => $data['akreditasi'] ?? null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $userName,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $userName,
                'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master kampus dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Kampus berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kampus: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah kampus. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Kampus')->where('Id_Master_Kampus', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:150',
                'singkatan' => 'nullable|string|max:30',
                'kota' => 'nullable|string|max:80',
                'akreditasi' => 'nullable|string|max:30',
            ]);

            DB::table('N_WEB_CAREERS_Master_Kampus')
                ->where('Id_Master_Kampus', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Singkatan' => $data['singkatan'] ?? null,
                    'Kota' => $data['kota'] ?? null,
                    'Akreditasi' => $data['akreditasi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master kampus #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kampus diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kampus #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif kampus. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kampus')
                ->where('Id_Master_Kampus', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kampus #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kampus #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus kampus. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kampus')->where('Id_Master_Kampus', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kampus #{$id} dihapus");

            return ResponseHelper::success(null, 'Kampus dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kampus #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
