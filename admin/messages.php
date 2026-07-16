<?php
/**
 * Contact messages inbox — read/unread, delete.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id > 0 && $act === 'read') {
        $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    } elseif ($id > 0 && $act === 'unread') {
        $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?')->execute([$id]);
    } elseif ($id > 0 && $act === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        logSecurity('message_deleted', "Message #$id by " . currentAdminName());
    }
    header('Location: /admin/messages.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC LIMIT 200')->fetchAll();

$pageTitle = 'Messages';
require __DIR__ . '/_header.php';
?>
<div class="message-list">
<?php foreach ($rows as $m): ?>
    <details class="panel msg-item <?= $m['is_read'] ? '' : 'msg-unread' ?>">
        <summary>
            <?php if (!$m['is_read']): ?><span class="dot-unread"></span><?php endif; ?>
            <strong><?= e($m['subject']) ?></strong>
            <span class="muted">— <?= e($m['sender_name']) ?> &lt;<?= e($m['sender_email']) ?>&gt;</span>
            <span class="muted right"><?= e(timeAgo($m['created_at'])) ?></span>
        </summary>
        <p class="msg-body"><?= nl2br(e($m['message'])) ?></p>
        <p class="muted">IP: <code><?= e($m['ip_address']) ?></code> · <?= e($m['created_at']) ?></p>
        <form method="post" class="inline"><?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <?php if (!$m['is_read']): ?>
            <button name="action" value="read" class="btn btn-sm btn-primary">✓ Mark read</button>
            <?php else: ?>
            <button name="action" value="unread" class="btn btn-sm btn-ghost">↺ Mark unread</button>
            <?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="mailto:<?= e($m['sender_email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>">✉ Reply by email</a>
            <button name="action" value="delete" class="btn btn-sm btn-danger"
                data-confirm="Delete this message?">🗑 Delete</button>
        </form>
    </details>
<?php endforeach; if (!$rows): ?>
    <div class="panel muted">Inbox empty.</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
