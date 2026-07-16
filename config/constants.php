<?php
/**
 * Site constants + config loader. Every entry point includes this first.
 */
declare(strict_types=1);

define('CB_ROOT', dirname(__DIR__));
define('CB_UPLOADS', CB_ROOT . '/protected_uploads');

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    // Not installed yet — send everything to the installer
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: /install.php');
        exit;
    }
    return;
}
$cfg = require $cfgFile;

define('DB_HOST', $cfg['db_host']);
define('DB_NAME', $cfg['db_name']);
define('DB_USER', $cfg['db_user']);
define('DB_PASS', $cfg['db_pass']);
define('SITE_URL', rtrim($cfg['site_url'], '/'));
define('SECRET_KEY', $cfg['secret_key']);

define('SITE_NAME', 'CyberBlogs');
define('SITE_TAGLINE', 'Cybersecurity Research & Write-ups');
define('SITE_DESC', 'Cybersecurity blog — ethical hacking, CTF write-ups, defensive security and tooling. byte.pro.bd');
define('POSTS_PER_PAGE', 9);

// Brute-force policy: 3 failures in 15 minutes → 15-minute block
define('LOGIN_MAX_ATTEMPTS', 3);
define('LOGIN_WINDOW_MIN', 15);

// Generic rate limits (per IP, per RATE_WINDOW_MIN minutes)
define('RATE_WINDOW_MIN', 15);
define('COMMENT_LIMIT', 5);      // comments / window
define('CONTACT_LIMIT', 3);      // messages / window
