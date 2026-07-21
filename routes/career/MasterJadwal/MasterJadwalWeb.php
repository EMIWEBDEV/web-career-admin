<?php

use App\Http\Controllers\Career\MasterJadwal\MasterJadwalController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JADWAL KEGIATAN (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-jadwal', [MasterJadwalController::class, 'index'])->name('career.master-jadwal');

Route::prefix('api/v1')->name('career.api.master-jadwal.')->group(function () {
    Route::get('/master-jadwal', [MasterJadwalController::class, 'list'])->name('list');
    Route::post('/master-jadwal', [MasterJadwalController::class, 'store'])->name('store');
    Route::put('/master-jadwal/{id}', [MasterJadwalController::class, 'update'])->name('update');
    Route::patch('/master-jadwal/{id}/toggle', [MasterJadwalController::class, 'toggle'])->name('toggle');
    Route::delete('/master-jadwal/{id}', [MasterJadwalController::class, 'destroy'])->name('destroy');
});
