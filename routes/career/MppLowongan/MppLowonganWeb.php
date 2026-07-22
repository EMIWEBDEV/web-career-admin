<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Career\MppLowongan\MppLowonganController;

/*
 * WEB CAREER — MONITORING MPP (read-only).
 * Halaman Inertia + endpoint JSON (api/v1) untuk card grid / table / off-canvas.
 *
 * Gerbang admin dipasang DI SINI (self-gating) — bukan bergantung pada posisi
 * require di web.php — supaya modul ini selalu admin-only meski file induk
 * di-refactor/di-format ulang.
 */
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    // Halaman (Inertia).
    Route::get('/karir/monitoring-mpp', [MppLowonganController::class, 'index'])->name('career.monitoring-mpp');

    // Data JSON (dihit axios saat mount / buka off-canvas).
    Route::prefix('api/v1')
        ->name('career.api.monitoring-mpp.')
        ->group(function () {
            Route::get('/monitoring-mpp', [MppLowonganController::class, 'list'])->name('list');
            // Opsi filter + statistik — DIDEFINISIKAN SEBELUM route {no} agar tidak tertangkap sebagai detail.
            Route::get('/monitoring-mpp/options', [MppLowonganController::class, 'options'])->name('options');
            Route::get('/monitoring-mpp/{no}', [MppLowonganController::class, 'detail'])->name('detail');
        });
});
