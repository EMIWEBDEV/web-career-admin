<?php

namespace App\Support\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RIWAYAT LOGIN STAF (N_WEB_CAREERS_Login_Riwayat) — masuk, gagal, keluar,
 * dan sesi yang dikeluarkan sistem. Kandidat masuk di situs kandidat
 * (database publik), bukan di sini.
 *
 * Tidak pernah mencatat kata sandi. Sesi disimpan sebagai 16 heksadesimal
 * awal SHA-256 id sesinya — cukup untuk mengaitkan MASUK dengan KELUAR tanpa
 * membocorkan sesi. Gagal mencatat TIDAK boleh menggagalkan login.
 */
final class RiwayatLogin
{
    public const TABEL = 'N_WEB_CAREERS_Login_Riwayat';

    public const MASUK = 'MASUK';

    public const GAGAL = 'GAGAL';

    public const KELUAR = 'KELUAR';

    public const DIKELUARKAN = 'DIKELUARKAN';

    public static function catat(Request $request, string $peristiwa, ?int $idUsers, ?string $email, ?string $alasan = null): void
    {
        try {
            DB::table(self::TABEL)->insert([
                'Peristiwa' => $peristiwa,
                'Alasan' => $alasan,
                'Id_Users' => $idUsers,
                'Email' => $email !== null && $email !== '' ? mb_substr(mb_strtolower(trim($email)), 0, 150) : null,
                'Alamat_IP' => $request->ip() ? mb_substr((string) $request->ip(), 0, 45) : null,
                'Agen' => $request->userAgent() ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'Sesi' => $request->hasSession() ? substr(hash('sha256', $request->session()->getId()), 0, 16) : null,
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->warning("[AUDIT] riwayat login {$peristiwa} gagal dicatat: ".$e->getMessage());
        }
    }
}
