<?php

use App\Http\Controllers\Career\MasterTes\MasterTesController;
use Illuminate\Support\Facades\Route;

/*
| MASTER JENIS TES (induk-detail). Halaman Inertia + JSON group api/v1.
*/
Route::get('/master-tes', [MasterTesController::class, 'index'])->name('career.master-tes');

Route::prefix('api/v1')->name('career.api.master-tes.')->group(function () {
    Route::get('/master-tes', [MasterTesController::class, 'list'])->name('list');
    Route::post('/master-tes', [MasterTesController::class, 'store'])->name('store');
    Route::put('/master-tes/{id}', [MasterTesController::class, 'update'])->name('update');
    Route::patch('/master-tes/{id}/toggle', [MasterTesController::class, 'toggle'])->name('toggle');
    Route::delete('/master-tes/{id}', [MasterTesController::class, 'destroy'])->name('destroy');
});
