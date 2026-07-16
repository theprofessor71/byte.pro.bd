<?php
/**
 * RSS 2.0 feed of published posts.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$posts = db()->query("SELECT title, slug, excerpt, category, created_at
    FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT 20")->fetchAll();

$x = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?= $x(SITE_NAME) ?></title>
    <link><?= $x(SITE_URL) ?></link>
    <description><?= $x(SITE_DESC) ?></description>
    <language>en</language>
    <lastBuildDate><?= $x(date(DATE_RSS, $posts ? strtotime($posts[0]['created_at']) : time())) ?></lastBuildDate>
    <atom:link href="<?= $x(SITE_URL) ?>/feed.php" rel="self" type="application/rss+xml"/>
<?php foreach ($posts as $p): ?>
    <item>
        <title><?= $x($p['title']) ?></title>
        <link><?= $x(SITE_URL . '/post/' . $p['slug']) ?></link>
        <guid isPermaLink="true"><?= $x(SITE_URL . '/post/' . $p['slug']) ?></guid>
        <category><?= $x($p['category']) ?></category>
        <description><?= $x($p['excerpt'] ?? '') ?></description>
        <pubDate><?= $x(date(DATE_RSS, strtotime($p['created_at']))) ?></pubDate>
    </item>
<?php endforeach; ?>
</channel>
</rss>
