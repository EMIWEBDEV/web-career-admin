<?php

use App\Http\Controllers\Career\MasterKampus\MasterKampusController;
use Illuminate\Support\Facades\Route;

/*
| MASTER KAMPUS
| - Halaman (Inertia / SPA): GET /master-kampus
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-kampus', [MasterKampusController::class, 'index'])->name('career.master-kampus');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-kampus.')->group(function () {
    Route::get('/master-kampus', [MasterKampusController::class, 'list'])->name('list');
    Route::post('/master-kampus', [MasterKampusController::class, 'store'])->name('store');
    Route::put('/master-kampus/{id}', [MasterKampusController::class, 'update'])->name('update');
    Route::patch('/master-kampus/{id}/toggle', [MasterKampusController::class, 'toggle'])->name('toggle');
    Route::delete('/master-kampus/{id}', [MasterKampusController::class, 'destroy'])->name('destroy');
});
