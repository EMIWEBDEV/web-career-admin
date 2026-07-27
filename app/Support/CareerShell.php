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

    /** Menu portal kandidat — hanya miliknya sendiri. */
    public static function kandidatNav(): array
    {
        return [
            ['id' => 'lamaran', 'title' => 'Lamaran Saya', 'items' => [
                ['key' => 'portal', 'label' => 'Lamaran Saya', 'icon' => 'bi bi-file-earmark-text', 'url' => '/kandidat/portal'],
                ['key' => 'loker', 'label' => 'Cari Lowongan', 'icon' => 'bi bi-search', 'url' => '/karir/landing-page'],
            ]],
            ['id' => 'akun', 'title' => 'Akun', 'items' => [
                ['key' => 'profil', 'label' => 'Profil Saya', 'icon' => 'bi bi-person-circle', 'url' => '/profil'],
            ]],
        ];
    }

    /** Menu Web Career (admin) — dikelompokkan di sidebar. */
    public static function adminNav(): array
    {
        return [
            ['id' => 'master', 'title' => 'Master Data', 'items' => [
                ['key' => 'master-talent', 'label' => 'Master Talent Acquisition', 'icon' => 'bi bi-diagram-3', 'url' => '/master-talent'],
                ['key' => 'master-perilaku', 'label' => 'Master Perilaku', 'icon' => 'bi bi-lightning-charge', 'url' => '/master-perilaku'],
                ['key' => 'master-mode', 'label' => 'Master Mode Pelaksanaan', 'icon' => 'bi bi-toggles', 'url' => '/master-mode'],
                ['key' => 'master-sumber', 'label' => 'Master Sumber Kandidat', 'icon' => 'bi bi-broadcast-pin', 'url' => '/master-sumber'],
                ['key' => 'master-tipe', 'label' => 'Master Tipe Tahap', 'icon' => 'bi bi-diagram-2', 'url' => '/master-tipe'],
                ['key' => 'master-formulir', 'label' => 'Master Formulir', 'icon' => 'bi bi-input-cursor-text', 'url' => '/master-formulir'],
                ['key' => 'master-tes', 'label' => 'Master Jenis Tes', 'icon' => 'bi bi-ui-checks-grid', 'url' => '/master-tes'],
                ['key' => 'master-kampus', 'label' => 'Master Kampus', 'icon' => 'bi bi-mortarboard', 'url' => '/master-kampus'],
                ['key' => 'master-kemitraan', 'label' => 'Master Kemitraan / MoU', 'icon' => 'bi bi-file-earmark-medical', 'url' => '/master-kemitraan'],
                ['key' => 'master-alur', 'label' => 'Master Tahapan Seleksi', 'icon' => 'bi bi-signpost-split', 'url' => '/master-alur'],
                ['key' => 'master-mode-pengumuman', 'label' => 'Master Mode Pengumuman', 'icon' => 'bi bi-megaphone', 'url' => '/master-mode-pengumuman'],
                ['key' => 'master-mode-keputusan', 'label' => 'Master Mode Keputusan', 'icon' => 'bi bi-diagram-3', 'url' => '/master-mode-keputusan'],
                ['key' => 'master-klasifikasi', 'label' => 'Master Kategori', 'icon' => 'bi bi-tags', 'url' => '/master-kategori'],
                ['key' => 'master-jadwal', 'label' => 'Master Jadwal Kegiatan', 'icon' => 'bi bi-calendar3-range', 'url' => '/master-jadwal'],
            ]],
            ['id' => 'program', 'title' => 'Operasional', 'items' => [
                ['key' => 'program', 'label' => 'Program Kegiatan', 'icon' => 'bi bi-diagram-3-fill', 'url' => '/karir/program-kegiatan'],
                ['key' => 'pembukaan', 'label' => 'Pembukaan Program', 'icon' => 'bi bi-megaphone', 'url' => '/karir/pembukaan'],
                ['key' => 'monitoring-mpp', 'label' => 'Monitoring MPP', 'icon' => 'bi bi-clipboard-data', 'url' => '/karir/monitoring-mpp'],
            ]],
            ['id' => 'seleksi', 'title' => 'Seleksi', 'items' => [
                ['key' => 'pelamar', 'label' => 'Worklist Pelamar', 'icon' => 'bi bi-kanban', 'url' => '/karir/pelamar'],
                ['key' => 'penjadwalan', 'label' => 'Penjadwalan Tes', 'icon' => 'bi bi-calendar-check', 'url' => '/karir/penjadwalan'],
                ['key' => 'hasil', 'label' => 'Hasil Tes', 'icon' => 'bi bi-clipboard-data', 'url' => '/karir/hasil-tes'],
                ['key' => 'pengumuman', 'label' => 'Pengumuman', 'icon' => 'bi bi-megaphone-fill', 'url' => '/karir/pengumuman'],
            ]],
            ['id' => 'data', 'title' => 'Data', 'items' => [
                ['key' => 'kandidat', 'label' => 'Kandidat', 'icon' => 'bi bi-people', 'url' => '/karir/kandidat'],
            ]],
            ['id' => 'pengaturan', 'title' => 'Pengaturan', 'items' => [
                ['key' => 'master-akun', 'label' => 'Master Akun', 'icon' => 'bi bi-person-badge', 'url' => '/master-akun'],
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
