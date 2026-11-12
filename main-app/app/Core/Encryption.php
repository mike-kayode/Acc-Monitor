<?php
/**
 * Encryption Utility
 * AES-256-GCM encryption for sensitive data
 */

namespace NAPM\Core;

use Exception;

class Encryption
{
    private string $key;
    private string $cipher = 'aes-256-gcm';

    public function __construct(string $encryptionKey)
    {
        if (strlen($encryptionKey) < 32) {
            throw new \InvalidArgumentException("Encryption key must be at least 32 characters");
        }
        $this->key = substr(hash('sha256', $encryptionKey, true), 0, 32);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $tag = null;
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException("Encryption failed: " . openssl_error_string());
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $ciphertext): string
    {
        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            throw new \InvalidArgumentException("Invalid ciphertext format");
        }

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertextData = substr($data, 32);

        $plaintext = openssl_decrypt(
            $ciphertextData,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException("Decryption failed: " . openssl_error_string());
        }

        return $plaintext;
    }
}