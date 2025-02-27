<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption utility functions for WP Repo Manager
 */
class WPRM_Encryption {
    /**
     * Salt to use for encryption
     */
    private static function get_salt() {
        // Use WordPress' AUTH_SALT if available, or fallback to a default
        if (defined('AUTH_SALT') && AUTH_SALT) {
            return AUTH_SALT;
        }
        
        // Generate a unique salt per site if AUTH_SALT isn't available
        $salt = get_option('wprm_encryption_salt');
        if (!$salt) {
            $salt = wp_generate_password(64, true, true);
            update_option('wprm_encryption_salt', $salt);
        }
        return $salt;
    }
    
    /**
     * Encrypt data using WordPress's encryption functions
     * 
     * @param string $data The data to encrypt
     * @return string The encrypted data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $salt = self::get_salt();
        
        // If sodium extension is available (WP 5.2+), use it for encryption
        if (function_exists('sodium_crypto_secretbox')) {
            try {
                $key = substr(sodium_crypto_generichash($salt, '', 32), 0, 32);
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $encrypted = sodium_crypto_secretbox($data, $nonce, $key);
                
                // Return nonce + encrypted data as base64 string
                return base64_encode($nonce . $encrypted);
            } catch (Exception $e) {
                // Fallback to OpenSSL if sodium fails
            }
        }
        
        // Fallback for older WordPress versions using OpenSSL
        $method = 'aes-256-ctr';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $key = substr(hash('sha256', $salt), 0, 32);
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        // Return iv + encrypted data as base64 string
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data encrypted with self::encrypt()
     * 
     * @param string $encrypted The encrypted data
     * @return string The decrypted data
     */
    public static function decrypt($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        $salt = self::get_salt();
        $decoded = base64_decode($encrypted);
        
        // If sodium extension is available (WP 5.2+), use it for decryption
        if (function_exists('sodium_crypto_secretbox')) {
            try {
                $key = substr(sodium_crypto_generichash($salt, '', 32), 0, 32);
                $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                
                $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
                if ($decrypted === false) {
                    return '';
                }
                
                return $decrypted;
            } catch (Exception $e) {
                // Fallback to OpenSSL if sodium fails
            }
        }
        
        // Fallback for older WordPress versions using OpenSSL
        $method = 'aes-256-ctr';
        $ivlen = openssl_cipher_iv_length($method);
        
        $iv = substr($decoded, 0, $ivlen);
        $encrypted = substr($decoded, $ivlen);
        
        $key = substr(hash('sha256', $salt), 0, 32);
        $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
        
        return $decrypted;
    }
}
