<?php

use App\Http\Controllers\Career\CareerAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| WEB ROUTES — PROJECT: WEB CAREERS ADMIN
|--------------------------------------------------------------------------
| Panel admin (zona dalam). Situs kandidat — landing, lowongan, daftar/masuk
| kandidat, portal, konfirmasi kehadiran, surat jadwal, feedback — adalah
| project terpisah (web-careers-pengguna); tautan ke sana dibuat lewat
| App\Support\Sinkron\TautanPengguna. Panel ini tidak diindeks mesin pencari
| (public/robots.txt + header X-Robots-Tag).
|
| Rute dipecah ke dua file "induk" per developer:
|   - routes/kpi/fransDevEvo.php  → autentikasi panel admin
|   - routes/kpi/ridhoDevEvo.php  → panel admin & webhook integrasi
| Semua rute berjalan di grup middleware "web" (session, CSRF, Inertia).
*/

// Root = dasbor admin; yang belum masuk dibawa career.auth ke halaman masuk.
Route::redirect('/', '/karir')->name('beranda');

// Masuk panel admin. Turnstile hanya dipakai di sini; sitekey dibagikan ke
// frontend (kosong = fitur mati).
Route::get('/login', fn () => Inertia::render('Career/Auth', ['mode' => 'login', 'turnstileSiteKey' => config('services.cloudflare.turnstile_sitekey')]))->name('login');

// Ganti / reset kata sandi (form). Email opsional dari query (alur lupa sandi).
Route::get(
    '/ganti-sandi',
    fn (Request $request) => Inertia::render('Career/GantiSandi', ['email' => (string) $request->query('email', '')]),
)->name('ganti-sandi');

// Profil akun yang sedang masuk.
Route::get('/profil', [CareerAdminController::class, 'profil'])
    ->middleware('career.auth')
    ->name('profil');

// Logout — akhiri sesi lalu kembali ke halaman masuk.
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->forget('career_auth');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login?loggedout=1');
})->name('logout');

require __DIR__ . '/kpi/fransDevEvo.php';
require __DIR__ . '/kpi/ridhoDevEvo.php';

// Pilihan jenjang & jenis institusi untuk master pendidikan.
require __DIR__ . '/career/Pendidikan/PendidikanWeb.php';

// Media hero slide — pratinjau di Master Hero (situs kandidat membaca salinannya sendiri).
Route::get('/karir/hero-media/{id}/{slot}', [\App\Http\Controllers\Career\MasterHero\MasterHeroController::class, 'media'])
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->where(['slot' => 'desktop|mobile|video_desktop|video_mobile|poster_desktop|poster_mobile'])
    ->name('career.hero.media');

// SELURUH modul master & operasional (17 modul) adalah milik ADMIN.
// Dikunci di satu tempat, bukan ditempel satu per satu di tiap berkas modul —
// supaya modul baru otomatis ikut terlindungi dan tidak ada yang kelewat.
Route::middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])->group(function () {
    require __DIR__ . '/career/fransDeveloperDevEvo.php';
});

require __DIR__ . '/career/ridhoDeveloperEvoDevEvo.php';

// [feat/feedback] Modul Feedback — master, penugasan & dasbor admin
require __DIR__ . '/career/ridhoDeveloperEvoDevEvo-v2.php';

// Diagnostik jalur jaringan (SMTP) — dijalankan lewat peramban karena Cloud Run
// tidak menyediakan shell, dan pemeriksaannya HARUS dari mesin yang gagal.
require __DIR__ . '/career/Diagnostik/DiagnostikWeb.php';

// GEMBOK — halaman bypass penjadwalan (/ui/bypass/gembok).
//
// SENGAJA DI LUAR grup career.auth di atas: halaman ini dipakai justru ketika
// panel admin sedang tidak bisa dipakai. Yang menjaganya adalah GEMBOK_SECRET
// lewat middleware GerbangGembok — tanpa kunci yang cocok, seluruh rutenya
// membalas 404. Baca catatan panjang di berkasnya sebelum mengubah ini.
require __DIR__ . '/career/Gembok/GembokWeb.php';
