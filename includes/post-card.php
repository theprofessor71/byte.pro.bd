<?php
/**
 * Post card partial — expects $p (post row). Used by index/blog/category/tag/search.
 */
if (!isset($p)) { return; }
?>
<article class="post-card glass" data-aos="fade-up">
    <a class="card-thumb" href="/post/<?= e($p['slug']) ?>">
        <?php if (!empty($p['thumbnail'])): ?>
        <img src="<?= e($p['thumbnail']) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <?php else: ?>
        <div class="thumb-fallback"><span>&gt;_</span></div>
        <?php endif; ?>
    </a>
    <div class="card-body">
        <div class="card-meta">
            <a class="chip" href="/category/<?= e(rawurlencode($p['category'])) ?>"><?= e($p['category']) ?></a>
            <span class="muted">⏱ <?= (int)$p['reading_time'] ?> min</span>
            <span class="muted">👁 <?= number_format((int)$p['views']) ?></span>
        </div>
        <h3 class="card-title"><a href="/post/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></h3>
        <p class="card-excerpt"><?= e($p['excerpt'] ?? '') ?></p>
        <div class="card-footer">
            <time datetime="<?= e(date('c', strtotime($p['created_at']))) ?>"><?= e(date('M j, Y', strtotime($p['created_at']))) ?></time>
            <a class="read-more" href="/post/<?= e($p['slug']) ?>">Read →</a>
        </div>
    </div>
</article>
