<?php

use App\Http\Controllers\Career\MasterAkun\MasterAkunController;
use Illuminate\Support\Facades\Route;

/*
| MASTER AKUN (Users: pengguna & admin). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-akun', [MasterAkunController::class, 'index'])->name('career.master-akun')->middleware('career.permission:masterAkunPage,VIEW');

Route::prefix('api/v1')->name('career.api.master-akun.')->group(function () {
    Route::get('/master-akun', [MasterAkunController::class, 'list'])->name('list')->middleware('career.permission:masterAkunPage,VIEW');
    Route::post('/master-akun', [MasterAkunController::class, 'store'])->name('store')->middleware('career.permission:masterAkunPage,CREATE');
    Route::put('/master-akun/{id}', [MasterAkunController::class, 'update'])->name('update')->middleware('career.permission:masterAkunPage,EDIT');
    Route::patch('/master-akun/{id}/toggle', [MasterAkunController::class, 'toggle'])->name('toggle')->middleware('career.permission:masterAkunPage,EDIT');
    // Kirim ULANG tautan verifikasi email. Izinnya EDIT, bukan CREATE: yang
    // dilakukan adalah membetulkan akun yang sudah ada, bukan membuat yang baru.
    Route::patch('/master-akun/{id}/kirim-verifikasi', [MasterAkunController::class, 'kirimVerifikasi'])->name('kirim-verifikasi')->middleware('career.permission:masterAkunPage,EDIT');
    Route::delete('/master-akun/{id}', [MasterAkunController::class, 'destroy'])->name('destroy')->middleware('career.permission:masterAkunPage,DELETE');
});
