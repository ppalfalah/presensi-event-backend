<?php

return [
    'database' => env('E2E_DB_DATABASE', 'presensi_event_e2e'),
    'public_storage_root' => storage_path('app/public/e2e'),

    'admin' => [
        'email' => env('E2E_ADMIN_EMAIL', 'e2e.admin@example.test'),
        'password' => env('E2E_ADMIN_PASSWORD', 'E2E-Admin-2026!'),
    ],

    'alumni' => [
        'email' => env('E2E_ALUMNI_EMAIL', 'e2e.alumni@example.test'),
        'password' => env('E2E_ALUMNI_PASSWORD', 'E2E-Alumni-2026!'),
    ],
];
