<?php
function _env(string $k, string $d = ''): string { $v = getenv($k); return $v === false ? $d : $v; }

return [
    'timezone' => 'Asia/Manila',

    'google' => [
        'enabled'        => true,
        'client_id'      => _env('GOOGLE_CLIENT_ID'),
        'client_secret'  => _env('GOOGLE_CLIENT_SECRET'),
        'refresh_token'  => _env('GOOGLE_REFRESH_TOKEN'),
        'calendar_id'    => _env('GOOGLE_CALENDAR_ID', 'primary'),
        'duration_min'   => 30,
    ],

    'brevo' => [
        'enabled'      => true,
        'api_key'      => _env('BREVO_API_KEY'),
        'sender_email' => _env('BREVO_SENDER_EMAIL', 'noreply@yourdomain.com'),
        'sender_name'  => 'MediTrack Hospital OS',
    ],

    'openfda' => [
        'enabled'    => true,
        'api_key'    => _env('OPENFDA_API_KEY'),
        'cache_days' => 7,
    ],
];
