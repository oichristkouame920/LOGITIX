<?php
/**
 * Implementation TOTP RFC 6238 sans dependance externe.
 */

class TOTP {
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string {
        if ($bytes < 20) {
            $bytes = 20;
        }
        return self::base32Encode(random_bytes($bytes));
    }

    public static function getCode(string $base32Secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, self::PERIOD);
        return self::getCodeForCounter($base32Secret, $counter);
    }

    public static function verify(string $base32Secret, string $code, int $window = 1): bool {
        return self::matchingCounter($base32Secret, $code, $window) !== null;
    }

    /** Retourne le compteur accepte pour permettre le blocage du rejeu. */
    public static function matchingCounter(string $base32Secret, string $code, int $window = 1): ?int {
        $code = preg_replace('/\s+/', '', $code);
        if (!is_string($code) || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $currentCounter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $counter = $currentCounter + $i;
            if ($counter < 0) {
                continue;
            }
            if (hash_equals(self::getCodeForCounter($base32Secret, $counter), $code)) {
                return $counter;
            }
        }
        return null;
    }

    public static function getProvisioningUri(string $base32Secret, string $accountLabel, string $issuer): string {
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $params = http_build_query([
            'secret' => $base32Secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
        return "otpauth://totp/{$label}?{$params}";
    }

    private static function getCodeForCounter(string $base32Secret, int $counter): string {
        $secret = self::base32Decode($base32Secret);
        if ($secret === '') {
            throw new InvalidArgumentException('Invalid TOTP secret.');
        }

        $high = intdiv($counter, 0x100000000);
        $low = $counter % 0x100000000;
        $binCounter = pack('N2', $high, $low);

        $hash = hash_hmac('sha1', $binCounter, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = $truncated % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $bits = str_pad($bits, (int) ceil(strlen($bits) / 5) * 5, '0', STR_PAD_RIGHT);

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec($chunk)];
        }
        return $output;
    }

    private static function base32Decode(string $base32): string {
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
        $bits = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }
        return $output;
    }
}
