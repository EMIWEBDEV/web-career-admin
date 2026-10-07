<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Global HTTP middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Panel admin tidak untuk mesin pencari (X-Robots-Tag: noindex, nofollow).
        \App\Http\Middleware\TanpaIndeks::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\NoCacheInLocal::class,
            // Siapa & dari mana untuk jejak audit — sesudah StartSession.
            \App\Http\Middleware\KonteksAuditPermintaan::class . ':PANEL',
        ],

        'api' => [
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\NoCacheInLocal::class,
            \App\Http\Middleware\KonteksAuditPermintaan::class . ':TUGAS',
        ],
    ];

    /**
     * Middleware aliases — hanya yang dipakai rute panel ini.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        // Web Career: wajib login, lalu gerbang peran. Urutannya penting —
        // career.role menganggap sesi sudah divalidasi career.auth.
        'career.auth' => \App\Http\Middleware\CareerAuth::class,
        'career.role' => \App\Http\Middleware\CareerRole::class,
        // Gerbang hak akses per halaman & aksi: career.permission:{jenisPage},{AKSI}
        'career.permission' => \App\Http\Middleware\CareerPermission::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    ];
}
