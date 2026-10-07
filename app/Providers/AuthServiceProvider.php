<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Otorisasi panel memakai hak akses Web Careers sendiri (career.role &
 * career.permission di atas sesi `career_auth`), bukan Gate/Policy Laravel.
 */
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        //
    }
}
