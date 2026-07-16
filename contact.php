<?php
/**
 * Contact form → contact_messages table. CSRF + honeypot + rate limit.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$ok = $error = '';
$vals = ['sender_name' => '', 'sender_email' => '', 'subject' => '', 'message' => ''];
if (isset($_GET['sent'])) {
    $ok = 'Message sent — thank you! I read everything, reply may take a day or two.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($vals as $k => $_) {
        $vals[$k] = str_param($_POST, $k);
    }

    if (honeypotTripped()) {
        logSecurity('honeypot_contact', 'Bot hit contact honeypot');
        $ok = 'Message sent — thank you!'; // pretend success to the bot
    } elseif (actionCount('contact_sent', RATE_WINDOW_MIN) >= CONTACT_LIMIT) {
        $error = 'Too many messages — please wait a while before sending another.';
        logSecurity('rate_limited', 'contact flood');
    } elseif ($vals['sender_name'] === '' || mb_strlen($vals['sender_name']) > 100) {
        $error = 'Name is required (max 100 characters).';
    } elseif (!filter_var($vals['sender_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } elseif ($vals['subject'] === '' || mb_strlen($vals['subject']) > 255) {
        $error = 'Subject is required (max 255 characters).';
    } elseif (mb_strlen($vals['message']) < 10 || mb_strlen($vals['message']) > 5000) {
        $error = 'Message must be 10–5000 characters.';
    } else {
        db()->prepare('INSERT INTO contact_messages (sender_name, sender_email, subject, message, ip_address)
                       VALUES (?,?,?,?,?)')
            ->execute([$vals['sender_name'], $vals['sender_email'], $vals['subject'], $vals['message'], getClientIp()]);
        logSecurity('contact_sent', 'From: ' . $vals['sender_email']);
        // POST/redirect/GET — refresh must not resend the message
        header('Location: /contact.php?sent=1');
        exit;
    }
}

$pageTitle = 'Contact';
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading grad-text">Contact</h1>
    <p class="muted">Messages go straight to my dashboard — no third-party services, no email trackers.</p>
</div>
<div class="container" style="max-width:640px">
    <?php if ($ok): ?><div class="alert alert-ok">✅ <?= e($ok) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="glass panel-lg contact-form">
        <?= csrfField() ?>
        <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-row">
            <div>
                <label>Name *</label>
                <input name="sender_name" required maxlength="100" value="<?= e($vals['sender_name']) ?>">
            </div>
            <div>
                <label>Email *</label>
                <input name="sender_email" type="email" required maxlength="255" value="<?= e($vals['sender_email']) ?>">
            </div>
        </div>
        <label>Subject *</label>
        <input name="subject" required maxlength="255" value="<?= e($vals['subject']) ?>">
        <label>Message *</label>
        <textarea name="message" rows="7" required minlength="10" maxlength="5000"><?= e($vals['message']) ?></textarea>
        <button class="btn btn-primary btn-block">Send message →</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
