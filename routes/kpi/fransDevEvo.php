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

// Perkenalan tim / fungsi perusahaan (konten statis di komponen Vue).
Route::get('/karir/tim', [CareerLandingController::class, 'semuaTim'])->name('career.tim.semua');
Route::get('/karir/tim/{slug}', [CareerLandingController::class, 'showTim'])->name('career.tim.detail');

// MELAMAR WAJIB LOGIN. Formulir ini menulis lamaran atas nama satu akun —
// tanpa sesi, isiannya tidak punya pemilik dan prefill/kelayakan/status
// "sudah melamar" semuanya kosong, jadi tamu melihat formulir yang terlihat
// jalan tapi tidak pernah bisa terkirim. career.auth memulangkan tamu ke
// halaman masuk SAMBIL membawa `?redirect=` ke sini, sehingga sesudah masuk
// mereka mendarat kembali di formulir lowongan yang tadi diklik.
Route::get('/karir/apply/{id}', [CareerLandingController::class, 'apply'])
    ->middleware('career.auth')
    ->name('career.apply');

Route::get('/karir/login', [CareerLandingController::class, 'login'])->name('career.login');
Route::get('/karir/register', [CareerLandingController::class, 'register'])->name('career.register');

require base_path('routes/Auth/AuthWeb.php');
require base_path('routes/Auth/AuthApi.php');