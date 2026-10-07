<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * HANYA pemanggil mesin-ke-mesin tanpa sesi. Endpoint panel yang memakai sesi
     * login TIDAK boleh dikecualikan: login justru alasan CSRF diperlukan.
     * Frontend panel sudah membawa X-XSRF-TOKEN (axios & penjaga fetch).
     *
     * @var array<int, string>
     */
    protected $except = [
        // Penerima Cloud Tasks (stackkit) — diamankan token OIDC Cloud Tasks.
        '/handle-task',
        // Webhook hasil tes dari CAT/HCLearn (server-to-server, tanpa session/CSRF);
        // diamankan header X-WC-Secret di controller.
        'api/v1/webhook/*',
    ];
}
