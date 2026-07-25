<?php

use App\Http\Controllers\Career\MasterModeKeputusan\MasterModeKeputusanController;
use Illuminate\Support\Facades\Route;

/*
| MASTER MODE KEPUTUSAN
| - Halaman (Inertia / SPA): GET /master-mode-keputusan
| - Data & CRUD (JSON, ResponseHelper) di GROUP prefix api/v1.
*/

Route::get('/master-mode-keputusan', [MasterModeKeputusanController::class, 'index'])->name('career.master-mode-keputusan');

Route::prefix('api/v1')->name('career.api.master-mode-keputusan.')->group(function () {
    Route::get('/master-mode-keputusan', [MasterModeKeputusanController::class, 'list'])->name('list');
    Route::post('/master-mode-keputusan', [MasterModeKeputusanController::class, 'store'])->name('store');
    Route::put('/master-mode-keputusan/{id}', [MasterModeKeputusanController::class, 'update'])->name('update');
    Route::patch('/master-mode-keputusan/{id}/toggle', [MasterModeKeputusanController::class, 'toggle'])->name('toggle');
    Route::delete('/master-mode-keputusan/{id}', [MasterModeKeputusanController::class, 'destroy'])->name('destroy');
});
