<?php

namespace App\Http\Controllers\Career\MasterKriteria;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER KRITERIA (SPA + WEB, tanpa API).
 * Katalog field kelayakan/knock-out (mesin auto-gugur dinamis).
 * - index()  : return Inertia (render halaman) — shell admin via CareerShell::props.
 * - list()   : data kriteria (Query Builder) -> ResponseHelper (dihit axios saat mount).
 * - store/update/toggle/destroy : CRUD Query Builder -> ResponseHelper, try/catch, Log channel.
 * Operator_List & Opsi_List disimpan sebagai string dipisah koma; dikirim ke UI sebagai ARRAY.
 */
class MasterKriteriaController extends Controller
{
    /** Halaman (Inertia). Data TIDAK dikirim lewat props — halaman fetch sendiri ke list(). */
    public function index()
    {
        return Inertia::render('Career/admin/master-kriteria/masterKriteria', CareerShell::props('/master-kriteria', 'Master Kriteria'));
    }

    /** Data list Master Kriteria (Query Builder + join nama pembuat) -> ResponseHelper. */
    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Kriteria as k')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'k.Created_By_Id')
                ->orderBy('k.Id_Master_Kriteria')
                ->select('k.*', 'u.Nama as Pembuat')
                ->get()
                ->map(function ($r) {
                    $ops = $r->Operator_List ? array_values(array_filter(explode(',', $r->Operator_List), fn ($v) => $v !== '')) : [];
                    $opsi = $r->Opsi_List ? array_values(array_filter(explode(',', $r->Opsi_List), fn ($v) => $v !== '')) : [];

                    return [
                        'id' => Hashids::encode($r->Id_Master_Kriteria), // id di-hash (raw id tidak diekspos)
                        'kode' => $r->Kode,
                        'nama' => $r->Nama,
                        'field' => $r->Field,
                        'tipe' => $r->Tipe,
                        'satuan' => $r->Satuan,
                        'ikon' => $r->Ikon,
                        'ops' => $ops,
                        'opsi' => $opsi,
                        'sifat' => $r->Flag_Sistem === 'Y' ? 'SISTEM' : 'KUSTOM',
                        'status' => $r->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                        'deskripsi' => $r->Deskripsi,
                        'createdBy' => $r->Pembuat ?: $r->Created_By,
                        'createdAt' => $r->Created_At,
                    ];
                })
                ->values();

            return ResponseHelper::success($rows, 'Data kriteria dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat kriteria: ' . $e->getMessage());
            return ResponseHelper::error('Gagal memuat data kriteria', 500);
        }
    }

    /** Tambah kriteria. */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'field' => 'required|string|max:50',
                'tipe' => 'required|string|max:20',
                'satuan' => 'nullable|string|max:20',
                'ikon' => 'nullable|string|max:50',
                'ops' => 'nullable|array',
                'opsi' => 'nullable|array',
                'deskripsi' => 'nullable|string|max:500',
            ]);

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();

            // Kode = NAMA di-uppercase, unik.
            $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($data['nama'])), '_') ?: 'KRITERIA';
            $base = substr($base, 0, 26); // Kode varchar(30): sisakan ruang untuk sufiks _N
            $kode = $base;
            $n = 2;
            while (DB::table('N_WEB_CAREERS_Master_Kriteria')->where('Kode', $kode)->exists()) {
                $kode = $base . '_' . $n++;
            }

            DB::table('N_WEB_CAREERS_Master_Kriteria')->insert([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Field' => $data['field'],
                'Tipe' => $data['tipe'],
                'Satuan' => $data['satuan'] ?? null,
                'Ikon' => $data['ikon'] ?? null,
                'Operator_List' => implode(',', $data['ops'] ?? []),
                'Opsi_List' => implode(',', $data['opsi'] ?? []),
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Flag_Sistem' => 'N',
                'Flag_Aktif' => 'Y',
                'Created_At' => $now,
                'Created_By' => $userName,
                'Created_By_Id' => $userId,
                'Updated_At' => $now,
                'Updated_By' => $userName,
                'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Master kriteria dibuat ({$kode}) oleh {$userName}");
            return ResponseHelper::success(null, 'Kriteria berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat kriteria: ' . $e->getMessage());
            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    /** Ubah kriteria. */
    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Kriteria')->where('Id_Master_Kriteria', $realId)->first();
            if (!$row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate([
                'nama' => 'required|string|max:80',
                'field' => 'required|string|max:50',
                'tipe' => 'required|string|max:20',
                'satuan' => 'nullable|string|max:20',
                'ikon' => 'nullable|string|max:50',
                'ops' => 'nullable|array',
                'opsi' => 'nullable|array',
                'deskripsi' => 'nullable|string|max:500',
            ]);

            DB::table('N_WEB_CAREERS_Master_Kriteria')
                ->where('Id_Master_Kriteria', $realId)
                ->update([
                    'Nama' => $data['nama'],
                    'Field' => $data['field'],
                    'Tipe' => $data['tipe'],
                    'Satuan' => $data['satuan'] ?? null,
                    'Ikon' => $data['ikon'] ?? null,
                    'Operator_List' => implode(',', $data['ops'] ?? []),
                    'Opsi_List' => implode(',', $data['opsi'] ?? []),
                    'Deskripsi' => $data['deskripsi'] ?? null,
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            Log::channel('web_career')->info("Master kriteria #{$id} diperbarui");

            return ResponseHelper::success(null, 'Kriteria diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update kriteria #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    /** Aktif/Nonaktif kriteria. */
    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kriteria')
                ->where('Id_Master_Kriteria', $realId)
                ->update([
                    'Flag_Aktif' => $aktif ? 'Y' : 'N',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kriteria #{$id} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle kriteria #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /** Hapus kriteria. */
    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Kriteria')->where('Id_Master_Kriteria', $realId)->delete();
            if (!$terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            Log::channel('web_career')->info("Master kriteria #{$id} dihapus");

            return ResponseHelper::success(null, 'Kriteria dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus kriteria #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }
}
