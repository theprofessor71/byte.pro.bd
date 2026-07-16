<?php
/**
 * Search — MySQL FULLTEXT (natural language mode), HTML page + JSON API
 * for the Ctrl+K modal (?format=json). Optional category filter (?cat=).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$q = str_param($_GET, 'q');
$fCat = str_param($_GET, 'cat');
$isJson = ($_GET['format'] ?? '') === 'json';
$results = [];
$total = 0;

$allCats = $pdo->query("SELECT DISTINCT category FROM posts WHERE status='published' ORDER BY category")
    ->fetchAll(PDO::FETCH_COLUMN);
if ($fCat !== '' && !in_array($fCat, $allCats, true)) {
    $fCat = ''; // unknown category → ignore filter
}

if ($q !== '' && mb_strlen($q) >= 2 && mb_strlen($q) <= 100) {
    $catSql = $fCat !== '' ? ' AND category = ?' : '';
    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, views, reading_time, thumbnail, created_at,
            MATCH(title, content, tags) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
        FROM posts
        WHERE status = 'published' AND MATCH(title, content, tags) AGAINST (? IN NATURAL LANGUAGE MODE)$catSql
        ORDER BY score DESC LIMIT 20");
    $stmt->execute($fCat !== '' ? [$q, $q, $fCat] : [$q, $q]);
    $results = $stmt->fetchAll();

    // Fallback: short/partial words don't hit FULLTEXT — try LIKE
    if (!$results) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
        $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, category, views, reading_time, thumbnail, created_at
            FROM posts WHERE status='published' AND (title LIKE ? OR tags LIKE ?)$catSql
            ORDER BY created_at DESC LIMIT 20");
        $stmt->execute($fCat !== '' ? [$like, $like, $fCat] : [$like, $like]);
        $results = $stmt->fetchAll();
    }
    $total = count($results);
}

if ($isJson) {
    sendSecurityHeaders(); // JSON path exits before header.php would send them
    jsonOut([
        'query'   => $q,
        'total'   => $total,
        'results' => array_map(fn($r) => [
            'title'    => $r['title'],
            'url'      => '/post/' . $r['slug'],
            'excerpt'  => mb_substr($r['excerpt'] ?? '', 0, 120),
            'category' => $r['category'],
        ], $results),
    ]);
}

$pageTitle = $q !== '' ? "Search: $q" : 'Search';
require __DIR__ . '/includes/header.php';
?>
<div class="container page-head">
    <h1 class="page-heading grad-text">Search</h1>
    <form method="get" action="/search.php" class="search-page-form">
        <input name="q" value="<?= e($q) ?>" placeholder="Search posts, tags, topics…" maxlength="100" autofocus>
        <select name="cat" class="search-cat-filter" title="Filter by category">
            <option value="">All categories</option>
            <?php foreach ($allCats as $c): ?>
            <option value="<?= e($c) ?>" <?= $fCat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Search</button>
    </form>
    <?php if ($q !== ''): ?><p class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($q) ?>”<?= $fCat !== '' ? ' in ' . e($fCat) : '' ?></p><?php endif; ?>
</div>
<div class="container">
    <div class="post-grid grid-3">
        <?php foreach ($results as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
        <?php if ($q !== '' && !$results): ?><p class="muted">No results. Try different keywords<?= $fCat !== '' ? ' or clear the category filter' : '' ?>.</p><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
