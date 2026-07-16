<?php
/**
 * 404 — matrix rain + "did you mean" suggestions based on the requested path.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
http_response_code(404);

/* Suggest similar posts: compare the last URL segment against slugs */
$suggestions = [];
try {
    $reqPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    $needle = strtolower(preg_replace('/[^a-z0-9-]/', '', basename($reqPath)));
    if (strlen($needle) >= 3) {
        $all = db()->query("SELECT title, slug FROM posts WHERE status='published' LIMIT 500")->fetchAll();
        foreach ($all as $p) {
            similar_text($needle, $p['slug'], $pct);
            if ($pct >= 55 || str_contains($p['slug'], $needle)) {
                $suggestions[] = ['title' => $p['title'], 'slug' => $p['slug'], 'score' => $pct];
            }
        }
        usort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);
        $suggestions = array_slice($suggestions, 0, 3);
    }
} catch (Throwable $e) { /* suggestions are best-effort only */ }

$pageTitle = '404 — Not Found';
$bodyClass = 'page-404';
require __DIR__ . '/includes/header.php';
?>
<section class="err-hero">
    <canvas id="matrixRain" aria-hidden="true"></canvas>
    <div class="err-content">
        <h1 class="err-code glitch" data-text="404">404</h1>
        <p class="mono">&gt; target not found. connection reset by peer_</p>
        <?php if ($suggestions): ?>
        <div class="err-suggest glass">
            <p class="mono muted">$ grep -ri "<?= e(substr($needle, 0, 30)) ?>" posts/ → did you mean:</p>
            <ul>
                <?php foreach ($suggestions as $s): ?>
                <li><a href="/post/<?= e($s['slug']) ?>"><?= e($s['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="hero-cta">
            <a class="btn btn-primary" href="/">← Back to base</a>
            <a class="btn btn-ghost" href="/blog.php">Browse posts</a>
            <a class="btn btn-ghost" href="/search.php">🔍 Search</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
