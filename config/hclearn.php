<?php

/*
|--------------------------------------------------------------------------
| INTEGRASI HCLEARN / CAT — konfigurasi kanal Web Careers
|--------------------------------------------------------------------------
| Kredensial TIDAK PERNAH dikirim ke browser. Semua panggilan ke HCLearn
| dilakukan dari sisi server (HclClient), lalu hasilnya diteruskan ke Vue
| lewat endpoint internal Web Careers sendiri.
*/

return [

    // development | staging | production — dipilih lewat .env HCLEARN_ENV.
    'env' => env('HCLEARN_ENV', 'development'),

    'domains' => [
        'development' => env('HCLEARN_URL_DEVELOPMENT', 'http://cat-evo-pembaharuan.test'),
        'staging' => env('HCLEARN_URL_STAGING', 'https://cat-evo-stagging-595840247695.asia-southeast1.run.app'),
        'production' => env('HCLEARN_URL_PRODUCTION', 'https://hclearn.evonusabersaudara.co.id'),
    ],

    // Kredensial KANAL WEB CAREERS — sengaja beda nama dari HCLEARN_API_* yang
    // dipakai config/dev/fransDev.php (itu milik HCIS, jangan dipakai ulang).
    'api_public' => env('HCLEARN_WC_API_PUBLIC'),
    'api_secret' => env('HCLEARN_WC_API_SECRET'),

    'prefix' => 'api/v1/web-careers',

    // ── CALLBACK HASIL (CAT → Web Careers) ──
    // URL publik Web Careers yang bisa dijangkau CAT untuk push hasil tes.
    // Dikirim sebagai Url_Callback saat penjadwalan; CAT memanggilnya saat tes
    // difinalisasi. Guard: header X-WC-Secret dicocokkan ke callback_secret.
    'public_url' => rtrim(env('WC_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),
    'callback_path' => 'api/v1/webhook/hclearn-hasil',
    'callback_secret' => env('WC_CALLBACK_SECRET', env('HCLEARN_WC_API_SECRET')),

    'timeout' => (int) env('HCLEARN_TIMEOUT', 30),
    'retry' => (int) env('HCLEARN_RETRY', 2),

    // ── KAMERA / PROCTORING WAJIB ──
    // Saat penjadwalan ujian dikirim ke CAT (cat-evo-pembaharuan), Web Careers
    // MEMAKSA kamera aktif supaya tak ada sesi tes tanpa kamera. Kontrak CAT
    // (PenjadwalanWebCareersController) mengharap field 'Flag_Camera' bertipe
    // BOOLEAN — CAT mengubahnya jadi 'Y' pada token bila truthy.
    'wajib_kamera' => (bool) env('HCLEARN_WAJIB_KAMERA', true),
    'kamera_field' => env('HCLEARN_KAMERA_FIELD', 'Flag_Camera'),

    // Simpan payload penuh di N_WEB_CAREERS_Integrasi_Log hanya saat gagal.
    'log_payload_sukses' => (bool) env('HCLEARN_LOG_PAYLOAD_SUKSES', false),
    'log_retensi_hari' => (int) env('HCLEARN_LOG_RETENSI_HARI', 30),
];
