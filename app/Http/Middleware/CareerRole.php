<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WEB CAREER — gerbang peran.
 *
 * Dipakai: ->middleware('career.role:ADMIN,SUPERADMIN')
 *
 * Selalu dipasang SESUDAH career.auth, karena middleware ini menganggap sesi
 * sudah diperiksa keabsahannya (termasuk peran yang sudah disegarkan dari DB).
 *
 * Panel ini hanya untuk staf. Sesi tanpa peran admin dikembalikan ke halaman
 * masuk — portal kandidat ada di situs kandidat (project web-careers-pengguna).
 */
class CareerRole
{
    /** Peran yang boleh membuka panel admin. */
    public const ADMIN = ['ADMIN', 'SUPERADMIN'];

    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $role = session('career_auth.role');

        if ($role && in_array($role, $peran, true)) {
            return $next($request);
        }

        $pesan = 'Anda tidak punya akses ke halaman tersebut.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'status' => 403, 'message' => $pesan], 403);
        }

        return redirect(self::beranda($role))->with('pesan', $pesan);
    }

    /** Beranda yang sesuai untuk sebuah peran. Dipakai juga sesudah login. */
    public static function beranda(?string $role): string
    {
        return in_array($role, self::ADMIN, true) ? '/karir' : '/login';
    }
}
