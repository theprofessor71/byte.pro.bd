<?php
/**
 * Markdown post editor with live preview (client-side render for preview,
 * server-side renderMarkdown() for the real page).
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/markdown.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$post = ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'excerpt' => '',
         'category' => 'General', 'tags' => '', 'thumbnail' => '', 'status' => 'draft',
         'series' => '', 'series_part' => 0, 'difficulty' => '', 'lang' => 'en', 'preview_token' => ''];
$saved = false;
$error = '';

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    if ($found = $stmt->fetch()) {
        $post = $found;
    } else {
        header('Location: /admin/posts.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'restore') {
    requireCsrf();
    $title    = trim($_POST['title'] ?? '');
    $content  = $_POST['content'] ?? '';
    $category = trim($_POST['category'] ?? 'General') ?: 'General';
    $tags     = trim($_POST['tags'] ?? '');
    $thumb    = trim($_POST['thumbnail'] ?? '');
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $slugIn   = trim($_POST['slug'] ?? '');
    $series   = trim($_POST['series'] ?? '');
    $seriesPart = max(0, (int)($_POST['series_part'] ?? 0));
    $difficulty = in_array($_POST['difficulty'] ?? '', ['easy', 'medium', 'hard', 'insane'], true)
        ? $_POST['difficulty'] : '';
    $lang     = in_array($_POST['lang'] ?? 'en', ['en', 'bn'], true) ? $_POST['lang'] : 'en';

    if ($title === '' || trim($content) === '') {
        $error = 'Title and content are required.';
    } elseif ($thumb !== '' && !preg_match('#^(https://|/assets/)#', $thumb)) {
        $error = 'Thumbnail must be an https:// URL or /assets/ path.';
    } else {
        $slug = slugify($slugIn !== '' ? $slugIn : $title);
        // Ensure slug unique (excluding self)
        $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id]);
        if ($chk->fetch()) {
            $slug .= '-' . bin2hex(random_bytes(3));
        }
        $excerpt = trim($_POST['excerpt'] ?? '') ?: excerptOf($content);
        $rt = readingTime($content);

        if ($id > 0) {
            // Save the outgoing version first — restore anytime from history
            $pdo->prepare('INSERT INTO post_revisions (post_id, title, content, excerpt)
                           SELECT id, title, content, excerpt FROM posts WHERE id = ?')
                ->execute([$id]);
            // Keep only the 10 newest revisions per post (free-tier disk guard)
            $pdo->prepare('DELETE FROM post_revisions WHERE post_id = ? AND id NOT IN (
                             SELECT id FROM (SELECT id FROM post_revisions WHERE post_id = ?
                                             ORDER BY id DESC LIMIT 10) keep)')
                ->execute([$id, $id]);
            $pdo->prepare('UPDATE posts SET title=?, slug=?, content=?, excerpt=?, category=?,
                           tags=?, thumbnail=?, status=?, reading_time=?,
                           series=?, series_part=?, difficulty=?, lang=? WHERE id=?')
                ->execute([$title, $slug, $content, $excerpt, $category, $tags, $thumb, $status, $rt,
                           $series, $seriesPart, $difficulty, $lang, $id]);
            logSecurity('post_updated', "Post #$id \"$title\" by " . currentAdminName());
            // Older rows may predate preview links — mint a token on first save
            $pdo->prepare("UPDATE posts SET preview_token = ? WHERE id = ? AND preview_token = ''")
                ->execute([bin2hex(random_bytes(16)), $id]);
        } else {
            $pdo->prepare('INSERT INTO posts (title, slug, content, excerpt, category, tags,
                           thumbnail, status, reading_time, series, series_part, difficulty, lang,
                           preview_token, author_id)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$title, $slug, $content, $excerpt, $category, $tags, $thumb, $status, $rt,
                           $series, $seriesPart, $difficulty, $lang,
                           bin2hex(random_bytes(16)), currentAdminId()]);
            $id = (int)$pdo->lastInsertId();
            logSecurity('post_created', "Post #$id \"$title\" by " . currentAdminName());
        }
        $saved = true;
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch();
    }
    if ($error) {
        $post = array_merge($post, [
            'title' => $title, 'content' => $content, 'category' => $category,
            'tags' => $tags, 'thumbnail' => $thumb, 'status' => $status, 'slug' => $slugIn,
            'excerpt' => trim($_POST['excerpt'] ?? ''),
            'series' => $series, 'series_part' => $seriesPart,
            'difficulty' => $difficulty, 'lang' => $lang,
        ]);
    }
}

/* Restore a revision (POST + CSRF) — current version is snapshotted first */
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    requireCsrf();
    $revId = (int)($_POST['revision_id'] ?? 0);
    $rev = $pdo->prepare('SELECT * FROM post_revisions WHERE id = ? AND post_id = ?');
    $rev->execute([$revId, $id]);
    if ($rev = $rev->fetch()) {
        $pdo->prepare('INSERT INTO post_revisions (post_id, title, content, excerpt)
                       SELECT id, title, content, excerpt FROM posts WHERE id = ?')->execute([$id]);
        $pdo->prepare('UPDATE posts SET title = ?, content = ?, excerpt = ? WHERE id = ?')
            ->execute([$rev['title'], $rev['content'], $rev['excerpt'], $id]);
        logSecurity('post_restored', "Post #$id restored to revision #$revId by " . currentAdminName());
        header('Location: /admin/post-editor.php?id=' . $id . '&restored=1');
        exit;
    }
}
$saved = $saved || isset($_GET['restored']);

$revisions = [];
if ($id > 0) {
    $rv = $pdo->prepare('SELECT id, title, saved_at FROM post_revisions WHERE post_id = ? ORDER BY id DESC LIMIT 10');
    $rv->execute([$id]);
    $revisions = $rv->fetchAll();
}

$categories = $pdo->query("SELECT DISTINCT category FROM posts ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $id ? 'Edit Post' : 'New Post';
$adminScripts = ['/assets/js/admin-editor.js'];
require __DIR__ . '/_header.php';
?>
<?php if ($saved): ?><div class="alert alert-ok">✅ Saved. <a href="/post/<?= e($post['slug']) ?>" target="_blank">View post →</a></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="editor-form">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
    <div class="editor-grid">
        <div class="editor-main">
            <input class="input-title" name="title" placeholder="Post title…" required maxlength="255" value="<?= e($post['title']) ?>">
            <div class="editor-toolbar" id="mdToolbar">
                <button type="button" data-md="bold" title="Bold">B</button>
                <button type="button" data-md="italic" title="Italic"><em>I</em></button>
                <button type="button" data-md="h2" title="Heading">H2</button>
                <button type="button" data-md="link" title="Link">🔗</button>
                <button type="button" data-md="image" title="Image">🖼</button>
                <button type="button" data-md="code" title="Code block">&lt;/&gt;</button>
                <button type="button" data-md="quote" title="Quote">❝</button>
                <button type="button" data-md="list" title="List">•</button>
                <button type="button" id="togglePreview" class="right">👁 Preview</button>
            </div>
            <textarea id="mdContent" name="content" class="editor-textarea" placeholder="Write in Markdown…" required><?= e($post['content']) ?></textarea>
            <div id="mdPreview" class="md-preview post-content" hidden></div>
        </div>
        <aside class="editor-side">
            <div class="panel">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
                <label>Slug</label>
                <input name="slug" value="<?= e($post['slug']) ?>" placeholder="auto from title">
                <label>Category</label>
                <input name="category" list="catList" value="<?= e($post['category']) ?>">
                <datalist id="catList">
                    <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                </datalist>
                <label>Tags (comma separated)</label>
                <input name="tags" value="<?= e($post['tags']) ?>" placeholder="nmap, recon, ctf">
                <label>Thumbnail URL</label>
                <input name="thumbnail" value="<?= e($post['thumbnail']) ?>" placeholder="https://… or /assets/images/…">
                <label>Excerpt (optional)</label>
                <textarea name="excerpt" rows="3"><?= e($post['excerpt'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-primary btn-block">💾 Save</button>
            </div>
            <div class="panel">
                <h3>📚 Series (optional)</h3>
                <label>Series name</label>
                <input name="series" value="<?= e($post['series'] ?? '') ?>" placeholder="HTB Walkthroughs">
                <label>Part number</label>
                <input name="series_part" type="number" min="0" max="99" value="<?= (int)($post['series_part'] ?? 0) ?>">
                <label>Difficulty (CTF badge)</label>
                <select name="difficulty">
                    <?php foreach (['' => '— none —', 'easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'insane' => 'Insane'] as $dv => $dl): ?>
                    <option value="<?= e($dv) ?>" <?= ($post['difficulty'] ?? '') === $dv ? 'selected' : '' ?>><?= e($dl) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Language</label>
                <select name="lang">
                    <option value="en" <?= ($post['lang'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="bn" <?= ($post['lang'] ?? '') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
                </select>
            </div>
            <?php if ($post['id'] && $post['status'] === 'draft' && !empty($post['preview_token'])): ?>
            <div class="panel">
                <h3>👁 Draft preview link</h3>
                <p class="muted">Anyone with this link can view the draft:</p>
                <input readonly value="<?= e(SITE_URL . '/post/' . $post['slug'] . '?preview=' . $post['preview_token']) ?>" data-copy-on-click>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</form>
<?php if ($revisions): ?>
<div class="panel revisions-panel">
    <h3>🕘 Revision history <span class="muted">(restore snapshots the current version first)</span></h3>
    <?php foreach ($revisions as $rev): ?>
    <form method="post" class="revision-row">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="revision_id" value="<?= (int)$rev['id'] ?>">
        <span class="muted"><?= e(date('M j, H:i', strtotime($rev['saved_at']))) ?></span>
        <span class="truncate"><?= e($rev['title']) ?></span>
        <button class="btn btn-sm btn-ghost" data-confirm="Restore this version?">↩ Restore</button>
    </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
