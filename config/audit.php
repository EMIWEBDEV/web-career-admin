<?php

/*
|--------------------------------------------------------------------------
| Audit trail admin
|--------------------------------------------------------------------------
| Jejak perubahan data ditulis PEMICU database (docs/07-10-2026/admin/
| 01-audit-trail.sql). Aplikasi hanya menitipkan siapa & dari mana ke
| SESSION_CONTEXT sebelum perintah tulis — lihat App\Support\Audit\KonteksAudit.
*/

return [
    // false = konteks tidak dititipkan; pemicu tetap mencatat (Sumber LANGSUNG).
    'konteks' => (bool) env('AUDIT_KONTEKS', true),

    // Tabel teknis yang ditulis hampir tiap permintaan dan tidak diaudit —
    // menulisnya tidak perlu memasang konteks dulu.
    'lewati' => [
        'N_WEB_CAREERS_Sessions',
        'N_WEB_CAREERS_Jobs',
        'N_WEB_CAREERS_Failed_Jobs',
        'N_LMS_Jobs',
        'N_LMS_Failed_Jobs',
        'cache',
        'cache_locks',
    ],
];
