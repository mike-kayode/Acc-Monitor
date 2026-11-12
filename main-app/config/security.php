<?php
// Security configuration
return [
    'worker_secret' => env('WORKER_SECRET', ''),
    'hmac_secret' => env('HMAC_SECRET', ''),
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 60),
    'session_lifetime' => (int) env('SESSION_LIFETIME', 3600),
    'session_secure' => (bool) env('SESSION_SECURE', true),
    'session_httponly' => (bool) env('SESSION_HTTPONLY', true),
    'session_samesite' => env('SESSION_SAMESITE', 'Strict'),
    'encryption_key' => env('SESSION_ENCRYPTION_KEY', env('APP_SECRET_KEY', '')),
];