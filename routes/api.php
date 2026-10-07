<?php

/*
|--------------------------------------------------------------------------
| API Routes — PROJECT: WEB CAREERS ADMIN
|--------------------------------------------------------------------------
| Tanpa sesi & tanpa CSRF — hanya pemanggil mesin yang bertoken.
*/

// Pemicu tugas terjadwal (Cloud Scheduler → antrean Cloud Tasks).
require base_path('routes/career/Tugas/PemicuTugasApi.php');

// Langganan dorong Pub/Sub dua zona (token OIDC Google).
require base_path('routes/career/Tugas/DorongPubSubApi.php');
