<?php
/**
 * Author page — bio line + all published posts by one admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$aid = (int)(is_scalar($_GET['id'] ?? 0) ? ($_GET['id'] ?? 0) : 0);
if ($aid < 1) {
    header('Location: /blog.php');
    exit;
}

$au = $pdo->prepare('SELECT id, username, created_at FROM admins WHERE id = ?');
$au->execute([$aid]);
$author = $au->fetch();
if (!$author) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$cnt = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(views),0) v FROM posts WHERE status='published' AND author_id = ?");
$cnt->execute([$aid]);
$stats = $cnt->fetch();
$total = (int)$stats['c'];
$pg = paginate($total, POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, tags, thumbnail, views, reading_time, created_at
                       FROM posts WHERE status='published' AND author_id = ?
                       ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $aid, PDO::PARAM_INT);
$stmt->bindValue(2, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(3, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$pageTitle = 'Author: ' . $author['username'];
$pageDesc = 'Posts written by ' . $author['username'] . ' — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <div class="author-hero glass">
        <span class="avatar avatar-lg mono"><?= e(strtoupper(mb_substr($author['username'], 0, 1))) ?></span>
        <div>
            <h1 class="page-heading grad-text"><?= e($author['username']) ?></h1>
            <p class="muted mono">
                <?= $total ?> post<?= $total === 1 ? '' : 's' ?> ·
                <?= number_format((int)$stats['v']) ?> total views ·
                writing since <?= e(date('M Y', strtotime($author['created_at']))) ?>
            </p>
        </div>
    </div>
</div>
<div class="container">
    <div class="post-grid grid-3">
        <?php foreach ($posts as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
        <?php if (!$posts): ?><p class="muted">No published posts yet.</p><?php endif; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>" href="?id=<?= $aid ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
