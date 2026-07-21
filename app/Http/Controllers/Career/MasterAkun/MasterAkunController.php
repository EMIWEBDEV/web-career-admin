<?php

namespace App\Http\Controllers\Career\MasterAkun;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER AKUN (Users: pengguna/pelamar + admin/superadmin).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel. Password di-Hash, id di-Hashids.
 */
class MasterAkunController extends Controller
{
    public function index()
    {
        return Inertia::render('Career/admin/master-akun/masterAkun', CareerShell::props('/master-akun', 'Master Akun'));
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Users as u')
                ->leftJoin('N_WEB_CAREERS_Users as c', 'c.Id_Users', '=', 'u.Created_By_Id')
                ->orderByDesc('u.Id_Users')
                ->select('u.*', 'c.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Users),
                    'nama' => $r->Nama,
                    'email' => $r->Email,
                    'phone' => $r->No_Hp,
                    'role' => $r->Role,
                    'klasifikasi' => $r->Klasifikasi,
                    'status' => $r->Status,
                    'mulai_berlaku' => $r->Mulai_Berlaku,
                    'valid_until' => $r->Valid_Until,
                    'last_login_at' => $r->Last_Login_At ?? null,
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data akun dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data akun', 500);
        }
    }

    /** Hitung Valid_Until dari klasifikasi (Durasi_Hari NULL = permanen). */
    private function hitungValidUntil(string $kode, Carbon $mulai): ?string
    {
        $klas = DB::table('N_WEB_CAREERS_Klasifikasi_Akun')->where('Kode', $kode)->first();
        if ($klas && $klas->Durasi_Hari !== null) {
            return $mulai->copy()->addDays((int) $klas->Durasi_Hari)->toDateString();
        }

        return null;
    }

    private function rules(bool $create = true): array
    {
        return [
            'nama' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'password' => ($create ? 'required' : 'nullable') . '|string|min:6',
            'role' => 'required|in:KANDIDAT,ADMIN,SUPERADMIN',
            'klasifikasi' => 'required|string|max:40',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(true));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            $now = now();
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::table('N_WEB_CAREERS_Users')->insert([
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Password' => Hash::make($data['password']),
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $now),
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Akun dibuat ({$data['role']}) {$data['email']} oleh {$userName}");

            return ResponseHelper::success(null, 'Akun berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal buat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan akun', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            $data = $request->validate($this->rules(false));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->where('Id_Users', '!=', $realId)->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            $now = now();
            $mulai = $row->Mulai_Berlaku ? Carbon::parse($row->Mulai_Berlaku) : $now;
            $update = [
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $mulai),
                'Updated_At' => $now, 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ];
            if (! empty($data['password'])) {
                $update['Password'] = Hash::make($data['password']);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update($update);
            Log::channel('web_career')->info("Akun diperbarui #{$realId} ({$data['email']})");

            return ResponseHelper::success(null, 'Akun diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui akun', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update([
                'Status' => $aktif ? 'AKTIF' : 'NONAKTIF',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            Log::channel('web_career')->info("Akun #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status akun diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            if ($row->Role === 'SUPERADMIN' && DB::table('N_WEB_CAREERS_Users')->where('Role', 'SUPERADMIN')->count() <= 1) {
                return ResponseHelper::error('Tidak dapat menghapus Superadmin terakhir.', 422);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->delete();
            Log::channel('web_career')->info("Akun dihapus #{$realId} ({$row->Email})");

            return ResponseHelper::success(null, 'Akun dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus akun', 500);
        }
    }
}
