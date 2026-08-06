<?php

// Routes induk milik ridhoDeveloperEvo (folder: career)
// CATATAN: berkas ini di-require di LUAR gerbang admin di routes/web.php, jadi
// tiap modul memasang gerbangnya sendiri (self-gating) — jangan berasumsi
// terlindungi hanya karena berada di sini.

use Illuminate\Support\Facades\Route;

require base_path('routes/career/MppLowongan/MppLowonganWeb.php');

// ── Master Workplace (admin) — tipe lokasi kerja (On-site/Hybrid/Remote) untuk lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterWorkplace/MasterWorkplaceWeb.php');
});
require base_path('routes/career/MasterWorkplace/MasterWorkplaceApi.php');
