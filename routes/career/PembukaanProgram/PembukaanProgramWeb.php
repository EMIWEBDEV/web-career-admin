<?php

use App\Http\Controllers\Career\PembukaanProgram\PembukaanProgramController;
use Illuminate\Support\Facades\Route;

/*
| PEMBUKAAN PROGRAM. Halaman Inertia /karir/pembukaan + JSON group api/v1.
*/
Route::get('/karir/pembukaan', [PembukaanProgramController::class, 'index'])->name('career.pembukaan');

Route::prefix('api/v1')->name('career.api.pembukaan.')->group(function () {
    Route::get('/pembukaan', [PembukaanProgramController::class, 'list'])->name('list');
    Route::post('/pembukaan', [PembukaanProgramController::class, 'store'])->name('store');
    Route::put('/pembukaan/{id}', [PembukaanProgramController::class, 'update'])->name('update');
    Route::patch('/pembukaan/{id}/toggle', [PembukaanProgramController::class, 'toggle'])->name('toggle');
    Route::delete('/pembukaan/{id}', [PembukaanProgramController::class, 'destroy'])->name('destroy');
});
