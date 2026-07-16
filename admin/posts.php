<?php
/**
 * Post management — list, publish/unpublish, delete.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($id > 0 && $act === 'delete') {
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        logSecurity('post_deleted', "Post #$id by " . currentAdminName());
    } elseif ($id > 0 && in_array($act, ['publish', 'unpublish'], true)) {
        $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?")
            ->execute([$act === 'publish' ? 'published' : 'draft', $id]);
        logSecurity('post_status', "Post #$id → $act by " . currentAdminName());
    } elseif ($id > 0 && $act === 'duplicate') {
        // Copy as a fresh draft: "Title (copy)", unique slug, zeroed stats
        $src = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
        $src->execute([$id]);
        if ($p = $src->fetch()) {
            $newSlug = substr($p['slug'], 0, 180) . '-copy-' . bin2hex(random_bytes(3));
            $pdo->prepare("INSERT INTO posts (title, slug, content, excerpt, category, tags,
                           thumbnail, status, reading_time, series, series_part, difficulty, lang,
                           preview_token, author_id)
                           VALUES (?,?,?,?,?,?,?,'draft',?,?,?,?,?,?,?)")
                ->execute([
                    $p['title'] . ' (copy)', $newSlug, $p['content'], $p['excerpt'],
                    $p['category'], $p['tags'], $p['thumbnail'], $p['reading_time'],
                    $p['series'], $p['series_part'], $p['difficulty'], $p['lang'],
                    bin2hex(random_bytes(16)), currentAdminId(),
                ]);
            $newId = (int)$pdo->lastInsertId();
            logSecurity('post_duplicated', "Post #$id → draft #$newId by " . currentAdminName());
            header('Location: /admin/post-editor.php?id=' . $newId);
            exit;
        }
    }
    header('Location: /admin/posts.php');
    exit;
}

$total = (int)$pdo->query('SELECT COUNT(*) c FROM posts')->fetch()['c'];
$pg = paginate($total, 20, (int)($_GET['page'] ?? 1));
$stmt = $pdo->prepare('SELECT id, title, slug, category, status, views, created_at
                       FROM posts ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(2, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle = 'Posts';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <a class="btn btn-primary" href="/admin/post-editor.php">✏️ New Post</a>
    <span class="muted"><?= $total ?> total</span>
</div>
<div class="panel">
<table class="data-table">
    <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><a href="/admin/post-editor.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
        <td><?= e($r['category']) ?></td>
        <td><span class="badge badge-<?= $r['status'] === 'published' ? 'green' : 'gray' ?>"><?= e($r['status']) ?></span></td>
        <td><?= number_format((int)$r['views']) ?></td>
        <td class="nowrap"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
        <td class="nowrap">
            <form method="post" class="inline"><?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <?php if ($r['status'] === 'published'): ?>
                <button name="action" value="unpublish" class="btn btn-sm btn-ghost">Unpublish</button>
                <?php else: ?>
                <button name="action" value="publish" class="btn btn-sm btn-primary">Publish</button>
                <?php endif; ?>
                <button name="action" value="duplicate" class="btn btn-sm btn-ghost" title="Copy as new draft">⧉</button>
                <button name="action" value="delete" class="btn btn-sm btn-danger"
                    data-confirm="Delete this post permanently? Comments go with it.">Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="6" class="muted">No posts yet — write the first one!</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php if ($pg['pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
    <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
