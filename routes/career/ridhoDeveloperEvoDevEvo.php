<?php

// Routes induk milik ridhoDeveloperEvo (folder: career)
// CATATAN: berkas ini di-require di LUAR gerbang admin di routes/web.php, jadi
// tiap modul memasang gerbangnya sendiri (self-gating) — jangan berasumsi
// terlindungi hanya karena berada di sini.

use Illuminate\Support\Facades\Route;

require base_path('routes/career/MppLowongan/MppLowonganWeb.php');

// ── Master Benefit (admin) — fasilitas & tunjangan yang dipasang pada lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterBenefit/MasterBenefitWeb.php');
});
require base_path('routes/career/MasterBenefit/MasterBenefitApi.php');

// ── Master Skill (admin) — keahlian yang disyaratkan lowongan MPP ──
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require base_path('routes/career/MasterSkill/MasterSkillWeb.php');
});
require base_path('routes/career/MasterSkill/MasterSkillApi.php');
