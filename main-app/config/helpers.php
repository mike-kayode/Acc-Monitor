<?php

use NAPM\Core\Env;

if (!class_exists('Security', false)) {
    class_alias(\NAPM\Core\Security::class, 'Security');
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = Env::get($key, $default);
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $basePath = defined('NAPM_BASE_PATH') ? NAPM_BASE_PATH : '';
        $path = ltrim($path, '/');
        return rtrim($basePath, '/') . ($path === '' ? '/' : '/' . $path);
    }
}
