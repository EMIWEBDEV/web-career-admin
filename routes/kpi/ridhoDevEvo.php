<?php

use App\Http\Controllers\Career\CareerAdminController;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB CAREER — Admin Panel & Portal Kandidat  (induk route: developer Ridho)
|--------------------------------------------------------------------------
| KEAMANAN: seluruh grup di bawah WAJIB login (career.auth), dan panel admin
| dibatasi peran ADMIN/SUPERADMIN (career.role). Sebelumnya semua halaman ini
| terbuka tanpa sesi sama sekali.
*/

// ── Admin Panel: operasional & dashboard (prefix /karir) ──
Route::prefix('karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.admin.')
    ->group(function () {
        $c = CareerAdminController::class;
        Route::get('/', [$c, 'dashboard'])->name('dashboard');
        // Program Kegiatan & Pembukaan DIPINDAH ke struktur per-modul (routes/career/*).
        // Lowongan lama (/karir/lowongan) DIHAPUS — sumber lowongan kini SATU-satunya
        // dari Monitoring MPP (routes/career/MppLowongan/MppLowonganWeb.php).
        // Seleksi — worklist pelamar ditangani modul Lamaran (mesin syarat + ketuk palu).
        Route::get('/pelamar', [LamaranController::class, 'worklist'])->name('pelamar');
        Route::get('/hasil-tes', [$c, 'hasil_page'])->name('hasil');
        Route::get('/pengumuman', [$c, 'pengumuman_page'])->name('pengumuman');
        // Data
        Route::get('/kandidat', [$c, 'kandidat_page'])->name('kandidat');
    });

// Seluruh master DIPINDAH ke struktur per-modul (routes/career/Master*/*Web.php):
// talent, perilaku, mode, sumber, tipe, kampus, kriteria, kategori, kemitraan,
// formulir, tes, alur, jadwal, akun. (Siklus dihapus — Batch 11.)

// ── WEB CAREER — API data (route WEB, prefix api/v1, dipanggil via axios) ──
// Dropdown memuat seluruh master data, jadi ikut dikunci sebagai milik admin.
Route::prefix('api/v1/karir')
    ->middleware(['career.auth', 'career.role:ADMIN,SUPERADMIN'])
    ->name('career.api.')
    ->group(function () {
        $c = CareerAdminController::class;
        // OPTIONS dropdown (semua dari DB; icons dari bootstrap-icons) — dihit RefSelect/IconPicker
        Route::get('/options/{type}', [$c, 'options'])->name('options');

        // Worklist admin: daftar program (panel kiri) + kanban seleksi (panel kanan) & ketuk palu.
        Route::get('/lamaran/worklist/program', [LamaranController::class, 'worklistProgram'])->name('lamaran.worklist.program');
        Route::get('/lamaran/worklist/program/{id}', [LamaranController::class, 'worklistDetail'])->name('lamaran.worklist.detail');
        Route::get('/lamaran/pengisian/{id}', [LamaranController::class, 'lihatPengisian'])->name('lamaran.pengisian');
        Route::get('/lamaran/berkas/{id}', [LamaranController::class, 'worklistBerkas'])->name('lamaran.berkas');
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'berkasFile'])->name('lamaran.berkas.file');
        Route::patch('/lamaran/tahap/{id}/putus', [LamaranController::class, 'putus'])->name('lamaran.putus');
        // Escape hatch multi-tes: tandai sub-tes tidak hadir → mesin evaluasi ulang.
        Route::patch('/lamaran/sub-tes/{id}/tidak-hadir', [LamaranController::class, 'subTesTidakHadir'])->name('lamaran.subtes.tidakhadir');
        // Catat hasil sub-tes MANUAL (wawancara/FGD di tahap campuran) → mesin yang sama.
        Route::patch('/lamaran/sub-tes/{id}/catat-hasil', [LamaranController::class, 'subTesCatatHasil'])->name('lamaran.subtes.catathasil');

        // CRUD master generik (master/simple, master/rich, master/akun, master/kemitraan)
        // DIHAPUS: halaman gaya lama yang memakainya sudah tidak punya route —
        // seluruh master kini punya modulnya sendiri di routes/career/Master*/.
    });

// ── Portal Kandidat (prefix /kandidat) ──
// Wajib login, TANPA gerbang peran: milik kandidat itu sendiri; admin juga
// boleh menengok untuk melihat tampilan kandidat.
Route::prefix('kandidat')
    ->middleware('career.auth')
    ->name('career.portal.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'loker'])->name('loker');
        Route::get('/portal', [LamaranController::class, 'portalIndex'])->name('index');
        Route::get('/lamaran/{id}', [LamaranController::class, 'portalDetail'])->name('detail');
        // Pratinjau berkas milik kandidat sendiri (signed URL GCS).
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'portalBerkasFile'])->name('berkas.file');
    });

// ── WEBHOOK hasil tes dari CAT/HCLearn (server-to-server, TANPA login) ──
// Guard: header X-WC-Secret dicocokkan di controller. CAT memanggil ini saat
// tes pihak ke-3 difinalisasi → WC auto gerakkan tahap (lulus/gugur) real-time.
Route::post('/api/v1/webhook/hclearn-hasil', [LamaranController::class, 'hasilUjianCallback'])
    ->name('career.webhook.hclearn-hasil');

// ── API Lamaran kandidat (login saja, TANPA gerbang peran admin) ──
// Dipisah dari api/v1/karir yang khusus admin, supaya kandidat bisa melamar
// & mengirim formulir tanpa dianggap admin.
Route::prefix('api/v1/lamaran')
    ->middleware('career.auth')
    ->name('career.lamaran.')
    ->group(function () {
        Route::get('/loker', [LamaranController::class, 'lokerList'])->name('loker');
        Route::post('/', [LamaranController::class, 'lamar'])->name('lamar');
        Route::get('/apply-status/{processId}', [LamaranController::class, 'applyStatus'])->name('apply.status');
        Route::post('/tahap/{id}/kirim', [LamaranController::class, 'kirimFormulir'])->name('kirim');
        Route::delete('/{id}', [LamaranController::class, 'batalkan'])->name('batal');
    });

