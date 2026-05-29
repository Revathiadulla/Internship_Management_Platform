<?php
/**
 * includes/crypto_helper.php
 * Handles security operations like AES-256 encryption/decryption of Aadhaar numbers
 * and formatting/masking for UI display.
 */

// Define standard encryption method
define('AES_METHOD', 'AES-256-CBC');

/**
 * Retrieves the encryption key from environment or fallback.
 * Ensure it is a 32-byte key.
 */
function get_crypto_key(): string {
    $key = getenv('ENCRYPTION_KEY') ?: ($_ENV['ENCRYPTION_KEY'] ?? '');
    if (empty($key)) {
        // Safe fallback key for local dev if key is missing
        $key = 'IMP_SECURE_ENCRYPTION_KEY_FALLBACK_2026';
    }
    // Pad or truncate to ensure exactly 32 bytes
    return substr(hash('sha256', $key, true), 0, 32);
}

/**
 * Encrypts data using AES-256-CBC
 */
function encrypt_aadhaar(?string $aadhaar): ?string {
    if ($aadhaar === null || trim($aadhaar) === '') {
        return null;
    }
    
    // Normalize - strip spaces
    $aadhaar = preg_replace('/\s+/', '', $aadhaar);
    
    $key = get_crypto_key();
    $iv_length = openssl_cipher_iv_length(AES_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    $ciphertext = openssl_encrypt($aadhaar, AES_METHOD, $key, 0, $iv);
    if ($ciphertext === false) {
        return null;
    }
    
    // Prepend IV (base64 encoded) so it can be unpacked during decryption
    return base64_encode($iv) . ':' . $ciphertext;
}

/**
 * Decrypts data using AES-256-CBC
 */
function decrypt_aadhaar(?string $encrypted_aadhaar): ?string {
    if ($encrypted_aadhaar === null || trim($encrypted_aadhaar) === '') {
        return null;
    }
    
    // If it's already a plain text 12-digit number (not encrypted), return it as is
    if (preg_match('/^[0-9]{12}$/', $encrypted_aadhaar)) {
        return $encrypted_aadhaar;
    }
    
    $parts = explode(':', $encrypted_aadhaar, 2);
    if (count($parts) !== 2) {
        return $encrypted_aadhaar; // Fallback to raw if not in IV:ciphertext format
    }
    
    $iv = base64_decode($parts[0], true);
    $ciphertext = $parts[1];
    
    if ($iv === false) {
        return $encrypted_aadhaar;
    }
    
    $key = get_crypto_key();
    $decrypted = openssl_decrypt($ciphertext, AES_METHOD, $key, 0, $iv);
    
    return $decrypted !== false ? $decrypted : $encrypted_aadhaar;
}

/**
 * Masks Aadhaar number for secure UI display
 * e.g., '123456789012' -> '•••• •••• 9012'
 */
function mask_aadhaar(?string $aadhaar): string {
    if ($aadhaar === null || trim($aadhaar) === '') {
        return '—';
    }
    
    // If it looks like ciphertext (contains ':'), decrypt it first
    if (strpos($aadhaar, ':') !== false) {
        $aadhaar = decrypt_aadhaar($aadhaar);
    }
    
    // Strip spaces
    $aadhaar = preg_replace('/\s+/', '', $aadhaar);
    
    if (strlen($aadhaar) >= 4) {
        $last_four = substr($aadhaar, -4);
        return '•••• •••• ' . $last_four;
    }
    
    return '•••• •••• ••••';
}
