<?php
// Application configuration
return [
    'name' => env('APP_NAME', 'NairaTrader Account Monitor'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),
    'url' => env('APP_URL', 'https://your-monitor-domain.com'),
    'secret_key' => env('APP_SECRET_KEY', ''),
];