<?php

use App\Http\Controllers\Career\Penjadwalan\PenjadwalanController;
use Illuminate\Support\Facades\Route;

/*
| PENJADWALAN. Halaman Inertia /karir/penjadwalan + JSON group api/v1.
| Semua opsi dari DB (tanpa hardcode); paket ujian diteruskan server dari HCLearn.
*/
Route::get('/karir/penjadwalan', [PenjadwalanController::class, 'index'])->name('career.penjadwalan');

Route::prefix('api/v1')->name('career.api.penjadwalan.')->group(function () {
    Route::get('/penjadwalan', [PenjadwalanController::class, 'list'])->name('list');
    Route::post('/penjadwalan', [PenjadwalanController::class, 'store'])->name('store');
    Route::delete('/penjadwalan/{id}', [PenjadwalanController::class, 'destroy'])->name('destroy');

    Route::get('/penjadwalan/opsi', [PenjadwalanController::class, 'opsi'])->name('opsi');
    Route::get('/penjadwalan/kandidat', [PenjadwalanController::class, 'kandidat'])->name('kandidat');
    Route::get('/penjadwalan/paket-ujian', [PenjadwalanController::class, 'paketUjian'])->name('paket');
});
