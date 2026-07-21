<?php

use App\Http\Controllers\Career\MasterFormulir\MasterFormulirController;
use Illuminate\Support\Facades\Route;

/*
| MASTER FORMULIR (katalog). Halaman Inertia /master-formulir + JSON group api/v1.
|
| Skema pertanyaan TIDAK disimpan di database — ditulis di komponen Vue
| (Formulir1.vue, Formulir2.vue). Tabel hanya mencatat komponen mana yang
| dipakai, jadi tidak ada endpoint penyusun skema di sini.
*/
Route::get('/master-formulir', [MasterFormulirController::class, 'index'])->name('career.master-formulir');

Route::prefix('api/v1')->name('career.api.master-formulir.')->group(function () {
    Route::get('/master-formulir', [MasterFormulirController::class, 'list'])->name('list');
    Route::post('/master-formulir', [MasterFormulirController::class, 'store'])->name('store');
    Route::put('/master-formulir/{id}', [MasterFormulirController::class, 'update'])->name('update');
    Route::patch('/master-formulir/{id}/toggle', [MasterFormulirController::class, 'toggle'])->name('toggle');
    Route::delete('/master-formulir/{id}', [MasterFormulirController::class, 'destroy'])->name('destroy');
});
