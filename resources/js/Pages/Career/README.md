# WEB CAREER — Struktur Frontend (Inertia + Vue 3)

Semua fitur karir ada di folder ini. **Data operasional 100% dummy** dikirim dari
controller PHP (`App\Http\Controllers\Career\*`) via Inertia props — tanpa database.

```
Pages/Career/
├── Layouts/
│   └── CareerLayout.vue      # Layout situs publik (navbar + footer + background)
│
├── components/               # Komponen reusable situs publik
│   ├── CareerNavbar.vue
│   └── CareerFooter.vue
│
├── sections/                 # Section landing page (dipakai LandingPage.vue)
│   ├── HeroSection.vue
│   ├── AchievementSection.vue
│   ├── LowonganSection.vue
│   ├── MtSection.vue
│   ├── LokasiSection.vue
│   └── CtaSection.vue
│
├── admin/                    # ADMIN PANEL (memakai shell HCIS asli)
│   ├── AdminModal.vue        #   modal reusable (header gradient + ikon emas)
│   ├── careerAdmin.js        #   helper admin (initials, statusBadge)
│   ├── Dashboard.vue  Pelamar.vue  Penjadwalan.vue
│   ├── monitoring/           #   Monitoring Rekrutmen (Live View + Full Process)
│   └── Kegiatan.vue  Lowongan.vue  Kandidat.vue  Pengumuman.vue
│
├── portal/                   # PORTAL KANDIDAT (shell HCIS asli, sama persis dgn admin)
│   ├── LamaranSaya.vue
│   ├── LamaranDetail.vue
│   └── Profil.vue
│
├── careerData.js             # Helper situs publik (format tgl, status, navigasi, URL)
├── LandingPage.vue           # Landing (compose sections)
├── Auth.vue                  # Login / Register kandidat (gaya HCIS)
├── DetailLowongan.vue
├── DetailMt.vue
└── README.md
```

## Konvensi
- **Shell HCIS:** admin & portal memakai shell/sidebar HCIS asli (`Layouts/AppShell.vue`).
  Layout + user diinjeksi controller via prop `layout`/`auth` — menu sidebar dibangun di
  `CareerAdminController` (`shellLayout`/`adminNav`/`portalNav`), **bukan** konstanta JS.
- **Prefix CSS:** situs publik `wc-*` · admin/portal `wca-*` · shell HCIS `shell-*`.
  Semua style terpusat di **`resources/css/evo-theme.css`** (dimuat global lewat `app.js`),
  tidak ada import CSS per-halaman.
- **Ikon:** Bootstrap Icons (`bi bi-*`).
- **Data dummy backend:** `CareerLandingController` (publik) & `CareerAdminController` (admin/portal).
- Controller baru wajib dibuat via: `php artisan make:fullcontroller {name} {developer}`.
