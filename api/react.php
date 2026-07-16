<?php
/**
 * Reaction API — POST {post_id, reaction}, CSRF-protected, one per IP-hash.
 * Called by comments.js. Returns updated counts.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

sendSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['error' => 'POST only'], 405);
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    jsonOut(['error' => 'Bad token'], 403);
}
// Cheap flood guard — the unique key dedups state, but each toggle costs
// 3 queries; don't let a loop hammer the free-tier DB
if (actionCount('reaction_toggled', RATE_WINDOW_MIN) >= 60) {
    jsonOut(['error' => 'Too many reactions — slow down.'], 429);
}

$postId = (int)(is_scalar($_POST['post_id'] ?? 0) ? ($_POST['post_id'] ?? 0) : 0);
$reaction = is_string($_POST['reaction'] ?? '') ? $_POST['reaction'] : '';
$allowed = ['like', 'love', 'rocket', 'fire', 'star'];

if ($postId < 1 || !in_array($reaction, $allowed, true)) {
    jsonOut(['error' => 'Invalid input'], 422);
}

$pdo = db();
$chk = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
$chk->execute([$postId]);
if (!$chk->fetch()) {
    jsonOut(['error' => 'Post not found'], 404);
}

// Toggle: insert, or remove if it already exists (unique key catches dup)
try {
    $pdo->prepare('INSERT INTO post_reactions (post_id, reaction, ip_hash) VALUES (?,?,?)')
        ->execute([$postId, $reaction, ipHash()]);
    $toggled = 'on';
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // duplicate → toggle off
        $pdo->prepare('DELETE FROM post_reactions WHERE post_id = ? AND reaction = ? AND ip_hash = ?')
            ->execute([$postId, $reaction, ipHash()]);
        $toggled = 'off';
    } else {
        error_log('reaction insert failed: ' . $e->getMessage());
        jsonOut(['error' => 'Server error'], 500);
    }
}

$counts = $pdo->prepare('SELECT reaction, COUNT(*) c FROM post_reactions WHERE post_id = ? GROUP BY reaction');
$counts->execute([$postId]);
logSecurity('reaction_toggled', "Post #$postId $reaction $toggled");
jsonOut(['ok' => true, 'toggled' => $toggled, 'counts' => array_column($counts->fetchAll(), 'c', 'reaction')]);
