<?php

use App\Http\Controllers\Career\MasterModePengumuman\MasterModePengumumanController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MODE PENGUMUMAN
| - Halaman (Inertia / SPA): GET /master-mode-pengumuman
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-mode-pengumuman', [MasterModePengumumanController::class, 'index'])->name('career.master-mode-pengumuman');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-mode-pengumuman.')->group(function () {
    Route::get('/master-mode-pengumuman', [MasterModePengumumanController::class, 'list'])->name('list');
    Route::post('/master-mode-pengumuman', [MasterModePengumumanController::class, 'store'])->name('store');
    Route::put('/master-mode-pengumuman/{id}', [MasterModePengumumanController::class, 'update'])->name('update');
    Route::patch('/master-mode-pengumuman/{id}/toggle', [MasterModePengumumanController::class, 'toggle'])->name('toggle');
    Route::delete('/master-mode-pengumuman/{id}', [MasterModePengumumanController::class, 'destroy'])->name('destroy');
});
