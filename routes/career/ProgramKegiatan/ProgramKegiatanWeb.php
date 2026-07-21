<?php

use App\Http\Controllers\Career\ProgramKegiatan\ProgramKegiatanController;
use Illuminate\Support\Facades\Route;

/*
| PROGRAM KEGIATAN (induk-detail). Halaman Inertia /karir/program-kegiatan + JSON group api/v1.
*/
Route::get('/karir/program-kegiatan', [ProgramKegiatanController::class, 'index'])->name('career.program-kegiatan');

Route::prefix('api/v1')->name('career.api.program-kegiatan.')->group(function () {
    Route::get('/program-kegiatan', [ProgramKegiatanController::class, 'list'])->name('list');
    Route::post('/program-kegiatan', [ProgramKegiatanController::class, 'store'])->name('store');
    Route::put('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'update'])->name('update');
    Route::patch('/program-kegiatan/{id}/toggle', [ProgramKegiatanController::class, 'toggle'])->name('toggle');
    Route::delete('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'destroy'])->name('destroy');
});
