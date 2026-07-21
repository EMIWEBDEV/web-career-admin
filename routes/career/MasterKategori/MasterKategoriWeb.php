<?php

use App\Http\Controllers\Career\MasterKategori\MasterKategoriController;
use Illuminate\Support\Facades\Route;

/*
| MASTER KATEGORI
| - Halaman (Inertia / SPA): GET /master-kategori
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1 — BUKAN halaman Inertia.
*/

// Halaman Inertia
Route::get('/master-kategori', [MasterKategoriController::class, 'index'])->name('career.master-kategori');

// JSON (bukan halaman Inertia) — prefix api/v1
Route::prefix('api/v1')->name('career.api.master-kategori.')->group(function () {
    Route::get('/master-kategori', [MasterKategoriController::class, 'list'])->name('list');
    Route::post('/master-kategori', [MasterKategoriController::class, 'store'])->name('store');
    Route::put('/master-kategori/{id}', [MasterKategoriController::class, 'update'])->name('update');
    Route::patch('/master-kategori/{id}/toggle', [MasterKategoriController::class, 'toggle'])->name('toggle');
    Route::delete('/master-kategori/{id}', [MasterKategoriController::class, 'destroy'])->name('destroy');
});
