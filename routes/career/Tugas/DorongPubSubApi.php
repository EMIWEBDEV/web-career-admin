<?php

use App\Http\Controllers\Career\Tugas\DorongPubSubController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Langganan DORONG Pub/Sub — wc-masuk-sinkron
|--------------------------------------------------------------------------
| Aktif hanya bila PUBSUB_DORONG_AUDIENCE & PUBSUB_DORONG_AKUN diisi; selain
| itu 404. Pengamanannya token OIDC Google (lihat PenjagaDorong), bukan
| rahasia di URL. Selama langganannya masih TARIK, rute ini tidak dipakai —
| tick /api/tugas/sinkron yang menarik pesannya.
*/

Route::post('/pubsub/wc-masuk', [DorongPubSubController::class, 'terima'])
    ->withoutMiddleware(ThrottleRequests::class.':api')
    ->name('pubsub.wc-masuk');
