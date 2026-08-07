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

        $semua = NavigasiShell::untukPenggunaSaatIni();

        // ── GRUP BERANDA = KUMPULAN DASHBOARD ────────────────────────────────
        //
        // Dikenali dari ISINYA, bukan dari namanya: grup mana pun yang memuat
        // item ber-URL beranda adalah grup dashboard. Judulnya boleh apa saja
        // ("Beranda", "Dashboard", "Ringkasan") dan boleh berbeda per akun,
        // sebab penyusun menu (/hak-akses/susun) mengizinkan tiap akun menimpa
        // Nama_Header sendiri — mencocokkan nama di sini akan gagal diam-diam
        // begitu satu admin menamainya lain.
        //
        // Anggota grup inilah yang jadi isi collapse "Dashboard". Admin yang
        // hanya punya satu dashboard tidak punya anggota lain, jadi tombol
        // berandanya tetap tautan tunggal seperti sebelumnya — tidak ada
        // collapse berisi satu baris.
        $idGrupBeranda = null;
        $itemBeranda = null;
        foreach ($semua as $g) {
            foreach ($g['items'] as $it) {
                if (($it['url'] ?? '') === $beranda) {
                    $idGrupBeranda = $g['id'];
                    $itemBeranda = $it;
                    break 2;
                }
            }
        }

        $petakan = fn ($it) => [
            'id' => $it['key'],
            'jenisPage' => strtolower($kode) . '-' . $it['key'],
            'title' => $it['label'],
            'subtitle' => '',
            'url' => $it['url'],
            'icon' => $it['icon'],
            'target' => 'self',
            'isActive' => $it['url'] === $activeUrl,
        ];

        // Dashboard SELAIN beranda — jadi anak collapse berandanya.
        $dashboard = collect($semua)
            ->firstWhere('id', $idGrupBeranda)['items'] ?? [];
        $dashboard = collect($dashboard)
            ->reject(fn ($it) => ($it['url'] ?? '') === $beranda)
            ->map($petakan)
            ->values()
            ->all();

        // Item yang URL-nya = beranda DIBUANG dari grup: beranda sudah punya
        // tombolnya sendiri di navigation.home. Ini bukan kosmetik semata —
        // halaman dashboard perlu baris N_WEB_CAREERS_Menu ('dashboardPage')
        // supaya bisa diberi batasan kategori di /hak-akses, dan baris itu
        // otomatis ikut jadi item sidebar. Tanpa penyaring ini, "Dashboard"
        // muncul dua kali: sebagai tombol beranda dan sebagai item grup.
        //
        // Seluruh GRUP beranda pun dikeluarkan — isinya sudah pindah ke
        // navigation.home di atas, dan membiarkannya membuat tiap dashboard
        // tampil dua kali: sekali di collapse Dashboard, sekali lagi di grup
        // asalnya.
        $groups = collect($semua)
            ->reject(fn ($g) => $idGrupBeranda !== null && $g['id'] === $idGrupBeranda)
            ->map(fn ($g) => [
                'id' => $g['id'],
                'title' => $g['title'],
                'items' => collect($g['items'])
                    ->reject(fn ($it) => ($it['url'] ?? '') === $beranda)
                    ->map($petakan)
                    ->values()
                    ->all(),
            ])
            ->reject(fn ($g) => ! $g['items'])   // grup yang jadi kosong ikut hilang
            ->values()
            ->all();

        // Dashboard ikut dicari: sejak isinya pindah ke navigation.home, item
        // aktif bisa berada di luar $groups — dan tanpa ini judul halaman &
        // breadcrumb untuk Dashboard Kandidat/Feedback jatuh ke null.
        $activeItem = collect($groups)->flatMap(fn ($g) => $g['items'])->firstWhere('isActive', true)
            ?: collect($dashboard)->firstWhere('isActive', true);
        $activeGroup = collect($groups)->first(fn ($g) => collect($g['items'])->firstWhere('isActive', true));

        // Beranda sedang dibuka, ATAU salah satu dashboard di dalamnya.
        // Dipakai sidebar untuk menyalakan tombol Dashboard dan membuka
        // collapse-nya sendiri saat halaman dimuat.
        $berandaAktif = $beranda === $activeUrl
            || (bool) collect($dashboard)->firstWhere('isActive', true);

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
                    // Namanya diambil dari MASTER MENU bila barisnya ada —
                    // admin yang menamainya "Dashboard Utama" di /master-menu
                    // berhak melihat nama itu di sidebarnya, bukan kata lain
                    // yang ditulis di sini.
                    'title' => $itemBeranda['label'] ?? 'Dashboard',
                    'subtitle' => 'Halaman Utama',
                    'url' => $beranda,
                    'icon' => $itemBeranda['icon'] ?? 'bi bi-house-door-fill',
                    'isActive' => $beranda === $activeUrl,
                    // Dashboard lain yang boleh dilihat akun ini. Kosong =
                    // sidebar menggambar tautan tunggal, persis seperti dulu.
                    'items' => $dashboard,
                    'hasActive' => $berandaAktif,
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
