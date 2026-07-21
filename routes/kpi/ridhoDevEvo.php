<?php

use App\Http\Controllers\Career\CareerAdminController;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREER — Admin Panel & Portal Kandidat  (induk route: developer Ridho)
|--------------------------------------------------------------------------
| KEAMANAN: seluruh grup di bawah WAJIB login (career.auth), dan panel admin
| dibatasi peran ADMIN/SUPERADMIN (career.role). Sebelumnya semua halaman ini
| terbuka tanpa sesi sama sekali.
*/

// ── Admin Panel: operasional & dashboard (prefix /karir) ──
Route::prefix('karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.admin.')
    ->group(function () {
        $c = CareerAdminController::class;
        Route::get('/', [$c, 'dashboard'])->name('dashboard');
        // Program Kegiatan & Pembukaan DIPINDAH ke struktur per-modul (routes/career/*).
        // Lowongan (dari MPP, read-only accordion)
        Route::get('/lowongan', [$c, 'lowongan_page'])->name('lowongan');
        // Seleksi — worklist pelamar ditangani modul Lamaran (mesin syarat + ketuk palu).
        Route::get('/pelamar', [LamaranController::class, 'worklist'])->name('pelamar');
        Route::get('/hasil-tes', [$c, 'hasil_page'])->name('hasil');
        Route::get('/pengumuman', [$c, 'pengumuman_page'])->name('pengumuman');
        // Data
        Route::get('/kandidat', [$c, 'kandidat_page'])->name('kandidat');
    });

// Seluruh master DIPINDAH ke struktur per-modul (routes/career/Master*/*Web.php):
// talent, perilaku, mode, sumber, tipe, kampus, kriteria, kategori, kemitraan,
// formulir, tes, alur, jadwal, akun. (Siklus dihapus — Batch 11.)

// ── WEB CAREER — API data (route WEB, prefix api/v1, dipanggil via axios) ──
// Dropdown memuat seluruh master data, jadi ikut dikunci sebagai milik admin.
Route::prefix('api/v1/karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.api.')
    ->group(function () {
        $c = CareerAdminController::class;
        // OPTIONS dropdown (semua dari DB; icons dari bootstrap-icons) — dihit RefSelect/IconPicker
        Route::get('/options/{type}', [$c, 'options'])->name('options');

        // Worklist admin: baca daftar & ketuk palu.
        Route::get('/lamaran/worklist', [LamaranController::class, 'worklistList'])->name('lamaran.worklist');
        Route::get('/lamaran/pengisian/{id}', [LamaranController::class, 'lihatPengisian'])->name('lamaran.pengisian');
        Route::patch('/lamaran/tahap/{id}/putus', [LamaranController::class, 'putus'])->name('lamaran.putus');

        // CRUD master generik (master/simple, master/rich, master/akun, master/kemitraan)
        // DIHAPUS: halaman gaya lama yang memakainya sudah tidak punya route —
        // seluruh master kini punya modulnya sendiri di routes/career/Master*/.
    });

// ── Portal Kandidat (prefix /kandidat) ──
// Wajib login, TANPA gerbang peran: milik kandidat itu sendiri; admin juga
// boleh menengok untuk melihat tampilan kandidat.
Route::prefix('kandidat')
    ->middleware('career.auth')
    ->name('career.portal.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'loker'])->name('loker');
        Route::get('/portal', [LamaranController::class, 'portalIndex'])->name('index');
        Route::get('/lamaran/{id}', [LamaranController::class, 'portalDetail'])->name('detail');
    });

// ── API Lamaran kandidat (login saja, TANPA gerbang peran admin) ──
// Dipisah dari api/v1/karir yang khusus admin, supaya kandidat bisa melamar
// & mengirim formulir tanpa dianggap admin.
Route::prefix('api/v1/lamaran')
    ->middleware('career.auth')
    ->name('career.lamaran.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'lokerList'])->name('loker');
        Route::post('/', [LamaranController::class, 'lamar'])->name('lamar');
        Route::post('/tahap/{id}/kirim', [LamaranController::class, 'kirimFormulir'])->name('kirim');
    });
