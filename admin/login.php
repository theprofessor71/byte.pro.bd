<?php
/**
 * Admin login — password step, then TOTP step. IP-based brute-force
 * lockout (3 fails / 15 min), CSRF on both steps, full security logging.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/auth.php';

sendSecurityHeaders();
secureSessionStart();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
$stage = 'password';                       // password | totp
if (!empty($_SESSION['pending_admin_id'])) {
    $stage = 'totp';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (isLoginBlocked()) {
        logSecurity('blocked_attempt', 'Login while IP blocked');
        $error = 'Too many failed attempts. Try again in ' . LOGIN_WINDOW_MIN . ' minutes.';
    } elseif (($_POST['stage'] ?? '') === 'password') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT id, username, password_hash, totp_secret FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Always verify against something → constant-time-ish whether user exists or not
        $hash = $admin['password_hash'] ?? '$2y$12$' . str_repeat('x', 53);
        if ($admin && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['pending_admin_id'] = (int)$admin['id'];
            $_SESSION['pending_started']  = time();
            $stage = 'totp';
        } else {
            logSecurity('failed_login', 'Bad credentials for username: ' . substr($username, 0, 50));
            $error = 'Invalid credentials.';
            if (isLoginBlocked()) {
                $error = 'Too many failed attempts. IP blocked for ' . LOGIN_WINDOW_MIN . ' minutes.';
            }
        }
    } elseif (($_POST['stage'] ?? '') === 'totp' && !empty($_SESSION['pending_admin_id'])) {
        // 2FA window: 5 minutes to enter the code
        if (time() - ($_SESSION['pending_started'] ?? 0) > 300) {
            unset($_SESSION['pending_admin_id'], $_SESSION['pending_started']);
            $error = '2FA window expired — start again.';
            $stage = 'password';
        } else {
            $stmt = db()->prepare('SELECT id, username, totp_secret, totp_last_slice FROM admins WHERE id = ?');
            $stmt->execute([$_SESSION['pending_admin_id']]);
            $admin = $stmt->fetch();
            $secret = $admin ? decryptSecret($admin['totp_secret'] ?? '') : '';

            $slice = ($admin && $secret !== '') ? totpMatchSlice($secret, $_POST['totp'] ?? '') : null;
            // Replay protection (RFC 6238 §5.2): a code may only be accepted once
            if ($slice !== null && $slice <= (int)$admin['totp_last_slice']) {
                logSecurity('totp_replay', 'Reused TOTP code for admin id ' . (int)$admin['id']);
                $slice = null;
            }

            if ($slice !== null) {
                db()->prepare('UPDATE admins SET totp_last_slice = ? WHERE id = ?')
                    ->execute([$slice, (int)$admin['id']]);
                session_regenerate_id(true);
                unset($_SESSION['pending_admin_id'], $_SESSION['pending_started']);
                unset($_SESSION['csrf_token']); // fresh token for the authenticated session
                $_SESSION['admin_id']       = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_2fa_ok']   = true;
                $_SESSION['ua_hash']        = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                $_SESSION['last_activity']  = time();
                logSecurity('login_success', 'Admin: ' . $admin['username']);
                header('Location: /admin/dashboard.php');
                exit;
            }
            logSecurity('failed_2fa', 'Bad TOTP for admin id ' . (int)$_SESSION['pending_admin_id']);
            $error = 'Invalid 2FA code.';
            $stage = 'totp';
            if (isLoginBlocked()) {
                unset($_SESSION['pending_admin_id'], $_SESSION['pending_started']);
                $error = 'Too many failed attempts. IP blocked for ' . LOGIN_WINDOW_MIN . ' minutes.';
                $stage = 'password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Admin Login — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-logo">🛡️ <span class="grad-text"><?= e(SITE_NAME) ?></span></div>

    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if ($stage === 'password'): ?>
    <form method="post" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="stage" value="password">
        <label for="u">Username</label>
        <input id="u" name="username" required maxlength="50" autofocus>
        <label for="p">Password</label>
        <input id="p" name="password" type="password" required>
        <button type="submit" class="btn btn-primary btn-block">Continue →</button>
    </form>
    <?php else: ?>
    <form method="post" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="stage" value="totp">
        <p class="totp-hint">📱 Enter the 6-digit code from your authenticator app</p>
        <input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus class="totp-input" placeholder="••••••">
        <button type="submit" class="btn btn-primary btn-block">Verify ✓</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
