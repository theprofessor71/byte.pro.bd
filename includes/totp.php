<?php
/**
 * Minimal RFC 6238 TOTP — compatible with Google Authenticator.
 * SHA-1, 6 digits, 30-second period. No dependencies.
 */
declare(strict_types=1);

function base32Encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $bits = 0;
    $value = 0;
    foreach (str_split($data) as $chr) {
        $value = ($value << 8) | ord($chr);
        $bits += 8;
        while ($bits >= 5) {
            $out .= $alphabet[($value >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($value << (5 - $bits)) & 31];
    }
    return $out;
}

function base32Decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $out = '';
    $bits = 0;
    $value = 0;
    foreach (str_split($b32) as $chr) {
        $pos = strpos($alphabet, $chr);
        if ($pos === false) {
            continue;
        }
        $value = ($value << 5) | $pos;
        $bits += 5;
        if ($bits >= 8) {
            $out .= chr(($value >> ($bits - 8)) & 0xFF);
            $bits -= 8;
        }
    }
    return $out;
}

function totpGenerateSecret(): string
{
    return base32Encode(random_bytes(20)); // 160-bit, RFC-recommended
}

function totpCode(string $secretB32, ?int $timeSlice = null): string
{
    $timeSlice = $timeSlice ?? intdiv(time(), 30);
    $key  = base32Decode($secretB32);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/** Verify with ±1 time-slice tolerance for clock drift. Timing-safe compare. */
function totpVerify(string $secretB32, string $userCode): bool
{
    return totpMatchSlice($secretB32, $userCode) !== null;
}

/**
 * Like totpVerify() but returns the matched time-slice (or null).
 * Callers persist the slice and reject codes with slice <= last accepted
 * one — RFC 6238 §5.2 replay protection.
 */
function totpMatchSlice(string $secretB32, string $userCode): ?int
{
    $userCode = preg_replace('/\D/', '', $userCode);
    if (strlen($userCode) !== 6) {
        return null;
    }
    $slice = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $drift) {
        if (hash_equals(totpCode($secretB32, $slice + $drift), $userCode)) {
            return $slice + $drift;
        }
    }
    return null;
}

/** otpauth:// URI for the QR code shown once at setup. */
function totpUri(string $secretB32, string $account, string $issuer = 'CyberBlogs'): string
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?secret=' . $secretB32
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
