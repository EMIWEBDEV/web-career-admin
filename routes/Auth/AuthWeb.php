<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Autentikasi panel admin. Pendaftaran & verifikasi surel kandidat ada di
// situs kandidat (project web-careers-pengguna).
Route::prefix('api/v1')->name('career.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    // Reset kata sandi via OTP: minta kode (lupa-sandi) → verifikasi OTP (verifikasi-otp)
    // → kirim OTP+password baru (ganti-sandi).
    Route::post('/lupa-sandi', [AuthController::class, 'mintaOtpReset'])->name('lupa-sandi');
    Route::post('/verifikasi-otp', [AuthController::class, 'verifikasiOtp'])->name('verifikasi-otp');
    Route::post('/ganti-sandi', [AuthController::class, 'gantiSandi'])->name('ganti-sandi');
});
