<?php

use App\Http\Controllers\Career\Penjadwalan\PenjadwalanController;
use Illuminate\Support\Facades\Route;

/*
| PENJADWALAN. Halaman Inertia /karir/penjadwalan + JSON group api/v1.
| Semua opsi dari DB (tanpa hardcode); paket ujian diteruskan server dari HCLearn.
*/
Route::get('/karir/penjadwalan', [PenjadwalanController::class, 'index'])->name('career.penjadwalan')->middleware('career.permission:penjadwalanPage,VIEW');

Route::prefix('api/v1')->name('career.api.penjadwalan.')->group(function () {
    Route::get('/penjadwalan', [PenjadwalanController::class, 'list'])->name('list')->middleware('career.permission:penjadwalanPage,VIEW');
    // Peserta satu penjadwalan — diambil saat barisnya dibuka (akordion).
    Route::get('/penjadwalan/{id}/peserta', [PenjadwalanController::class, 'peserta'])->name('peserta')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::post('/penjadwalan', [PenjadwalanController::class, 'store'])->name('store')->middleware('career.permission:penjadwalanPage,CREATE');
    // Geser jadwal SATU kandidat (yang dipakai layar Penjadwalan).
    Route::put('/penjadwalan/peserta/{id}', [PenjadwalanController::class, 'updatePeserta'])->name('peserta.update')->middleware('career.permission:penjadwalanPage,EDIT');
    Route::put('/penjadwalan/{id}', [PenjadwalanController::class, 'update'])->name('update')->middleware('career.permission:penjadwalanPage,EDIT');
    Route::delete('/penjadwalan/{id}', [PenjadwalanController::class, 'destroy'])->name('destroy')->middleware('career.permission:penjadwalanPage,DELETE');

    Route::get('/penjadwalan/opsi', [PenjadwalanController::class, 'opsi'])->name('opsi')->middleware('career.permission:penjadwalanPage,VIEW');
    // Tahap/aktivitas tes milik ALUR program terpilih — pengganti daftar jenis tes global.
    Route::get('/penjadwalan/tes', [PenjadwalanController::class, 'tesAlur'])->name('tes')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::get('/penjadwalan/kandidat', [PenjadwalanController::class, 'kandidat'])->name('kandidat')->middleware('career.permission:penjadwalanPage,VIEW');
    Route::get('/penjadwalan/paket-ujian', [PenjadwalanController::class, 'paketUjian'])->name('paket')->middleware('career.permission:penjadwalanPage,VIEW');
});
