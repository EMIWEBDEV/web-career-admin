<?php

use App\Http\Controllers\Career\MasterAkun\MasterAkunController;
use Illuminate\Support\Facades\Route;

/*
| MASTER AKUN (Users: pengguna & admin). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-akun', [MasterAkunController::class, 'index'])->name('career.master-akun');

Route::prefix('api/v1')->name('career.api.master-akun.')->group(function () {
    Route::get('/master-akun', [MasterAkunController::class, 'list'])->name('list');
    Route::post('/master-akun', [MasterAkunController::class, 'store'])->name('store');
    Route::put('/master-akun/{id}', [MasterAkunController::class, 'update'])->name('update');
    Route::patch('/master-akun/{id}/toggle', [MasterAkunController::class, 'toggle'])->name('toggle');
    Route::delete('/master-akun/{id}', [MasterAkunController::class, 'destroy'])->name('destroy');
});
