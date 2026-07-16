<?php
/**
 * Settings — change password (requires current password + fresh TOTP code).
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

$ok = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $totp    = $_POST['totp'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash, totp_secret, totp_last_slice FROM admins WHERE id = ?');
    $stmt->execute([currentAdminId()]);
    $admin = $stmt->fetch();
    $secret = $admin ? decryptSecret($admin['totp_secret'] ?? '') : '';

    // Replay protection: same rule as login — a code is accepted only once
    $slice = ($admin && $secret !== '') ? totpMatchSlice($secret, $totp) : null;
    if ($slice !== null && $slice <= (int)$admin['totp_last_slice']) {
        logSecurity('totp_replay', 'Reused TOTP code in settings');
        $slice = null;
    }

    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        $error = 'Current password is wrong.';
        logSecurity('failed_pw_change', 'Wrong current password');
    } elseif ($slice === null) {
        $error = 'Invalid 2FA code.';
        logSecurity('failed_pw_change', 'Wrong TOTP');
    } elseif (strlen($new) < 12) {
        $error = 'New password must be at least 12 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $pdo->prepare('UPDATE admins SET password_hash = ?, totp_last_slice = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $slice, currentAdminId()]);
        logSecurity('password_changed', 'Admin: ' . currentAdminName());
        session_regenerate_id(true);
        $ok = 'Password changed.';
    }
}

$pageTitle = 'Settings';
require __DIR__ . '/_header.php';
?>
<?php if ($ok): ?><div class="alert alert-ok">✅ <?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="panel" style="max-width:480px">
    <h2>🔑 Change password</h2>
    <form method="post" autocomplete="off"><?= csrfField() ?>
        <label>Current password</label>
        <input type="password" name="current_password" required>
        <label>New password (min 12 chars)</label>
        <input type="password" name="new_password" required minlength="12">
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" required minlength="12">
        <label>2FA code</label>
        <input name="totp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
        <button class="btn btn-primary btn-block">Change password</button>
    </form>
</div>

<div class="panel" style="max-width:480px">
    <h2>ℹ️ Site info</h2>
    <table class="data-table">
        <tr><td>Site URL</td><td><code><?= e(SITE_URL) ?></code></td></tr>
        <tr><td>PHP</td><td><code><?= e(PHP_VERSION) ?></code></td></tr>
        <tr><td>MySQL</td><td><code><?= e($pdo->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></code></td></tr>
        <tr><td>Session timeout</td><td><?= ADMIN_IDLE_TIMEOUT / 60 ?> min idle</td></tr>
        <tr><td>Login lockout</td><td><?= LOGIN_MAX_ATTEMPTS ?> fails / <?= LOGIN_WINDOW_MIN ?> min</td></tr>
    </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
