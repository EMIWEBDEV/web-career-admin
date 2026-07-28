<?php

namespace App\Support;

/**
 * WEB CAREER — Shell admin (sidebar + brand + auth) sebagai fungsi STATIK.
 * Dipakai langsung di controller: `return Inertia::render($komponen, CareerShell::props($url, $title));`
 * (tanpa $this->, tanpa mewarisi controller monolit).
 */
class CareerShell
{
    /** Props shell (layout + auth) untuk halaman master. */
    public static function props(string $url, string $title, array $extra = []): array
    {
        return array_merge($extra, [
            'layout' => self::layout($url, $title),
            'auth' => ['user' => self::adminUser()],
            // Hak akses halaman ini → dipakai Vue menyembunyikan tombol yang
            // tidak diizinkan (gerbang sebenarnya tetap di middleware server).
            'akses' => [
                'permissions' => session('career_akses.permissions', []),
                'konten' => session('career_akses.permission_konten', []),
            ],
        ]);
    }

    /** Identitas admin dari sesi login (career_auth); fallback netral bila belum login. */
    public static function adminUser(): array
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

    /**
     * Menu sesuai peran.
     *
     * Kandidat TIDAK boleh melihat menu admin sama sekali — bukan sekadar
     * ditolak saat diklik. Menu yang terlihat tapi selalu ditolak justru
     * membocorkan struktur sistem dan bikin bingung.
     */
    public static function nav(): array
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

        // Cadangan: sesi lama / paket akses belum terbentuk → daftar bawaan,
        // supaya panel tidak pernah tampil tanpa menu sama sekali.
        return self::adalahAdmin() ? self::adminNav() : self::kandidatNav();
    }

    /*
    |===========================================================================
    | ⛔ JANGAN MENAMBAH MENU DI BERKAS INI
    |===========================================================================
    | Menu TIDAK lagi ditulis sebagai array di sini. Sumbernya tabel
    | N_WEB_CAREERS_Menu, dan cara menambahnya lewat halaman **Master Menu**
    | (/master-menu) → lalu beri aksesnya di **Manajemen Hak Akses** (/hak-akses).
    |
    | KENAPA: berkas ini dulu jadi titik bentrok nomor satu. Setiap fitur baru
    | menyisipkan satu baris di array yang sama, sehingga dua orang yang bekerja
    | paralel hampir pasti menabrak baris yang sama saat merge — dan yang kalah
    | merge kehilangan menunya diam-diam.
    |
    | Langkah menambah menu baru (tanpa menyentuh berkas ini sama sekali):
    |   1. Master Menu → "Menu Baru": isi Kunci Halaman (mis. masterAnuPage),
    |      nama, grup, URL, ikon, peran.
    |   2. Manajemen Hak Akses → "Beri Akses": centang aksinya untuk pengguna.
    |   3. Kunci route-nya:
    |        ->middleware('career.permission:masterAnuPage,VIEW')
    |
    | Daftar DARURAT di bawah hanya dipakai bila tabel menu belum ada / kosong
    | (mis. basis data baru). Isinya sengaja minimal — cukup untuk masuk dan
    | membuka Master Menu, bukan salinan seluruh menu.
    */

    /** Menit cache daftar menu master. Pendek — perubahan menu harus cepat terasa. */
    private const CACHE_MENU_MENIT = 5;

    /**
     * Susun menu dari tabel master (bukan array kode).
     * Dikelompokkan per Nama_Header, urut mengikuti kolom Urutan.
     */
    private static function navDariMaster(string $role): array
    {
        try {
            $rows = \Illuminate\Support\Facades\Cache::remember(
                "wc_nav_master_{$role}",
                now()->addMinutes(self::CACHE_MENU_MENIT),
                fn () => \Illuminate\Support\Facades\DB::table('N_WEB_CAREERS_Menu')
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
            'id' => \Illuminate\Support\Str::slug($header),
            'title' => $header,
            'items' => $items,
        ], array_keys($grup), $grup);
    }

    /** Buang cache menu master — dipanggil setiap Master Menu berubah. */
    public static function lupakanNav(): void
    {
        foreach (['ADMIN', 'KANDIDAT'] as $r) {
            \Illuminate\Support\Facades\Cache::forget("wc_nav_master_{$r}");
        }
    }

    /** Menu portal kandidat — dari master; daftar darurat bila master kosong. */
    public static function kandidatNav(): array
    {
        $dariMaster = self::navDariMaster('KANDIDAT');
        if ($dariMaster) {
            return $dariMaster;
        }

        return [
            ['id' => 'lamaran', 'title' => 'Lamaran Saya', 'items' => [
                ['key' => 'portalPage', 'label' => 'Lamaran Saya', 'icon' => 'bi bi-file-earmark-text', 'url' => '/kandidat/portal'],
            ]],
        ];
    }

    /**
     * Menu admin — DARI TABEL MASTER (N_WEB_CAREERS_Menu), bukan array di sini.
     * Lihat catatan besar di atas: jangan menambah menu di berkas ini.
     */
    public static function adminNav(): array
    {
        $dariMaster = self::navDariMaster('ADMIN');
        if ($dariMaster) {
            return $dariMaster;
        }

        // DARURAT — hanya saat tabel menu belum ada / kosong. Sengaja minimal:
        // cukup untuk masuk lalu mendaftarkan menu lewat Master Menu.
        return [
            ['id' => 'hak-akses', 'title' => 'Hak Akses', 'items' => [
                ['key' => 'masterMenuPage', 'label' => 'Master Menu', 'icon' => 'bi bi-list-nested', 'url' => '/master-menu'],
                ['key' => 'hakAksesPage', 'label' => 'Manajemen Hak Akses', 'icon' => 'bi bi-person-lock', 'url' => '/hak-akses'],
            ]],
        ];
    }

    /** Payload shell (brand + navigation + meta) — sama seperti InertiaShellService. */
    public static function layout(string $activeUrl, string $title): array
    {
        $code = 'CAREER';
        $label = 'Web Career';
        // Beranda ikut peran: admin ke panel, kandidat ke portalnya sendiri.
        $landingUrl = self::adalahAdmin() ? '/karir' : '/kandidat/portal';
        $homeUrl = $landingUrl;

        $groups = collect(self::nav())
            ->map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => collect($g['items'])
                    ->map(fn ($it) => [
                        'id' => $it['key'],
                        'jenisPage' => strtolower($code) . '-' . $it['key'],
                        'title' => $it['label'],
                        'subtitle' => '',
                        'url' => $it['url'],
                        'icon' => $it['icon'],
                        'target' => 'self',
                        'isActive' => $it['url'] === $activeUrl,
                    ])
                    ->all(),
            ])
            ->all();

        $activeItem = collect($groups)->flatMap(fn ($g) => $g['items'])->firstWhere('isActive', true);
        $activeGroup = collect($groups)->first(fn ($g) => collect($g['items'])->firstWhere('isActive', true));
        $groupId = $activeGroup['id'] ?? '';

        $module = [
            'id' => $code,
            'code' => $code,
            'name' => $label,
            'label' => $label,
            'subtitle' => 'Rekrutmen & MT',
            'icon' => 'bi bi-briefcase-fill',
            'sortOrder' => 1,
            'landingJenisPage' => '',
            'landingUrl' => $landingUrl,
            'landingTarget' => 'self',
            'isActive' => true,
            'activeGroupId' => $groupId,
            'groups' => $groups,
        ];

        return [
            'brand' => [
                'appName' => 'EVO Group Unified Platform',
                'appShortName' => 'EVO',
                'mainLogo' => asset('logo/EVOGROUP.png'),
                'mainLogoAlt' => 'EVO Group',
                'subsidiaries' => [
                    ['id' => 'emi', 'name' => 'PT Evo Manufacturing Indonesia', 'src' => asset('logo/EMI.png'), 'fallback' => 'EMI'],
                    ['id' => 'enb', 'name' => 'PT Evo Nusa Bersaudara', 'src' => asset('logo/ENB.png'), 'fallback' => 'ENB'],
                    ['id' => 'gmn', 'name' => 'PT Graha Maju Nusantara', 'src' => asset('logo/GMN.png'), 'fallback' => 'GMN'],
                ],
            ],
            'navigation' => [
                'sectionLabel' => $label,
                'home' => [
                    'title' => 'Dashboard',
                    'subtitle' => 'Halaman Utama',
                    'url' => $homeUrl,
                    'icon' => 'bi bi-house-door-fill',
                    'isActive' => $homeUrl === $activeUrl,
                ],
                'modules' => [$module],
            ],
            'shell' => [
                'currentUrl' => $activeUrl,
                'activeModule' => $code,
                'activeModuleLabel' => $label,
                'activeModuleUrl' => $landingUrl,
                'activeSubMenu' => $activeItem['jenisPage'] ?? null,
                'activeSubMenuLabel' => $activeItem['title'] ?? null,
                'currentPageTitle' => $title,
                'breadcrumbs' => [
                    ['label' => 'HOME', 'url' => $homeUrl, 'active' => false],
                    ['label' => $code, 'url' => $landingUrl, 'active' => false],
                    ['label' => $title, 'url' => $activeUrl, 'active' => true],
                ],
            ],
            'notifications' => [],
            'unreadNotificationsCount' => 0,
            'messages' => [],
            'help' => [],
            'appVersion' => config('services.project_config.app_version', '3.0.0'),
        ];
    }
}
