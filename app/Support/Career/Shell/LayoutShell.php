<?php

namespace App\Support\Career\Shell;

/**
 * WEB CAREERS — PERAKIT PAYLOAD LAYOUT (brand + navigation + breadcrumb).
 *
 * Bentuk payload-nya mengikuti kontrak shell bersama (sama seperti
 * InertiaShellService di sistem lain), jadi berkas ini praktis tidak berubah:
 * menu datang dari NavigasiShell, merek dari MerekShell.
 */
class LayoutShell
{
    public static function bangun(string $activeUrl, string $judul): array
    {
        $kode = (string) config('career_shell.kode', 'CAREER');
        $label = (string) config('career_shell.label', 'Web Career');
        $beranda = IdentitasShell::beranda();

        $groups = collect(NavigasiShell::untukPenggunaSaatIni())
            ->map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => collect($g['items'])
                    ->map(fn ($it) => [
                        'id' => $it['key'],
                        'jenisPage' => strtolower($kode) . '-' . $it['key'],
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

        $module = [
            'id' => $kode,
            'code' => $kode,
            'name' => $label,
            'label' => $label,
            'subtitle' => (string) config('career_shell.subtitle', ''),
            'icon' => (string) config('career_shell.ikon', 'bi bi-briefcase-fill'),
            'sortOrder' => 1,
            'landingJenisPage' => '',
            'landingUrl' => $beranda,
            'landingTarget' => 'self',
            'isActive' => true,
            'activeGroupId' => $activeGroup['id'] ?? '',
            'groups' => $groups,
        ];

        return [
            'brand' => MerekShell::payload(),
            'navigation' => [
                'sectionLabel' => $label,
                'home' => [
                    'title' => 'Dashboard',
                    'subtitle' => 'Halaman Utama',
                    'url' => $beranda,
                    'icon' => 'bi bi-house-door-fill',
                    'isActive' => $beranda === $activeUrl,
                ],
                'modules' => [$module],
            ],
            'shell' => [
                'currentUrl' => $activeUrl,
                'activeModule' => $kode,
                'activeModuleLabel' => $label,
                'activeModuleUrl' => $beranda,
                'activeSubMenu' => $activeItem['jenisPage'] ?? null,
                'activeSubMenuLabel' => $activeItem['title'] ?? null,
                'currentPageTitle' => $judul,
                'breadcrumbs' => [
                    ['label' => 'HOME', 'url' => $beranda, 'active' => false],
                    ['label' => $kode, 'url' => $beranda, 'active' => false],
                    ['label' => $judul, 'url' => $activeUrl, 'active' => true],
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
