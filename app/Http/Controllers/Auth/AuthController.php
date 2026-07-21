<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * WEB CAREER — AUTH (REAL, DB N_WEB_CAREERS_Users) memakai QUERY BUILDER.
 * Controller WEB, dipanggil via axios ke route web ber-prefix api/v1 → balas JSON.
 */
class AuthController extends Controller
{
    private string $table = 'N_WEB_CAREERS_Users';

    private string $klasTable = 'N_WEB_CAREERS_Klasifikasi_Akun';

    public function register(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
        ]);

        $now = Carbon::now();

        // Masa berlaku registrasi diambil dari tabel config (TIDAK hardcode).
        $klas = DB::table($this->klasTable)->where('Is_Default_Register', 'Y')->where('Flag_Aktif', 'Y')->first();
        $kode = $klas->Kode ?? 'PERMANEN';
        $validUntil = ($klas && $klas->Durasi_Hari !== null)
            ? $now->copy()->addDays((int) $klas->Durasi_Hari)->toDateString()
            : null;

        $existing = DB::table($this->table)->where('Email', $data['email'])->first();

        if ($existing) {
            $stillValid = $existing->Status === 'AKTIF'
                && ($existing->Valid_Until === null || Carbon::parse($existing->Valid_Until)->startOfDay()->greaterThanOrEqualTo($now->copy()->startOfDay()));

            if ($stillValid) {
                return ResponseHelper::error('Email sudah terdaftar dan masih aktif.', 422);
            }

            // Akun lama sudah kedaluwarsa/nonaktif → daftar ulang (perbarui akun yang sama).
            DB::table($this->table)->where('Id_Users', $existing->Id_Users)->update([
                'Nama' => $data['nama'],
                'No_Hp' => $data['phone'] ?? $existing->No_Hp,
                'Password' => Hash::make($data['password']),
                'Klasifikasi' => $kode,
                'Status' => 'AKTIF',
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $validUntil,
                'Updated_At' => $now,
                'Updated_By' => $data['email'],
            ]);

            $user = ['id' => $existing->Id_Users, 'nama' => $data['nama'], 'email' => $data['email'], 'role' => $existing->Role, 'klasifikasi' => $kode, 'valid_until' => $validUntil];
            $request->session()->put('career_auth', $user);

            return ResponseHelper::success($user, 'Pendaftaran ulang berhasil (akun sebelumnya telah kedaluwarsa).');
        }

        $id = DB::table($this->table)->insertGetId([
            'Nama' => $data['nama'],
            'Email' => $data['email'],
            'No_Hp' => $data['phone'] ?? null,
            'Password' => Hash::make($data['password']),
            'Role' => 'KANDIDAT',
            'Klasifikasi' => $kode,
            'Status' => 'AKTIF',
            'Mulai_Berlaku' => $now->toDateString(),
            'Valid_Until' => $validUntil,
            'Created_At' => $now,
            'Created_By' => $data['email'],
            'Updated_At' => $now,
            'Updated_By' => $data['email'],
        ], 'Id_Users');

        $user = ['id' => $id, 'nama' => $data['nama'], 'email' => $data['email'], 'role' => 'KANDIDAT', 'klasifikasi' => $kode, 'valid_until' => $validUntil];
        $request->session()->put('career_auth', $user);

        return ResponseHelper::success($user, 'Registrasi berhasil.', 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        if (! $row || ! Hash::check($data['password'], $row->Password)) {
            return ResponseHelper::error('Email atau kata sandi salah.', 401);
        }

        if ($row->Status !== 'AKTIF') {
            return ResponseHelper::error('Akun Anda dinonaktifkan. Silakan hubungi tim rekrutmen EVO Group.', 403);
        }

        if ($row->Valid_Until !== null && Carbon::parse($row->Valid_Until)->startOfDay()->lessThan(Carbon::now()->startOfDay())) {
            return ResponseHelper::error('Masa berlaku akun Anda telah berakhir pada ' . Carbon::parse($row->Valid_Until)->format('d M Y') . '.', 403);
        }

        DB::table($this->table)->where('Id_Users', $row->Id_Users)->update(['Last_Login_At' => Carbon::now()]);

        $user = ['id' => $row->Id_Users, 'nama' => $row->Nama, 'email' => $row->Email, 'role' => $row->Role, 'klasifikasi' => $row->Klasifikasi, 'valid_until' => $row->Valid_Until];
        $request->session()->put('career_auth', $user);

        return ResponseHelper::success($user, 'Login berhasil.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('career_auth');

        return ResponseHelper::success(null, 'Logout berhasil.');
    }

    /** Ganti / reset kata sandi berdasarkan email (sementara tanpa verifikasi token). */
    public function gantiSandi(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $exists = DB::table($this->table)->where('Email', $data['email'])->exists();
        if (! $exists) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        DB::table($this->table)->where('Email', $data['email'])->update([
            'Password' => Hash::make($data['password']),
            'Updated_At' => Carbon::now(),
            'Updated_By' => $data['email'],
        ]);

        return ResponseHelper::success(null, 'Kata sandi berhasil diperbarui. Silakan masuk kembali.');
    }
}
