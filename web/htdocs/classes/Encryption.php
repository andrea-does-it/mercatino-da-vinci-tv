<?php
/**
 * Encryption utility class for sensitive data (IBAN, etc.)
 * Uses AES-256-GCM for authenticated encryption
 */
class Encryption {

    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    /**
     * Encrypt data using AES-256-GCM
     *
     * @param string $plaintext The data to encrypt
     * @return string|false Base64-encoded encrypted data (IV + tag + ciphertext) or false on failure
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) {
            return null;
        }

        $key = self::getKey();
        if (!$key) {
            error_log('Encryption key not configured');
            return false;
        }

        // Generate a random IV
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Encrypt with authentication tag
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            error_log('Encryption failed: ' . openssl_error_string());
            return false;
        }

        // Combine IV + tag + ciphertext and encode as base64
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt data encrypted with AES-256-GCM
     *
     * @param string $encryptedData Base64-encoded encrypted data
     * @return string|false Decrypted plaintext or false on failure
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return null;
        }

        $key = self::getKey();
        if (!$key) {
            error_log('Encryption key not configured');
            return false;
        }

        // Decode from base64
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return false;
        }

        // Extract IV, tag, and ciphertext
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($data) < $ivLength + self::TAG_LENGTH) {
            return false;
        }

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLength + self::TAG_LENGTH);

        // Decrypt with authentication
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            error_log('Decryption failed: ' . openssl_error_string());
            return false;
        }

        return $plaintext;
    }

    /**
     * Get the encryption key from configuration
     *
     * @return string|false The binary encryption key or false if not configured
     */
    private static function getKey() {
        if (!defined('ENCRYPTION_KEY') || empty(ENCRYPTION_KEY)) {
            return false;
        }

        // The key should be a hex-encoded 256-bit (32 byte) key
        $key = hex2bin(ENCRYPTION_KEY);

        if ($key === false || strlen($key) !== 32) {
            error_log('Invalid encryption key format. Must be 64 hex characters (256 bits).');
            return false;
        }

        return $key;
    }

    /**
     * Generate a new random encryption key
     * Use this to create a key for your .env file
     *
     * @return string Hex-encoded 256-bit key
     */
    public static function generateKey() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Check if encryption is properly configured
     *
     * @return bool True if encryption key is configured and valid
     */
    public static function isConfigured() {
        return self::getKey() !== false;
    }
}
