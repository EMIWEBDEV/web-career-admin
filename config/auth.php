<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'web',
    ],

    /*
    | Panel memakai sesi `career_auth` sendiri (lihat AuthController &
    | CareerAuth); guard ini hanya penopang facade Auth bawaan (mis. logout).
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'staf',
        ],
    ],

    'providers' => [
        'staf' => [
            'driver' => 'database',
            'table' => 'N_WEB_CAREERS_Users',
        ],
    ],

    'password_timeout' => 10800,

];
