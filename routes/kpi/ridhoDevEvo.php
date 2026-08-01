<?php

use App\Http\Controllers\Career\CareerAdminController;
use App\Http\Controllers\Career\Dashboard\DashboardController;
use App\Http\Controllers\Career\Lamaran\FormulirDrafController;
use App\Http\Controllers\Career\Lamaran\LamaranController;
use App\Http\Controllers\Career\Monitoring\MonitoringController;
use App\Http\Controllers\Career\TalentPool\TalentPoolController;
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
        // Dashboard bertab per kategori (Rekrutmen/Magang/MT). SENGAJA tanpa
        // career.permission: ini halaman beranda admin — yang dibatasi adalah
        // kategori yang tampil, ditegakkan di dalam controller lewat
        // AksesService::kategoriDiizinkan('dashboardPage'). Memasang gerbang
        // halaman di sini akan mengunci admin baru dari berandanya sendiri.
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // Program Kegiatan & Pembukaan DIPINDAH ke struktur per-modul (routes/career/*).
        // Lowongan lama (/karir/lowongan) DIHAPUS — sumber lowongan kini SATU-satunya
        // dari Monitoring MPP (routes/career/MppLowongan/MppLowonganWeb.php).
        // Seleksi — worklist pelamar ditangani modul Lamaran (mesin syarat + ketuk palu).
        Route::get('/pelamar', [LamaranController::class, 'worklist'])->name('pelamar')->middleware('career.permission:pelamarPage,VIEW');
        // Monitoring Rekrutmen (dulu "Hasil Tes") — pengawasan read-only untuk
        // atasan/super admin: Live View (funnel semua program) + Full Process.
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring')->middleware('career.permission:hasilTesPage,VIEW');
        Route::redirect('/hasil-tes', '/karir/monitoring', 301); // URL lama di bookmark/menu tetap hidup
        Route::get('/pengumuman', [$c, 'pengumuman_page'])->name('pengumuman')->middleware('career.permission:pengumumanPage,VIEW');
        // Talent Pool — kolam kandidat bagus yang belum terpakai (diisi dari Worklist).
        Route::get('/talent-pool', [TalentPoolController::class, 'index'])->name('talent-pool')->middleware('career.permission:talentPoolPage,VIEW');
        // Data
        Route::get('/kandidat', [$c, 'kandidat_page'])->name('kandidat')->middleware('career.permission:kandidatPage,VIEW');
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

        // Dashboard — tiga endpoint terpisah supaya halaman terbit cepat dan
        // tiap seksi punya keadaan muat/galat sendiri. Kategori divalidasi di
        // controller terhadap Role_Konten_Access, bukan lewat middleware.
        Route::get('/dashboard/ringkas', [DashboardController::class, 'ringkas'])->name('dashboard.ringkas');
        Route::get('/dashboard/analitik', [DashboardController::class, 'analitik'])->name('dashboard.analitik');
        Route::get('/dashboard/kalender', [DashboardController::class, 'kalender'])->name('dashboard.kalender');
        Route::get('/dashboard/kalender/tes/{id}/peserta', [DashboardController::class, 'pesertaKalender'])->name('dashboard.kalender.peserta');
        Route::get('/dashboard/khas', [DashboardController::class, 'khas'])->name('dashboard.khas');

        // Worklist admin: daftar program (panel kiri) + kanban seleksi (panel kanan) & ketuk palu.
        Route::get('/lamaran/worklist/program', [LamaranController::class, 'worklistProgram'])->name('lamaran.worklist.program')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/worklist/program/{id}', [LamaranController::class, 'worklistDetail'])->name('lamaran.worklist.detail')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/pengisian/{id}', [LamaranController::class, 'lihatPengisian'])->name('lamaran.pengisian')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/berkas/{id}', [LamaranController::class, 'worklistBerkas'])->name('lamaran.berkas')->middleware('career.permission:pelamarPage,VIEW');
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'berkasFile'])->name('lamaran.berkas.file')->middleware('career.permission:pelamarPage,VIEW');
        Route::patch('/lamaran/tahap/{id}/putus', [LamaranController::class, 'putus'])->name('lamaran.putus')->middleware('career.permission:pelamarPage,APPROVE');
        // Escape hatch multi-tes: tandai sub-tes tidak hadir → mesin evaluasi ulang.
        Route::patch('/lamaran/sub-tes/{id}/tidak-hadir', [LamaranController::class, 'subTesTidakHadir'])->name('lamaran.subtes.tidakhadir')->middleware('career.permission:pelamarPage,EDIT');
        // Catat hasil sub-tes MANUAL (wawancara/FGD di tahap campuran) → mesin yang sama.
        Route::patch('/lamaran/sub-tes/{id}/catat-hasil', [LamaranController::class, 'subTesCatatHasil'])->name('lamaran.subtes.catathasil')->middleware('career.permission:pelamarPage,EDIT');
        // Jadwal wawancara / tes tatap muka + undangan email ke kandidat.
        Route::patch('/lamaran/sub-tes/{id}/jadwal', [LamaranController::class, 'subTesJadwal'])->name('lamaran.subtes.jadwal')->middleware('career.permission:pelamarPage,EDIT');
        // Kehadiran MCU/wawancara — gerbang sebelum hasil boleh dicatat.
        Route::patch('/lamaran/sub-tes/{id}/kehadiran', [LamaranController::class, 'subTesKehadiran'])->name('lamaran.subtes.kehadiran')->middleware('career.permission:pelamarPage,EDIT');
        // Tarik hasil ujian online dari HCLearn bila webhook-nya tak sampai.
        Route::post('/lamaran/sub-tes/{id}/sinkron', [LamaranController::class, 'subTesSinkron'])->name('lamaran.subtes.sinkron')->middleware('career.permission:pelamarPage,EDIT');

        // Berkas hasil tahap (MCU/Interview) — unggah PDF/JPG, daftar, preview, hapus.
        Route::get('/lamaran/tahap/{id}/berkas', [LamaranController::class, 'berkasTahap'])->name('lamaran.tahap.berkas')->middleware('career.permission:pelamarPage,VIEW');
        Route::post('/lamaran/tahap/{id}/berkas', [LamaranController::class, 'unggahBerkasTahap'])->name('lamaran.tahap.berkas.unggah')->middleware('career.permission:pelamarPage,EDIT');
        Route::get('/lamaran/tahap/berkas/file/{id}', [LamaranController::class, 'berkasTahapFile'])->name('lamaran.tahap.berkas.file')->middleware('career.permission:pelamarPage,VIEW');
        Route::delete('/lamaran/tahap/berkas/{id}', [LamaranController::class, 'hapusBerkasTahap'])->name('lamaran.tahap.berkas.hapus')->middleware('career.permission:pelamarPage,EDIT');

        // Monitoring Rekrutmen — read-only; permission ikut key lama hasilTesPage.
        Route::get('/monitoring/live', [MonitoringController::class, 'live'])->name('monitoring.live')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/program/{id}/papan', [MonitoringController::class, 'papan'])->name('monitoring.papan')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/program/{id}/tahap/{urutan}/detail', [MonitoringController::class, 'stageDetail'])->name('monitoring.tahap.detail')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{id}', [MonitoringController::class, 'detail'])->name('monitoring.pelamar.detail')->middleware('career.permission:hasilTesPage,VIEW');
        // Detail satu tahap milik satu pelamar + berkasnya (offcanvas tumpukan ke-3).
        Route::get('/monitoring/pelamar/{id}/tahap/{urutan}', [MonitoringController::class, 'tahapPelamar'])->name('monitoring.pelamar.tahap')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{lamaran}/berkas-tahap/{berkas}', [MonitoringController::class, 'berkasTahapFile'])->name('monitoring.berkas.tahap')->middleware('career.permission:hasilTesPage,VIEW');
        Route::get('/monitoring/pelamar/{lamaran}/berkas-formulir/{berkas}', [MonitoringController::class, 'berkasFormulirFile'])->name('monitoring.berkas.formulir')->middleware('career.permission:hasilTesPage,VIEW');

        // Talent Pool — data kartu + kelola status/tag/catatan.
        Route::get('/talent-pool', [TalentPoolController::class, 'list'])->name('talent-pool.list')->middleware('career.permission:talentPoolPage,VIEW');
        Route::get('/talent-pool/export', [TalentPoolController::class, 'export'])->name('talent-pool.export')->middleware('career.permission:talentPoolPage,VIEW');
        Route::post('/talent-pool/bulk', [TalentPoolController::class, 'bulk'])->name('talent-pool.bulk')->middleware('career.permission:talentPoolPage,EDIT');
        Route::patch('/talent-pool/{id}', [TalentPoolController::class, 'ubah'])->name('talent-pool.ubah')->middleware('career.permission:talentPoolPage,EDIT');
        Route::patch('/talent-pool/{id}/perpanjang', [TalentPoolController::class, 'perpanjang'])->name('talent-pool.perpanjang')->middleware('career.permission:talentPoolPage,EDIT');
        // Tarik ke lowongan (lintas MPP + pilih titik masuk).
        Route::get('/talent-pool/{id}/lowongan', [TalentPoolController::class, 'lowongan'])->name('talent-pool.lowongan')->middleware('career.permission:talentPoolPage,VIEW');
        Route::get('/talent-pool/lowongan/{posisiId}/tahap', [TalentPoolController::class, 'tahapLowongan'])->name('talent-pool.lowongan.tahap')->middleware('career.permission:talentPoolPage,VIEW');
        Route::post('/talent-pool/{id}/tarik', [TalentPoolController::class, 'tarik'])->name('talent-pool.tarik')->middleware('career.permission:talentPoolPage,APPROVE');
        Route::delete('/talent-pool/{id}', [TalentPoolController::class, 'destroy'])->name('talent-pool.destroy')->middleware('career.permission:talentPoolPage,DELETE');

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
        Route::get('/loker', [LamaranController::class, 'loker'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::get('/portal', [LamaranController::class, 'portalIndex'])->name('index')->middleware('career.permission:portalPage,VIEW');
        Route::get('/lamaran/{id}', [LamaranController::class, 'portalDetail'])->name('detail')->middleware('career.permission:portalPage,VIEW');
        // Pratinjau berkas milik kandidat sendiri (signed URL GCS).
        Route::get('/lamaran/berkas/file/{id}', [LamaranController::class, 'portalBerkasFile'])->name('berkas.file');
        // Berkas HASIL TAHAP (MCU, hasil wawancara) — boleh dilihat kandidat.
        // Nilai tetap ditahan di payload; yang dibuka hanya dokumennya.
        Route::get('/lamaran/tahap/berkas/{id}', [LamaranController::class, 'portalBerkasTahap'])->name('tahap.berkas');

        // ── SIMPAN SEMENTARA (DRAF) FORMULIR TAHAP ──
        // Semua endpoint memeriksa kepemilikan tahap lewat Lamaran.Id_Users, jadi
        // id tahap milik orang lain tidak bisa dipakai membaca atau menimpa draf.
        // Berkas draf HANYA dibuka lewat endpoint berkas di bawah (signed URL
        // 15 menit) — front-end tidak pernah menyentuh API storage langsung.
        Route::get('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'ambil'])->name('draf.ambil');
        Route::post('/lamaran/tahap/{id}/draf', [FormulirDrafController::class, 'simpan'])->name('draf.simpan');
        Route::post('/lamaran/tahap/{id}/draf/berkas', [FormulirDrafController::class, 'unggahBerkas'])->name('draf.berkas.unggah');
        Route::get('/lamaran/tahap/{id}/draf/berkas/{field}', [FormulirDrafController::class, 'berkas'])->name('draf.berkas');

        // ── JAWABAN KANDIDAT ATAS PENAWARAN ──
        // Menerima atau mundur. Kepemilikan tahap diperiksa lewat
        // Lamaran.Id_Users di controller, jadi id tahap orang lain tidak bisa
        // dipakai menjawabkan penawaran atas nama mereka.
        Route::post('/lamaran/tahap/{id}/tanggapan', [LamaranController::class, 'portalTanggapanPenawaran'])->name('tanggapan');
    });

// ── Referensi pendidikan untuk formulir (login saja) ──
// Dipakai field ber-tipe `referensi`: jenjang, jenis institusi, kampus, prodi.
// Kandidat memakainya saat mengisi formulir; admin memakainya di pratinjau
// Master Formulir — jadi cukup career.auth, tanpa gerbang peran.
Route::prefix('api/v1/referensi')
    ->middleware('career.auth')
    ->name('career.referensi.')
    ->group(function () {
        Route::get('/{sumber}', [\App\Http\Controllers\Career\Referensi\ReferensiController::class, 'opsi'])
            ->where('sumber', 'jenjang|jenis_institusi|kampus|prodi')
            ->name('opsi');
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
        Route::get('/loker', [LamaranController::class, 'lokerList'])->name('loker')->middleware('career.permission:lokerPage,VIEW');
        Route::post('/', [LamaranController::class, 'lamar'])->name('lamar')->middleware('career.permission:portalPage,CREATE');
        Route::get('/apply-status/{processId}', [LamaranController::class, 'applyStatus'])->name('apply.status');
        Route::post('/tahap/{id}/kirim', [LamaranController::class, 'kirimFormulir'])->name('kirim')->middleware('career.permission:portalPage,EDIT');
        Route::delete('/{id}', [LamaranController::class, 'batalkan'])->name('batal');
    });
