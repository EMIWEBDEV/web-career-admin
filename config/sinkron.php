<?php

/*
|--------------------------------------------------------------------------
| Dua zona — sisi ADMIN (zona dalam)
|--------------------------------------------------------------------------
|
| Panel admin tidak melayani halaman kandidat. Situs kandidat adalah project
| terpisah (web-careers-pengguna) dengan database publiknya sendiri.
|
|   pengguna_url   alamat situs kandidat. SEMUA tautan untuk kandidat di surel
|                  (konfirmasi kehadiran, surat jadwal, feedback, verifikasi,
|                  portal) menunjuk ke sini — lihat App\Support\Sinkron\TautanPengguna.
|   kunci_tautan   kunci tanda tangan tautan kandidat. WAJIB SAMA dengan
|                  TAUTAN_KUNCI di project pengguna; beda satu karakter = semua
|                  tautan di surel ditolak di sana.
|   kunci_rahasia  kunci AES-256 pembuka OTP/token yang dibungkus pengguna di
|                  peristiwa Akun.KodeDiminta. WAJIB SAMA dengan pengguna.
|
| Jalur data (Sync Worker — kode di app/Support/Sinkron):
|
|   MASUK   Pub/Sub wc-masuk ─► langganan wc-masuk-sinkron ─┬─ tarik (tick / sinkron:jalan)
|                                                          └─ dorong (POST /api/pubsub/wc-masuk)
|           ─► usp_WC_Sinkron_Terima (kotak masuk) ─► PemrosesMasuk ─► kode admin yang ada
|
|   KELUAR  Sinkron_Keluar (potret portal, hasil peristiwa, dokumen, status akun)
|           + penyapu salinan master ─► database publik (koneksi `pengguna`)
|
*/

return [

    'pengguna_url' => rtrim((string) env('PENGGUNA_URL', ''), '/'),

    'kunci_tautan' => env('TAUTAN_KUNCI'),

    'kunci_rahasia' => env('SINKRON_KUNCI_RAHASIA'),

    // Koneksi database PUBLIK (config/database.php → connections.pengguna).
    'koneksi_publik' => env('SINKRON_KONEKSI_PUBLIK', 'pengguna'),

    'pubsub' => [
        'project' => env('PUBSUB_PROJECT', env('GOOGLE_CLOUD_PROJECT_ID')),
        'langganan' => env('PUBSUB_LANGGANAN', 'wc-masuk-sinkron'),
        // Endpoint regional yang sama dengan penerbit di project pengguna.
        'endpoint' => env('PUBSUB_ENDPOINT', 'https://asia-southeast1-pubsub.googleapis.com'),
        'timeout' => (int) env('PUBSUB_TIMEOUT', 20),
        // Paling banyak pesan per satu permintaan pull.
        'per_tarik' => (int) env('PUBSUB_PER_TARIK', 100),
    ],

    // LANGGANAN DORONG (push). Kosong = rute POST /api/pubsub/wc-masuk menjawab
    // 404. Bila langganan diubah ke push dengan autentikasi OIDC, isi keduanya:
    //   audience  = nilai "Audience" di setelan push (bawaan: URL endpoint-nya)
    //   akun      = email akun layanan yang dipakai Pub/Sub untuk menandatangani
    'dorong' => [
        'audience' => env('PUBSUB_DORONG_AUDIENCE'),
        'akun' => env('PUBSUB_DORONG_AKUN'),
    ],

    // Bucket lintas zona. Karantina ditulis aplikasi pengguna (CV, berkas
    // formulir & tes) — zona dalam hanya MENYALIN darinya ke bucket admin.
    // Publik hanya dibaca pengguna; zona dalam yang mengisinya (surat
    // pengantar, berkas hasil tahap, salinan bersih berkas kandidat).
    'bucket' => [
        'karantina' => env('GCS_BUCKET_KARANTINA', 'web-careers-karantina'),
        'publik' => env('GCS_BUCKET_PUBLIK', 'web-careers-publik'),
    ],

    'masuk' => [
        // Percobaan proses satu peristiwa sebelum MATI (jeda 30 dtk, 1, 2, 4 … 30 mnt).
        'maks_percobaan' => (int) env('SINKRON_MAKS_PERCOBAAN', 8),
        // Peristiwa per satu putaran pemroses.
        'batas' => (int) env('SINKRON_BATAS_PROSES', 200),
    ],

    'keluar' => [
        // Baris Sinkron_Keluar per klaim + lama sewanya.
        'batas' => (int) env('SINKRON_BATAS_DORONG', 200),
        'sewa_detik' => 120,
    ],

    'potret' => [
        // Lamaran yang disegarkan bergiliran tiap putaran (menangkap perubahan
        // yang lolos dari penanda waktu, mis. batas konfirmasi yang lewat).
        'giliran' => (int) env('SINKRON_POTRET_GILIRAN', 40),
    ],

    'salinan' => [
        // Rekonsiliasi PENUH (tanpa gerbang sidik tabel) tiap N menit.
        'penuh_menit' => (int) env('SINKRON_SALINAN_PENUH_MENIT', 360),
        // Baris per satu MERGE ke database publik.
        'per_tulis' => 500,
        // Baris per satu INSERT saat menyalin baris baru (muatan awal).
        'per_tambah' => 2000,
    ],

    // Satu putaran tick (POST /api/tugas/sinkron atau sinkron:jalan --sekali):
    // berapa lama boleh menarik Pub/Sub sebelum lanjut memproses & mendorong.
    'tick_detik_tarik' => (int) env('SINKRON_TICK_DETIK_TARIK', 20),

];
