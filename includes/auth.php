<?php
/**
 * Admin authentication gate. Include at the top of every admin page
 * (except login.php) and call requireAdmin().
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

define('ADMIN_IDLE_TIMEOUT', 30 * 60); // 30 min inactivity → logout

function isAdminLoggedIn(): bool
{
    secureSessionStart();
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_2fa_ok'])) {
        return false;
    }
    // Bind session to UA to make cookie theft slightly harder
    $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (($_SESSION['ua_hash'] ?? '') !== $ua) {
        adminLogout();
        return false;
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        adminLogout();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function currentAdminId(): int
{
    return (int)($_SESSION['admin_id'] ?? 0);
}

function currentAdminName(): string
{
    return (string)($_SESSION['admin_username'] ?? '');
}

function adminLogout(): void
{
    secureSessionStart();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
