<?php

use App\Http\Controllers\Career\MasterKriteria\MasterKriteriaController;
use Illuminate\Support\Facades\Route;

/*
| MASTER KRITERIA
| - Halaman (Inertia / SPA): GET /master-kriteria
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-kriteria', [MasterKriteriaController::class, 'index'])->name('career.master-kriteria');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-kriteria.')->group(function () {
    Route::get('/master-kriteria', [MasterKriteriaController::class, 'list'])->name('list');
    Route::post('/master-kriteria', [MasterKriteriaController::class, 'store'])->name('store');
    Route::put('/master-kriteria/{id}', [MasterKriteriaController::class, 'update'])->name('update');
    Route::patch('/master-kriteria/{id}/toggle', [MasterKriteriaController::class, 'toggle'])->name('toggle');
    Route::delete('/master-kriteria/{id}', [MasterKriteriaController::class, 'destroy'])->name('destroy');
});
