<?php
/**
 * Shared helpers: output escaping, slugs, pagination, misc.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

/** THE output-escaping function. Every dynamic value printed into HTML goes through e(). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Safe scalar from $_GET/$_POST — ?q[]=x sends an array, which would fatal in trim(). */
function str_param(array $src, string $key, string $default = ''): string
{
    $v = $src[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'post-' . bin2hex(random_bytes(4));
    }
    return substr($text, 0, 200);
}

function readingTime(string $content): int
{
    return max(1, (int)ceil(str_word_count(strip_tags($content)) / 200));
}

function excerptOf(string $content, int $len = 180): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
    return mb_strlen($plain) > $len ? mb_substr($plain, 0, $len) . '…' : $plain;
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function parseTags(?string $tags): array
{
    if (!$tags) return [];
    return array_values(array_filter(array_map(
        fn($t) => trim($t),
        explode(',', $tags)
    ), fn($t) => $t !== ''));
}

/** Simple honeypot check — bots fill the hidden "website" field. */
function honeypotTripped(): bool
{
    return trim($_POST['website'] ?? '') !== '';
}

function jsonOut(array $data, int $code = 200): void // not ": never" — PHP 8.0 compat
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function paginate(int $total, int $perPage, int $current): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $current = max(1, min($current, $pages));
    return [
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
        'perPage' => $perPage,
    ];
}

/** Record one view: total counter + daily row for the dashboard chart. */
function recordView(int $postId): void
{
    try {
        $pdo = db();
        $pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([$postId]);
        $pdo->prepare('INSERT INTO post_views_daily (post_id, view_date, views)
                       VALUES (?, CURDATE(), 1)
                       ON DUPLICATE KEY UPDATE views = views + 1')->execute([$postId]);
    } catch (PDOException $e) {
        error_log('view tracking failed: ' . $e->getMessage());
    }
}
