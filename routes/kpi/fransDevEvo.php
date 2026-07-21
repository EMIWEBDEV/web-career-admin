<?php

use App\Http\Controllers\Career\CareerLandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREER — Publik & Auth Kandidat  (induk route: developer Frans)
|--------------------------------------------------------------------------
| Dummy, tanpa middleware auth/DB. Situs karir + halaman detail + auth kandidat.
*/

Route::get('/test/karir/landing-page', [CareerLandingController::class, 'index'])->name('career.landing');

Route::get('/test/karir/landing-page/lowongan/{id}', [CareerLandingController::class, 'showLowongan'])->name(
    'career.lowongan.detail',
);
Route::get('/test/karir/landing-page/mt/{id}', [CareerLandingController::class, 'showMt'])->name('career.mt.detail');

Route::get('/test/karir/apply/{id}', [CareerLandingController::class, 'apply'])->name('career.apply');

Route::get('/test/karir/login', [CareerLandingController::class, 'login'])->name('career.login');
Route::get('/test/karir/register', [CareerLandingController::class, 'register'])->name('career.register');

require base_path('routes/Auth/AuthWeb.php');
require base_path('routes/Auth/AuthApi.php');