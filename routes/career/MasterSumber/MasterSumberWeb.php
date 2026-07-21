<?php

use App\Http\Controllers\Career\MasterSumber\MasterSumberController;
use Illuminate\Support\Facades\Route;

/*
| MASTER SUMBER KANDIDAT
| - Halaman (Inertia / SPA): GET /master-sumber
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-sumber', [MasterSumberController::class, 'index'])->name('career.master-sumber');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-sumber.')->group(function () {
    Route::get('/master-sumber', [MasterSumberController::class, 'list'])->name('list');
    Route::post('/master-sumber', [MasterSumberController::class, 'store'])->name('store');
    Route::put('/master-sumber/{id}', [MasterSumberController::class, 'update'])->name('update');
    Route::patch('/master-sumber/{id}/toggle', [MasterSumberController::class, 'toggle'])->name('toggle');
    Route::delete('/master-sumber/{id}', [MasterSumberController::class, 'destroy'])->name('destroy');
});
