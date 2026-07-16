<?php
/**
 * One-time installer: writes config.php, creates tables, creates the first
 * admin with 2FA. Self-disables via install.lock — delete this file after
 * setup anyway.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$lockFile = __DIR__ . '/config/install.lock';
$cfgFile  = __DIR__ . '/config/config.php';

if (is_file($lockFile)) {
    http_response_code(403);
    exit('<h1>Already installed</h1><p>Installation is locked. Delete <code>config/install.lock</code> AND <code>config/config.php</code> to reinstall (this wipes nothing in the DB). Also delete <code>install.php</code> from the server now if you have not.</p>');
}

$step   = $_POST['step'] ?? '1';
$errors = [];
$out    = '';

// Minimal CSRF for the one-time installer (full stack isn't loaded yet)
session_start();
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

function field(string $k): string
{
    $v = $_POST[$k] ?? '';
    return is_string($v) ? trim($v) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    if (!hash_equals($_SESSION['install_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Invalid request token — reload the page and try again.';
    }
    $dbHost = field('db_host'); $dbName = field('db_name');
    $dbUser = field('db_user'); $dbPass = $_POST['db_pass'] ?? '';
    $siteUrl = rtrim(field('site_url'), '/');
    $adminUser = field('admin_user');
    $adminPass = $_POST['admin_pass'] ?? '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') $errors[] = 'Database host, name and user are required.';
    if (!preg_match('#^https://#', $siteUrl))               $errors[] = 'Site URL must start with https://';
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $adminUser)) $errors[] = 'Admin username: 3–50 chars, letters/digits/._- only.';
    if (strlen($adminPass) < 12)                             $errors[] = 'Admin password must be at least 12 characters.';

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Create schema — strip comment lines first, then split on ";"
            $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
            $schema = preg_replace('/^\s*--.*$/m', '', $schema);
            foreach (array_filter(array_map('trim', preg_split('/;\s*(\n|$)/', $schema))) as $stmt) {
                $pdo->exec($stmt);
            }

            // Write config.php
            $secretKey = bin2hex(random_bytes(32));
            $cfg = "<?php\nreturn [\n"
                . "    'db_host'    => " . var_export($dbHost, true) . ",\n"
                . "    'db_name'    => " . var_export($dbName, true) . ",\n"
                . "    'db_user'    => " . var_export($dbUser, true) . ",\n"
                . "    'db_pass'    => " . var_export($dbPass, true) . ",\n"
                . "    'site_url'   => " . var_export($siteUrl, true) . ",\n"
                . "    'secret_key' => " . var_export($secretKey, true) . ",\n"
                . "];\n";
            if (file_put_contents($cfgFile, $cfg, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config/config.php — check folder permissions.');
            }

            require __DIR__ . '/config/security.php';
            require __DIR__ . '/includes/totp.php';

            // First admin + encrypted TOTP secret
            $totpSecret = totpGenerateSecret();
            $pdo->prepare('INSERT INTO admins (username, password_hash, totp_secret) VALUES (?, ?, ?)')
                ->execute([
                    $adminUser,
                    password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
                    encryptSecret($totpSecret),
                ]);

            // Lock installer
            file_put_contents($lockFile, date('c') . "\n", LOCK_EX);

            $uri = totpUri($totpSecret, $adminUser);
            // NOTE: QR deliberately NOT rendered via a third-party image API —
            // that would send the TOTP seed to an external server. Manual entry
            // (or a local QR app pointed at the URI) keeps the secret private.
            $out = '<h1>✅ Installed</h1>'
                . '<div class="card"><h2>Set up 2FA now — shown only once</h2>'
                . '<p>In Google Authenticator (or any TOTP app) choose <strong>Enter a setup key</strong> and add:</p>'
                . '<p>Account: <code>' . htmlspecialchars($adminUser) . '</code><br>'
                . 'Key: <code>' . htmlspecialchars($totpSecret) . '</code><br>'
                . 'Type: <code>Time based</code></p>'
                . '<p>Or paste this URI into an app that accepts otpauth links:</p>'
                . '<p><code>' . htmlspecialchars($uri) . '</code></p></div>'
                . '<div class="card warn"><h2>⚠️ Do these immediately</h2><ol>'
                . '<li><strong>Delete <code>install.php</code> from the server.</strong> (It is locked, but delete it anyway.)</li>'
                . '<li>Confirm <code>config/.htaccess</code> exists (denies web access to config).</li>'
                . '<li>Log in at <a href="/admin/login.php">/admin/login.php</a> and verify 2FA works.</li>'
                . '</ol></div>';
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CyberBlogs — Install</title>
<style>
:root{color-scheme:dark}
body{font-family:system-ui,sans-serif;background:#0a0e17;color:#e2e8f0;max-width:640px;margin:40px auto;padding:0 16px}
h1{color:#00f0ff}.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:20px;margin:16px 0}
.card.warn{border-color:#f59e0b}.err{background:#7f1d1d;border-radius:8px;padding:10px 14px;margin:8px 0}
label{display:block;margin:12px 0 4px;font-size:14px;color:#94a3b8}
input{width:100%;padding:10px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;box-sizing:border-box}
button{margin-top:18px;background:linear-gradient(90deg,#00f0ff,#00ff88);color:#0a0e17;font-weight:700;border:0;border-radius:8px;padding:12px 24px;cursor:pointer}
code{background:#1e293b;padding:2px 6px;border-radius:4px;word-break:break-all}
a{color:#00f0ff}
</style>
</head>
<body>
<?php if ($out): ?>
    <?= $out /* built above from escaped parts */ ?>
<?php else: ?>
    <h1>🛡️ CyberBlogs Setup</h1>
    <?php foreach ($errors as $e): ?><div class="err"><?= $e ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
        <div class="card">
            <h2>Database (from InfinityFree panel)</h2>
            <label>MySQL Host</label><input name="db_host" required value="<?= htmlspecialchars(field('db_host')) ?>" placeholder="sqlXXX.infinityfree.com">
            <label>Database Name</label><input name="db_name" required value="<?= htmlspecialchars(field('db_name')) ?>" placeholder="if0_XXXXXXXX_cyberblogs">
            <label>Username</label><input name="db_user" required value="<?= htmlspecialchars(field('db_user')) ?>" placeholder="if0_XXXXXXXX">
            <label>Password</label><input name="db_pass" type="password">
        </div>
        <div class="card">
            <h2>Site</h2>
            <label>Site URL</label><input name="site_url" required value="<?= htmlspecialchars(field('site_url') ?: 'https://byte.pro.bd') ?>">
        </div>
        <div class="card">
            <h2>First Admin</h2>
            <label>Username</label><input name="admin_user" required minlength="3" maxlength="50">
            <label>Password (min 12 chars)</label><input name="admin_pass" type="password" required minlength="12">
        </div>
        <button type="submit">Install →</button>
    </form>
<?php endif; ?>
</body>
</html>
