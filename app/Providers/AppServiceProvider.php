<?php

namespace App\Providers;

use App\Support\Audit\KonteksAudit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Siapa & dari mana untuk pemicu audit di database admin.
        KonteksAudit::daftarkan();
    }
}
