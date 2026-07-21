<?php

use App\Http\Controllers\Career\MasterAlur\MasterAlurController;
use Illuminate\Support\Facades\Route;

/*
| MASTER TAHAPAN SELEKSI / ALUR (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-alur', [MasterAlurController::class, 'index'])->name('career.master-alur');

Route::prefix('api/v1')->name('career.api.master-alur.')->group(function () {
    Route::get('/master-alur', [MasterAlurController::class, 'list'])->name('list');
    Route::post('/master-alur', [MasterAlurController::class, 'store'])->name('store');
    Route::put('/master-alur/{id}', [MasterAlurController::class, 'update'])->name('update');
    Route::patch('/master-alur/{id}/toggle', [MasterAlurController::class, 'toggle'])->name('toggle');
    Route::delete('/master-alur/{id}', [MasterAlurController::class, 'destroy'])->name('destroy');
});
