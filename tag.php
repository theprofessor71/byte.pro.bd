<?php
/**
 * Posts filtered by tag (comma-separated tags column, matched safely with
 * FIND_IN_SET on a normalized string).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$tag = str_param($_GET, 'tag');
if ($tag === '' || mb_strlen($tag) > 100) {
    header('Location: /blog.php');
    exit;
}

// Normalize "a, b , c" → "a,b,c" then FIND_IN_SET — parameterized, no injection surface
$where = "status='published' AND FIND_IN_SET(?, REPLACE(tags, ', ', ','))";
$cnt = $pdo->prepare("SELECT COUNT(*) c FROM posts WHERE $where");
$cnt->execute([$tag]);
$total = (int)$cnt->fetch()['c'];
$pg = paginate($total, POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, tags, thumbnail, views, reading_time, created_at
                       FROM posts WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $tag);
$stmt->bindValue(2, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(3, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$pageTitle = 'Tag: #' . $tag;
$pageDesc = "Posts tagged #$tag — " . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading"><span class="muted">Tag /</span> <span class="grad-text">#<?= e($tag) ?></span></h1>
    <p class="muted"><?= $total ?> post<?= $total === 1 ? '' : 's' ?></p>
</div>
<div class="container">
    <div class="post-grid grid-3">
        <?php foreach ($posts as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
        <?php if (!$posts): ?><p class="muted">No posts with this tag.</p><?php endif; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>" href="?tag=<?= e(rawurlencode($tag)) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
