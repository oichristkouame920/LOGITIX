<?php
/**
 * Primitives de securite applicative pour les donnees sensibles.
 */

require_once __DIR__ . '/../config/config.php';

function logitixSensitiveKey(): string {
    if (TOTP_ENCRYPTION_KEY === '') {
        throw new RuntimeException('LOGITIX_TOTP_ENCRYPTION_KEY is not configured.');
    }

    $key = base64_decode(TOTP_ENCRYPTION_KEY, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('LOGITIX_TOTP_ENCRYPTION_KEY must be a base64 encoded 32-byte key.');
    }

    return $key;
}

function isTotpEncryptionConfigured(): bool {
    if (TOTP_ENCRYPTION_KEY === '') {
        return false;
    }
    $key = base64_decode(TOTP_ENCRYPTION_KEY, true);
    return $key !== false && strlen($key) === 32;
}

function encryptSensitiveValue(string $plaintext): string {
    $key = logitixSensitiveKey();

    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return 's1:' . base64_encode($nonce . $ciphertext);
    }

    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt sensitive value.');
        }
        return 'o1:' . base64_encode($iv . $tag . $ciphertext);
    }

    throw new RuntimeException('No supported authenticated encryption extension is available.');
}

function decryptSensitiveValue(?string $encoded): ?string {
    if ($encoded === null || $encoded === '') {
        return null;
    }

    $key = logitixSensitiveKey();

    if (logitixStartsWith($encoded, 's1:')) {
        if (!function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('Sodium is required to decrypt this TOTP secret.');
        }
        $raw = base64_decode(substr($encoded, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted TOTP secret.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt TOTP secret.');
        }
        return $plaintext;
    }

    if (logitixStartsWith($encoded, 'o1:')) {
        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL is required to decrypt this TOTP secret.');
        }
        $raw = base64_decode(substr($encoded, 3), true);
        if ($raw === false || strlen($raw) <= 28) {
            throw new RuntimeException('Invalid encrypted TOTP secret.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '');
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt TOTP secret.');
        }
        return $plaintext;
    }

    // Un secret sans prefixe est une ancienne valeur en clair : on refuse de
    // l'utiliser. La migration de securite force une nouvelle inscription 2FA.
    throw new RuntimeException('Legacy plaintext TOTP secret detected; re-enrollment is required.');
}
