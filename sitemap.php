<?php
/**
 * Dynamic XML sitemap.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = db();
$posts = $pdo->query("SELECT slug, updated_at FROM posts WHERE status='published' ORDER BY updated_at DESC")->fetchAll();
$cats = $pdo->query("SELECT DISTINCT category FROM posts WHERE status='published'")->fetchAll(PDO::FETCH_COLUMN);

$x = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
$url = function (string $loc, string $mod = '', string $freq = 'weekly', string $pri = '0.6') use ($x) {
    echo "  <url>\n    <loc>" . $x($loc) . "</loc>\n";
    if ($mod) echo "    <lastmod>" . $x(date('Y-m-d', strtotime($mod))) . "</lastmod>\n";
    echo "    <changefreq>$freq</changefreq>\n    <priority>$pri</priority>\n  </url>\n";
};

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$url(SITE_URL . '/', '', 'daily', '1.0');
$url(SITE_URL . '/blog.php', '', 'daily', '0.9');
$url(SITE_URL . '/categories.php', '', 'weekly', '0.5');
$url(SITE_URL . '/about.php', '', 'monthly', '0.4');
$url(SITE_URL . '/contact.php', '', 'monthly', '0.4');
foreach ($posts as $p) {
    $url(SITE_URL . '/post/' . $p['slug'], $p['updated_at'], 'monthly', '0.8');
}
foreach ($cats as $c) {
    $url(SITE_URL . '/category/' . rawurlencode($c), '', 'weekly', '0.5');
}
echo '</urlset>';
