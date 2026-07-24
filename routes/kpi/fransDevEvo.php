<?php

use App\Http\Controllers\Career\CareerLandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREER — Publik & Auth Kandidat  (induk route: developer Frans)
|--------------------------------------------------------------------------
| Dummy, tanpa middleware auth/DB. Situs karir + halaman detail + auth kandidat.
*/

Route::get('/karir/landing-page', [CareerLandingController::class, 'index'])->name('career.landing');

// Kumpulan SELURUH lowongan (rekrutmen + MT) — landing hanya cuplikan 6 teratas.
Route::get('/karir/lowongan', [CareerLandingController::class, 'semuaLowongan'])->name('career.lowongan.semua');

Route::get('/karir/landing-page/lowongan/{id}', [CareerLandingController::class, 'showLowongan'])->name(
    'career.lowongan.detail',
);
Route::get('/karir/landing-page/mt/{id}', [CareerLandingController::class, 'showMt'])->name('career.mt.detail');

Route::get('/karir/apply/{id}', [CareerLandingController::class, 'apply'])->name('career.apply');

Route::get('/karir/login', [CareerLandingController::class, 'login'])->name('career.login');
Route::get('/karir/register', [CareerLandingController::class, 'register'])->name('career.register');

require base_path('routes/Auth/AuthWeb.php');
require base_path('routes/Auth/AuthApi.php');