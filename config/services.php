<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Layanan pihak ketiga — PROJECT: WEB CAREERS ADMIN
    |--------------------------------------------------------------------------
    |
    | Hanya yang dipakai panel ini, tanpa satu pun nilai bawaan rahasia: token
    | & kunci wajib datang dari env. Integrasi CAT/HCLearn, HCIS, dan EVO Mail
    | punya berkas config-nya sendiri (hclearn.php, hcis.php, surat.php).
    |
    */

    'project_config' => [
        // Versi aplikasi yang ditampilkan di layar masuk & panel.
        'app_version' => env('APP_VERSION', '2.13.0'),
    ],

    // Cloudflare Turnstile (CAPTCHA halaman masuk panel).
    'cloudflare' => [
        'turnstile_sitekey' => env('CLOUDFLARE_TURNSTILE_SITEKEY'),
        'turnstile_secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
    ],
];
