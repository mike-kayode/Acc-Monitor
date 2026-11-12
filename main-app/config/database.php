<?php
// Database configuration for NairaTrader Account Pool Monitor
$encodedPassword = (string) env('DB_PASSWORD_BASE64', '');
$dbPassword = $encodedPassword === '' ? env('DB_PASSWORD', '') : base64_decode($encodedPassword, true);
if ($dbPassword === false) {
    throw new RuntimeException('DB_PASSWORD_BASE64 is invalid.');
}

return [
    'connection' => env('DB_CONNECTION', 'mysql'),
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'nairatrader_monitor'),
    'username' => env('DB_USERNAME', ''),
    'password' => $dbPassword,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];