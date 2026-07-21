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

    'timeout' => (int) env('HCLEARN_TIMEOUT', 30),
    'retry' => (int) env('HCLEARN_RETRY', 2),

    // Simpan payload penuh di N_WEB_CAREERS_Integrasi_Log hanya saat gagal.
    'log_payload_sukses' => (bool) env('HCLEARN_LOG_PAYLOAD_SUKSES', false),
    'log_retensi_hari' => (int) env('HCLEARN_LOG_RETENSI_HARI', 30),
];
