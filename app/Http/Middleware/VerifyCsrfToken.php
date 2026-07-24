<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/handle-task',
        'scheduler-T4sk-RuNn3r-sEcr3t',
        // Backfill cap KPI di-hit dari Postman (tanpa session/XSRF cookie); diamankan
        // secret token di controller. Dikecualikan dari CSRF agar tidak 419.
        'api/v1/kpi-enforcement/backfill-cap',
        // Webhook hasil tes dari CAT/HCLearn (server-to-server, tanpa session/CSRF);
        // diamankan header X-WC-Secret di controller.
        'api/v1/webhook/*',
    ];
}
