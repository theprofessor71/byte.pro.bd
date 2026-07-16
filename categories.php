<?php
/**
 * All categories overview.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$cats = db()->query("SELECT category, COUNT(*) c, COALESCE(SUM(views),0) v
    FROM posts WHERE status='published' GROUP BY category ORDER BY c DESC")->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading grad-text">Categories</h1>
    <p class="muted">Browse posts by topic</p>
</div>
<div class="container">
    <div class="cat-grid cat-grid-lg">
        <?php foreach ($cats as $c): ?>
        <a class="cat-card glass" href="/category/<?= e(rawurlencode($c['category'])) ?>" data-aos="fade-up">
            <span class="cat-icon mono">&gt;_</span>
            <span class="cat-name"><?= e($c['category']) ?></span>
            <span class="cat-count"><?= (int)$c['c'] ?> posts · <?= number_format((int)$c['v']) ?> views</span>
        </a>
        <?php endforeach; if (!$cats): ?><p class="muted">No categories yet.</p><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
