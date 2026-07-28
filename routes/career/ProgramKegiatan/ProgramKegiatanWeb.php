<?php

use App\Http\Controllers\Career\ProgramKegiatan\ProgramKegiatanController;
use Illuminate\Support\Facades\Route;

/*
| PROGRAM KEGIATAN (induk-detail). Halaman Inertia /karir/program-kegiatan + JSON group api/v1.
*/
Route::get('/karir/program-kegiatan', [ProgramKegiatanController::class, 'index'])->name('career.program-kegiatan')->middleware('career.permission:programPage,VIEW');

Route::prefix('api/v1')->name('career.api.program-kegiatan.')->group(function () {
    Route::get('/program-kegiatan', [ProgramKegiatanController::class, 'list'])->name('list')->middleware('career.permission:programPage,VIEW');
    // Guard: cek kelengkapan Info Divisi untuk MPP posisi terpilih (wajib sebelum lanjut).
    Route::post('/program-kegiatan/cek-info-divisi', [ProgramKegiatanController::class, 'cekInfoDivisi'])->name('cek-info-divisi')->middleware('career.permission:programPage,VIEW');
    Route::post('/program-kegiatan', [ProgramKegiatanController::class, 'store'])->name('store')->middleware('career.permission:programPage,CREATE');
    Route::put('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'update'])->name('update')->middleware('career.permission:programPage,EDIT');
    Route::patch('/program-kegiatan/{id}/toggle', [ProgramKegiatanController::class, 'toggle'])->name('toggle')->middleware('career.permission:programPage,EDIT');
    Route::delete('/program-kegiatan/{id}', [ProgramKegiatanController::class, 'destroy'])->name('destroy')->middleware('career.permission:programPage,DELETE');
});
