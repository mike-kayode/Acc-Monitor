<?php
/**
 * Security Utilities
 * CSRF protection, input validation, output escaping, HMAC signing
 */

namespace NAPM\Core;

class Security
{
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function regenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function escape(string $string, string $flags = ENT_QUOTES | ENT_HTML5): string
    {
        return htmlspecialchars($string, $flags, 'UTF-8');
    }

    public static function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    public static function generateHmac(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret);
    }

    public static function verifyHmac(string $data, string $signature, string $secret): bool
    {
        $expected = self::generateHmac($data, $secret);
        return hash_equals($expected, $signature);
    }

    public static function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function validatePhoneNumber(string $phone): bool
    {
        // International format: + followed by 7-15 digits
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    public static function generateEventId(string $prefix = 'evt'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    public static function sanitizeArray(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $cleanKey = self::sanitizeInput($key);
            if (is_array($value)) {
                $clean[$cleanKey] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$cleanKey] = self::sanitizeInput($value);
            } else {
                $clean[$cleanKey] = $value;
            }
        }
        return $clean;
    }
}