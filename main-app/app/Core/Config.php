<?php
/**
 * Configuration Loader
 * Loads configuration from PHP files and environment variables
 */

namespace NAPM\Core;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $file, string $key): void
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Configuration file not found: {$file}");
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new \RuntimeException("Configuration file must return an array: {$file}");
        }
        self::$config[$key] = $config;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new \RuntimeException("Configuration not loaded. Call Config::load() first.");
        }

        $keys = explode('.', $key);
        $data = self::$config;

        foreach ($keys as $k) {
            if (!isset($data[$k])) {
                return $default;
            }
            $data = $data[$k];
        }

        return $data;
    }

    public static function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $data = &self::$config;

        foreach ($keys as $k) {
            if (!isset($data[$k])) {
                $data[$k] = [];
            }
            $data = &$data[$k];
        }
        $data = $value;
        unset($data);
    }

    public static function all(string $key = null): array
    {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? [];
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}