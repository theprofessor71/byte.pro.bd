<?php
/**
 * Blog listing with pagination + sidebar (categories, tags, popular).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$total = (int)$pdo->query("SELECT COUNT(*) c FROM posts WHERE status='published'")->fetch()['c'];
$pg = paginate($total, POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, tags, thumbnail, views, reading_time, created_at
                       FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(2, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$cats = $pdo->query("SELECT category, COUNT(*) c FROM posts WHERE status='published' GROUP BY category ORDER BY c DESC")->fetchAll();
$popular = $pdo->query("SELECT title, slug, views FROM posts WHERE status='published' ORDER BY views DESC LIMIT 5")->fetchAll();
$allTags = [];
foreach ($pdo->query("SELECT tags FROM posts WHERE status='published'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    foreach (parseTags($t) as $tag) {
        $allTags[$tag] = ($allTags[$tag] ?? 0) + 1;
    }
}
arsort($allTags);
$allTags = array_slice($allTags, 0, 20, true);

$pageTitle = 'Blog';
$pageDesc = 'All posts — ' . SITE_DESC;
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading grad-text">Blog</h1>
    <p class="muted"><?= $total ?> post<?= $total === 1 ? '' : 's' ?> and counting</p>
</div>

<div class="container layout-sidebar">
    <div class="layout-main">
        <div class="post-grid grid-2">
            <?php foreach ($posts as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
            <?php if (!$posts): ?><p class="muted">No posts yet.</p><?php endif; ?>
        </div>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="pagination">
            <?php if ($pg['current'] > 1): ?><a class="page-link" href="?page=<?= $pg['current'] - 1 ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
            <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['current'] < $pg['pages']): ?><a class="page-link" href="?page=<?= $pg['current'] + 1 ?>">→</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>

    <aside class="layout-aside">
        <div class="widget glass">
            <h3>🗂 Categories</h3>
            <ul class="widget-list">
                <?php foreach ($cats as $c): ?>
                <li><a href="/category/<?= e(rawurlencode($c['category'])) ?>"><?= e($c['category']) ?> <span class="count">(<?= (int)$c['c'] ?>)</span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="widget glass">
            <h3>🔥 Popular</h3>
            <ul class="widget-list">
                <?php foreach ($popular as $p2): ?>
                <li><a href="/post/<?= e($p2['slug']) ?>"><?= e($p2['title']) ?></a> <span class="count">👁 <?= number_format((int)$p2['views']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($allTags): ?>
        <div class="widget glass">
            <h3>🏷 Tags</h3>
            <div class="tag-cloud">
                <?php foreach ($allTags as $tag => $n): ?>
                <a class="chip" href="/tag/<?= e(rawurlencode($tag)) ?>" style="font-size:<?= min(1.2, 0.8 + $n * 0.06) ?>rem">#<?= e($tag) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
