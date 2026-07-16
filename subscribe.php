<?php
/**
 * Newsletter signup — collect-only (InfinityFree has no reliable mail()).
 * Emails land in `subscribers`; export CSV from admin and send via any
 * external service when you want to. CSRF + honeypot + rate limit.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}
requireCsrf();

$back = '/?sub=1';
if (honeypotTripped()) {
    logSecurity('honeypot_subscribe', 'Bot hit subscribe honeypot');
    header('Location: ' . $back); // pretend success to the bot
    exit;
}
if (actionCount('subscribed', RATE_WINDOW_MIN) >= 3) {
    logSecurity('rate_limited', 'subscribe flood');
    header('Location: /?sub=err');
    exit;
}

$email = str_param($_POST, 'email');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    header('Location: /?sub=bad');
    exit;
}

try {
    db()->prepare('INSERT INTO subscribers (email, ip_address) VALUES (?, ?)')
        ->execute([mb_strtolower($email), getClientIp()]);
    logSecurity('subscribed', 'New subscriber');
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') { // duplicate email → still act like success
        error_log('subscribe failed: ' . $e->getMessage());
        header('Location: /?sub=err');
        exit;
    }
}
header('Location: ' . $back);
exit;
