<?php
/**
 * Comment upvote API — POST {comment_id}, CSRF-protected, one per IP-hash
 * (toggle). Same privacy model as reactions: raw IP never stored.
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
if (actionCount('comment_voted', RATE_WINDOW_MIN) >= 60) {
    jsonOut(['error' => 'Too many votes — slow down.'], 429);
}

$commentId = (int)(is_scalar($_POST['comment_id'] ?? 0) ? ($_POST['comment_id'] ?? 0) : 0);
if ($commentId < 1) {
    jsonOut(['error' => 'Invalid input'], 422);
}

$pdo = db();
// Comment must be approved and belong to a published post
$chk = $pdo->prepare("SELECT c.id FROM comments c
    JOIN posts p ON p.id = c.post_id
    WHERE c.id = ? AND c.status = 1 AND p.status = 'published'");
$chk->execute([$commentId]);
if (!$chk->fetch()) {
    jsonOut(['error' => 'Comment not found'], 404);
}

// Toggle: insert, or remove if already voted (PK catches the duplicate)
try {
    $pdo->prepare('INSERT INTO comment_votes (comment_id, ip_hash) VALUES (?, ?)')
        ->execute([$commentId, ipHash()]);
    $toggled = 'on';
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $pdo->prepare('DELETE FROM comment_votes WHERE comment_id = ? AND ip_hash = ?')
            ->execute([$commentId, ipHash()]);
        $toggled = 'off';
    } else {
        error_log('comment vote failed: ' . $e->getMessage());
        jsonOut(['error' => 'Server error'], 500);
    }
}

$cnt = $pdo->prepare('SELECT COUNT(*) c FROM comment_votes WHERE comment_id = ?');
$cnt->execute([$commentId]);
logSecurity('comment_voted', "Comment #$commentId $toggled");
jsonOut(['ok' => true, 'toggled' => $toggled, 'votes' => (int)$cnt->fetch()['c']]);
