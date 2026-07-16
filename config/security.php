<?php
/**
 * Security core: headers, sessions, real client IP (Cloudflare-aware),
 * CSRF tokens, IP rate limiting, security logging.
 */
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/* ---------- HTTP security headers ---------- */

/** HTTPS at the edge counts too — behind Cloudflare Flexible the origin sees
 *  plain HTTP but the visitor is on https (X-Forwarded-Proto). */
function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sendSecurityHeaders(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: https:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ---------- Session ---------- */

function secureSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('cb_session');
    session_start();
}

/* ---------- Real client IP (Cloudflare-aware, spoof-resistant) ---------- */

/**
 * Behind Cloudflare REMOTE_ADDR is the edge, not the visitor. Trust
 * CF-Connecting-IP only when REMOTE_ADDR is inside Cloudflare's published
 * ranges — otherwise the header is attacker-controlled and would let anyone
 * dodge (or weaponize) IP blocks.
 */
function getClientIp(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cfIp   = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';

    if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP) && ipFromCloudflare($remote)) {
        return $cfIp;
    }
    return $remote;
}

function ipFromCloudflare(string $ip): bool
{
    // https://www.cloudflare.com/ips/ (v4 + v6), current as of mid-2026
    static $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    foreach ($ranges as $cidr) {
        if (ipInCidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function ipInCidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr);
    $ipBin  = inet_pton($ip);
    $subBin = inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
        return false;
    }
    $bits  = (int)$bits;
    $bytes = intdiv($bits, 8);
    $rem   = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
        return false;
    }
    if ($rem > 0) {
        $mask = 0xFF << (8 - $rem) & 0xFF;
        if ((ord($ipBin[$bytes]) & $mask) !== (ord($subBin[$bytes]) & $mask)) {
            return false;
        }
    }
    return true;
}

/* ---------- CSRF ---------- */

function csrfToken(): string
{
    secureSessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(?string $token = null): bool
{
    secureSessionStart();
    $token    = $token ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function requireCsrf(): void
{
    if (!verifyCsrf()) {
        logSecurity('csrf_failure', 'Invalid CSRF token on ' . ($_SERVER['REQUEST_URI'] ?? '?'));
        http_response_code(403);
        exit('Invalid request token. Go back, refresh the page and try again.');
    }
}

/* ---------- Security log + rate limiting ---------- */

function logSecurity(string $action, string $details = ''): void
{
    try {
        db()->prepare('INSERT INTO security_logs (ip_address, action, details, user_agent)
                       VALUES (?, ?, ?, ?)')
            ->execute([
                getClientIp(),
                substr($action, 0, 50),
                substr($details, 0, 2000),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
    } catch (PDOException $e) {
        error_log('security log write failed: ' . $e->getMessage());
    }
}

/** Count occurrences of $action from this IP within the last $windowMin minutes. */
function actionCount(string $action, int $windowMin): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM security_logs
                           WHERE ip_address = ? AND action = ?
                             AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $stmt->execute([getClientIp(), $action, $windowMin]);
    return (int)$stmt->fetch()['c'];
}

function isLoginBlocked(): bool
{
    return actionCount('failed_login', LOGIN_WINDOW_MIN) >= LOGIN_MAX_ATTEMPTS
        || actionCount('failed_2fa', LOGIN_WINDOW_MIN) >= LOGIN_MAX_ATTEMPTS;
}

/* ---------- TOTP secret encryption (AES-256-GCM with SECRET_KEY) ---------- */

function encryptSecret(string $plain): string
{
    $key   = hash('sha256', SECRET_KEY, true);
    $iv    = random_bytes(12);
    $tag   = '';
    $ct    = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}

function decryptSecret(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $key = hash('sha256', SECRET_KEY, true);
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    $pt  = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $pt === false ? '' : $pt;
}

/** Privacy-friendly IP hash for reaction dedup (raw IP never stored). */
function ipHash(): string
{
    return hash('sha256', getClientIp() . '|' . SECRET_KEY);
}
