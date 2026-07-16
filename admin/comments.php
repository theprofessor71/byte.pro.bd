<?php
/**
 * Comment moderation queue.
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
    if ($id > 0 && $act === 'approve') {
        $pdo->prepare('UPDATE comments SET status = 1 WHERE id = ?')->execute([$id]);
        logSecurity('comment_approved', "Comment #$id by " . currentAdminName());
    } elseif ($id > 0 && $act === 'delete') {
        $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        logSecurity('comment_deleted', "Comment #$id by " . currentAdminName());
    }
    header('Location: /admin/comments.php?filter=' . urlencode($_POST['filter'] ?? 'pending'));
    exit;
}

$filter = $_GET['filter'] ?? 'pending';
$where = match ($filter) {
    'approved' => 'c.status = 1',
    'all'      => '1=1',
    default    => 'c.status = 0',
};
$rows = $pdo->query("SELECT c.*, p.title AS post_title, p.slug AS post_slug
    FROM comments c JOIN posts p ON p.id = c.post_id
    WHERE $where ORDER BY c.created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'Comments';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'all' => 'All'] as $k => $label): ?>
    <a class="btn <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?>" href="?filter=<?= $k ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>
<div class="comment-list">
<?php foreach ($rows as $c): ?>
    <div class="panel comment-item">
        <div class="comment-meta">
            <strong><?= e($c['author_name']) ?></strong>
            <?php if ($c['author_email']): ?><span class="muted">&lt;<?= e($c['author_email']) ?>&gt;</span><?php endif; ?>
            <span class="badge badge-<?= $c['status'] ? 'green' : 'gray' ?>"><?= $c['status'] ? 'approved' : 'pending' ?></span>
            <span class="muted">on <a href="/post/<?= e($c['post_slug']) ?>" target="_blank"><?= e($c['post_title']) ?></a></span>
            <span class="muted"><?= e(timeAgo($c['created_at'])) ?> · IP <code><?= e($c['ip_address']) ?></code></span>
        </div>
        <p class="comment-body"><?= nl2br(e($c['content'])) ?></p>
        <form method="post" class="inline"><?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <?php if (!$c['status']): ?>
            <button name="action" value="approve" class="btn btn-sm btn-primary">✓ Approve</button>
            <?php endif; ?>
            <button name="action" value="delete" class="btn btn-sm btn-danger"
                data-confirm="Delete this comment?">🗑 Delete</button>
        </form>
    </div>
<?php endforeach; if (!$rows): ?>
    <div class="panel muted">Nothing here. 🎉</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
