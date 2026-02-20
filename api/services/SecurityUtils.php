<?php
namespace MapaRD\Services;

class SecurityUtils
{
    private static $cipher = "aes-256-cbc";

    /**
     * Encrypt data using AES-256-CBC
     */
    public static function encrypt($data)
    {
        if (!defined('MAPARD_SECRET_KEY'))
            return $data;

        $key = hash('sha256', MAPARD_SECRET_KEY, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($data, self::$cipher, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data using AES-256-CBC
     */
    public static function decrypt($data)
    {
        if (!defined('MAPARD_SECRET_KEY'))
            return $data;

        $data = base64_decode($data);
        $key = hash('sha256', MAPARD_SECRET_KEY, true);
        $iv_len = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $iv_len);
        $encrypted = substr($data, $iv_len);

        return openssl_decrypt($encrypted, self::$cipher, $key, 0, $iv);
    }

    /**
     * Hash password securely
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate 6-digit 2FA code
     */
    public static function generate2FA()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
