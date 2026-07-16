<?php
/**
 * Posts filtered by category.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$cat = str_param($_GET, 'cat');
if ($cat === '' || mb_strlen($cat) > 100) {
    header('Location: /categories.php');
    exit;
}

$cnt = $pdo->prepare("SELECT COUNT(*) c FROM posts WHERE status='published' AND category = ?");
$cnt->execute([$cat]);
$total = (int)$cnt->fetch()['c'];
$pg = paginate($total, POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, tags, thumbnail, views, reading_time, created_at
                       FROM posts WHERE status='published' AND category = ?
                       ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $cat);
$stmt->bindValue(2, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(3, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$pageTitle = 'Category: ' . $cat;
$pageDesc = "Posts in category \"$cat\" — " . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading"><span class="muted">Category /</span> <span class="grad-text"><?= e($cat) ?></span></h1>
    <p class="muted"><?= $total ?> post<?= $total === 1 ? '' : 's' ?></p>
</div>
<div class="container">
    <div class="post-grid grid-3">
        <?php foreach ($posts as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
        <?php if (!$posts): ?><p class="muted">Nothing in this category (yet).</p><?php endif; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>" href="?cat=<?= e(rawurlencode($cat)) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
