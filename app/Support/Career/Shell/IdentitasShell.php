<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — SIAPA yang sedang membuka shell.
 *
 * Dipisah dari CareerShell supaya urusan peran/identitas punya berkasnya
 * sendiri: kalau aturan peran berubah, yang tersentuh hanya berkas ini.
 */
class IdentitasShell
{
    /** Peran yang boleh membuka panel admin. */
    public const PERAN_ADMIN = ['ADMIN', 'SUPERADMIN'];

    /** Peran pengguna saat ini (dari sesi). */
    public static function peran(): string
    {
        return session('career_auth.role') ?: 'KANDIDAT';
    }

    public static function adalahAdmin(): bool
    {
        return in_array(self::peran(), self::PERAN_ADMIN, true);
    }

    /** Beranda sesuai peran: admin ke panel, kandidat ke portalnya sendiri. */
    public static function beranda(): string
    {
        return self::adalahAdmin() ? '/karir' : '/kandidat/portal';
    }

    /** Identitas admin dari sesi login (career_auth); fallback netral bila belum login. */
    public static function pengguna(): array
    {
        $auth = session('career_auth');
        $role = $auth['role'] ?? 'ADMIN';

        return [
            'name' => $auth['nama'] ?? 'Administrator',
            'username' => $auth['email'] ?? 'admin',
            'nik' => $role,
            'kode_karyawan' => '-',
            'department' => $role === 'SUPERADMIN' ? 'Superadmin' : 'Administrator',
        ];
    }
}
