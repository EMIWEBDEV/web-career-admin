<?php

namespace App\Support\Career\Shell;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WEB CAREERS — SUMBER MENU SIDEBAR.
 *
 * ⛔ JANGAN MENAMBAH MENU DI BERKAS INI.
 *
 * Menu bukan lagi array di kode. Sumbernya tabel N_WEB_CAREERS_Menu, dan cara
 * menambahnya lewat halaman **Master Menu** (/master-menu) → lalu beri aksesnya
 * di **Manajemen Hak Akses** (/hak-akses).
 *
 * KENAPA: menu yang ditulis di kode dulu jadi titik bentrok nomor satu. Setiap
 * fitur baru menyisipkan satu baris di array yang sama, sehingga dua orang yang
 * bekerja paralel menabrak baris yang sama saat merge — dan yang kalah merge
 * kehilangan menunya diam-diam (route & halaman utuh, tapi menu lenyap).
 *
 * Daftar DARURAT di bawah hanya dipakai bila tabel menu belum ada / kosong
 * (mis. basis data baru). Sengaja minimal: cukup untuk masuk dan membuka
 * Master Menu, bukan salinan seluruh menu.
 */
class NavigasiShell
{
    private const TABEL = 'N_WEB_CAREERS_Menu';

    /**
     * Menu sesuai peran.
     *
     * Kandidat TIDAK boleh melihat menu admin sama sekali — bukan sekadar
     * ditolak saat diklik. Menu yang terlihat tapi selalu ditolak justru
     * membocorkan struktur sistem dan bikin bingung.
     */
    public static function untukPenggunaSaatIni(): array
    {
        // SUMBER UTAMA: paket hak akses di sesi (dibangun dari master menu +
        // Page_Access user). Hanya menu yang ia punya izin VIEW-nya yang muncul.
        $dariAkses = session('career_akses.menu');
        if (is_array($dariAkses) && $dariAkses) {
            return array_map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => array_map(fn ($it) => [
                    'key' => $it['key'],
                    'label' => $it['label'],
                    'icon' => $it['icon'],
                    'url' => $it['url'],
                ], $g['items']),
            ], $dariAkses);
        }

        // Cadangan: sesi lama / paket akses belum terbentuk → daftar dari master,
        // supaya panel tidak pernah tampil tanpa menu sama sekali.
        return IdentitasShell::adalahAdmin() ? self::admin() : self::kandidat();
    }

    /** Menu admin — dari tabel master; daftar darurat bila master kosong. */
    public static function admin(): array
    {
        return self::dariMaster('ADMIN') ?: [
            ['id' => 'hak-akses', 'title' => 'Hak Akses', 'items' => [
                ['key' => 'masterMenuPage', 'label' => 'Master Menu', 'icon' => 'bi bi-list-nested', 'url' => '/master-menu'],
                ['key' => 'hakAksesPage', 'label' => 'Manajemen Hak Akses', 'icon' => 'bi bi-person-lock', 'url' => '/hak-akses'],
            ]],
        ];
    }

    /** Menu portal kandidat — dari tabel master; daftar darurat bila master kosong. */
    public static function kandidat(): array
    {
        return self::dariMaster('KANDIDAT') ?: [
            ['id' => 'lamaran', 'title' => 'Lamaran Saya', 'items' => [
                ['key' => 'portalPage', 'label' => 'Lamaran Saya', 'icon' => 'bi bi-file-earmark-text', 'url' => '/kandidat/portal'],
            ]],
        ];
    }

    /**
     * Susun menu dari tabel master.
     * Dikelompokkan per Nama_Header, urut mengikuti kolom Urutan.
     */
    private static function dariMaster(string $role): array
    {
        try {
            $rows = Cache::remember(
                "wc_nav_master_{$role}",
                now()->addMinutes((int) config('career_shell.cache_menu_menit', 5)),
                fn () => DB::table(self::TABEL)
                    ->where('Untuk_Role', $role)
                    ->where('Flag_Aktif', 'Y')
                    ->orderBy('Urutan')
                    ->get(['Jenis_Page', 'Nama_Menu', 'Nama_Header', 'Icon_Menu', 'Url_Menu', 'Urutan'])
            );
        } catch (\Throwable $e) {
            return []; // tabel belum ada / DB bermasalah → pakai daftar darurat
        }

        $grup = [];
        foreach ($rows as $r) {
            $header = $r->Nama_Header ?: 'Menu';
            $grup[$header] ??= [];
            $grup[$header][] = [
                'key' => $r->Jenis_Page,
                'label' => $r->Nama_Menu,
                'icon' => $r->Icon_Menu ?: 'bi bi-dot',
                'url' => $r->Url_Menu ?: '#',
            ];
        }

        return array_map(fn ($header, $items) => [
            'id' => Str::slug($header),
            'title' => $header,
            'items' => $items,
        ], array_keys($grup), $grup);
    }

    /** Buang cache menu master — dipanggil setiap Master Menu berubah. */
    public static function lupakan(): void
    {
        foreach (['ADMIN', 'KANDIDAT'] as $r) {
            Cache::forget("wc_nav_master_{$r}");
        }
    }
}
