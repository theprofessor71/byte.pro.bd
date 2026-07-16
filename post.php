<?php
/**
 * Single post — markdown render, TOC, reactions, comments (moderated),
 * related posts, share buttons, view tracking.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/markdown.php';

$pdo = db();
$slug = str_param($_GET, 'slug');
if ($slug === '' || !preg_match('/^[a-z0-9-]{1,200}$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$post = $stmt->fetch();

/* Draft preview: /post/slug?preview=<32-hex token> shows an unpublished post */
$isPreview = false;
$previewToken = is_string($_GET['preview'] ?? '') ? ($_GET['preview'] ?? '') : '';
if (!$post && preg_match('/^[a-f0-9]{32}$/', $previewToken)) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'draft' AND preview_token != '' AND preview_token = ?");
    $stmt->execute([$slug, $previewToken]);
    if ($post = $stmt->fetch()) {
        $isPreview = true;
    }
}
if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/* ---- Comment submit ---- */
$commentMsg = $commentErr = '';
if (isset($_GET['commented'])) {
    $commentMsg = '✅ Thanks! Your comment is awaiting moderation.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'comment') {
    requireCsrf();
    if (honeypotTripped()) {
        logSecurity('honeypot_comment', 'Post #' . $post['id']);
        $commentMsg = 'Comment submitted for review.'; // pretend success to the bot
    } elseif (actionCount('comment_posted', RATE_WINDOW_MIN) >= COMMENT_LIMIT) {
        $commentErr = 'Too many comments — slow down and try again later.';
        logSecurity('rate_limited', 'comment flood, post #' . $post['id']);
    } else {
        $name    = str_param($_POST, 'author_name');
        $email   = str_param($_POST, 'author_email');
        $content = str_param($_POST, 'content');
        $parent  = (int)(is_scalar($_POST['parent_id'] ?? 0) ? ($_POST['parent_id'] ?? 0) : 0) ?: null;

        if ($name === '' || mb_strlen($name) > 100) {
            $commentErr = 'Name is required (max 100 chars).';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $commentErr = 'Email looks invalid.';
        } elseif (mb_strlen($content) < 3 || mb_strlen($content) > 3000) {
            $commentErr = 'Comment must be 3–3000 characters.';
        } else {
            if ($parent) { // parent must be an approved comment on this post
                $chk = $pdo->prepare('SELECT id, parent_id FROM comments WHERE id = ? AND post_id = ? AND status = 1');
                $chk->execute([$parent, $post['id']]);
                if (!$row = $chk->fetch()) {
                    $parent = null;
                } elseif ($row['parent_id']) {
                    // Threads are 1 level deep — replying to a reply attaches
                    // to the top-level ancestor so it stays visible
                    $parent = (int)$row['parent_id'];
                }
            }
            $pdo->prepare('INSERT INTO comments (post_id, parent_id, author_name, author_email, content, ip_address)
                           VALUES (?,?,?,?,?,?)')
                ->execute([$post['id'], $parent, $name, $email ?: null, $content, getClientIp()]);
            logSecurity('comment_posted', 'Post #' . $post['id']);
            // POST/redirect/GET — a refresh must not resubmit the comment
            header('Location: /post/' . $post['slug'] . '?commented=1#comments');
            exit;
        }
    }
}

if (!$isPreview) {
    recordView((int)$post['id']);
}

$html = renderMarkdown($post['content']);
$toc  = extractToc($html);

// Cap the thread — free-tier memory guard; oldest first so context reads naturally
$comments = $pdo->prepare('SELECT c.*, COALESCE(v.votes, 0) AS votes
    FROM comments c
    LEFT JOIN (SELECT comment_id, COUNT(*) votes FROM comment_votes GROUP BY comment_id) v
      ON v.comment_id = c.id
    WHERE c.post_id = ? AND c.status = 1 ORDER BY c.created_at ASC LIMIT 300');
$comments->execute([$post['id']]);
$comments = $comments->fetchAll();
$byParent = [];
foreach ($comments as $c) {
    $byParent[$c['parent_id'] ?? 0][] = $c;
}
// Top-level: most-upvoted first, ties by age
if (isset($byParent[0])) {
    usort($byParent[0], fn($a, $b) => [$b['votes'], $a['created_at']] <=> [$a['votes'], $b['created_at']]);
}

$reactions = $pdo->prepare('SELECT reaction, COUNT(*) c FROM post_reactions WHERE post_id = ? GROUP BY reaction');
$reactions->execute([$post['id']]);
$reactCounts = array_column($reactions->fetchAll(), 'c', 'reaction');

/* Series navigation — other parts of the same multi-part write-up */
$seriesParts = [];
if ($post['series'] !== '') {
    $sp = $pdo->prepare("SELECT title, slug, series_part FROM posts
        WHERE status='published' AND series = ? ORDER BY series_part ASC, created_at ASC");
    $sp->execute([$post['series']]);
    $seriesParts = $sp->fetchAll();
}

/* Related: same category OR sharing a tag, ranked by tag overlap */
$myTags = parseTags($post['tags']);
$related = $pdo->prepare("SELECT title, slug, excerpt, thumbnail, category, tags, views, reading_time, created_at
    FROM posts WHERE status='published' AND id != ? AND (category = ? OR tags != '')
    ORDER BY created_at DESC LIMIT 30");
$related->execute([$post['id'], $post['category']]);
$candidates = $related->fetchAll();
usort($candidates, function ($a, $b) use ($myTags, $post) {
    $score = fn($p) => count(array_intersect($myTags, parseTags($p['tags'])))
        + ($p['category'] === $post['category'] ? 1 : 0);
    return $score($b) <=> $score($a);
});
$related = array_slice(array_values(array_filter($candidates, function ($p) use ($myTags, $post) {
    return $p['category'] === $post['category'] || array_intersect($myTags, parseTags($p['tags']));
})), 0, 3);

/* Author byline */
$author = null;
if ($post['author_id']) {
    $au = $pdo->prepare('SELECT id, username FROM admins WHERE id = ?');
    $au->execute([$post['author_id']]);
    $author = $au->fetch() ?: null;
}

$pageTitle = $post['title'];
$pageDesc  = $post['excerpt'] ?: excerptOf($post['content']);
$ogType    = 'article';
$ogImage   = $post['thumbnail'] ?: '';
if ($ogImage !== '' && $ogImage[0] === '/') {
    $ogImage = SITE_URL . $ogImage; // og:image must be absolute
}
$bodyClass = 'page-post';
$pageScripts = ['/assets/vendor/prism.js', '/assets/js/comments.js'];
$shareUrl  = SITE_URL . '/post/' . $post['slug'];
require __DIR__ . '/includes/header.php';

/* Lucide-style inline SVGs for reactions */
$reactionIcons = [
    'like'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>',
    'love'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
    'rocket' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>',
    'fire'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
    'star'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>',
];
?>
<article class="container post-layout" data-post-id="<?= (int)$post['id'] ?>">
    <?php if ($isPreview): ?>
    <div class="alert alert-warn preview-banner">👁 <strong>Draft preview</strong> — this post is not published. Anyone with this link can view it.</div>
    <?php endif; ?>
    <header class="post-head">
        <div class="card-meta">
            <a class="chip" href="/category/<?= e(rawurlencode($post['category'])) ?>"><?= e($post['category']) ?></a>
            <?php if ($post['difficulty'] !== ''): ?>
            <span class="chip chip-diff diff-<?= e($post['difficulty']) ?>"><?= e(ucfirst($post['difficulty'])) ?></span>
            <?php endif; ?>
            <span class="muted">⏱ <?= (int)$post['reading_time'] ?> min read</span>
            <span class="muted">👁 <?= number_format((int)$post['views'] + 1) ?></span>
            <time class="muted" datetime="<?= e(date('c', strtotime($post['created_at']))) ?>"><?= e(date('F j, Y', strtotime($post['created_at']))) ?></time>
            <?php if (strtotime($post['updated_at']) - strtotime($post['created_at']) > 86400): ?>
            <span class="muted updated-note">✎ Updated <?= e(date('M j, Y', strtotime($post['updated_at']))) ?></span>
            <?php endif; ?>
            <?php if ($author): ?><span class="muted">by <a href="/author.php?id=<?= (int)$author['id'] ?>"><?= e($author['username']) ?></a></span><?php endif; ?>
        </div>
        <h1 class="post-title"><?= e($post['title']) ?></h1>
        <?php if ($tags = parseTags($post['tags'])): ?>
        <div class="tag-row">
            <?php foreach ($tags as $t): ?><a class="chip chip-sm" href="/tag/<?= e(rawurlencode($t)) ?>">#<?= e($t) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </header>

    <?php if ($post['thumbnail']): ?>
    <img class="post-hero-img" src="<?= e($post['thumbnail']) ?>" alt="<?= e($post['title']) ?>">
    <?php endif; ?>

    <?php if (count($seriesParts) > 1): ?>
    <nav class="series-nav glass" aria-label="Series">
        <div class="series-title mono">📚 Series: <strong><?= e($post['series']) ?></strong></div>
        <ol class="series-list">
            <?php foreach ($seriesParts as $sp): ?>
            <li class="<?= $sp['slug'] === $post['slug'] ? 'current' : '' ?>">
                <?php if ($sp['slug'] === $post['slug']): ?>
                <span><?= e($sp['title']) ?> <em>(you are here)</em></span>
                <?php else: ?>
                <a href="/post/<?= e($sp['slug']) ?>"><?= e($sp['title']) ?></a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>

    <div class="post-body-grid">
        <?php if (count($toc) >= 2): ?>
        <aside class="toc glass" id="toc">
            <h4>Contents</h4>
            <ul>
                <?php foreach ($toc as $h): ?>
                <li class="toc-l<?= $h['level'] ?>"><a href="#<?= e($h['id']) ?>"><?= e($h['text']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <?php endif; ?>

        <div class="post-content" id="postContent">
            <?= $html /* built by renderMarkdown — all user text escaped inside */ ?>

            <div class="post-actions">
                <div class="reactions" id="reactions">
                    <?php foreach ($reactionIcons as $key => $svg): ?>
                    <button class="reaction-btn" data-reaction="<?= e($key) ?>" title="<?= e(ucfirst($key)) ?>">
                        <?= $svg ?><span class="reaction-count"><?= (int)($reactCounts[$key] ?? 0) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="share-row">
                    <button class="icon-btn" id="bookmarkBtn" data-slug="<?= e($post['slug']) ?>" data-title="<?= e($post['title']) ?>" title="Bookmark">🔖</button>
                    <a class="icon-btn" href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&text=<?= e(rawurlencode($post['title'])) ?>" target="_blank" rel="noopener" title="Share on X">𝕏</a>
                    <a class="icon-btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener" title="Share on LinkedIn">in</a>
                    <button class="icon-btn" id="copyLinkBtn" data-url="<?= e($shareUrl) ?>" title="Copy link">🔗</button>
                    <button class="icon-btn" id="printBtn" type="button" title="Print">🖨</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($related): ?>
    <section class="related">
        <h2 class="section-title">Related posts</h2>
        <div class="post-grid grid-3">
            <?php foreach ($related as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="comments-section" id="comments">
        <h2 class="section-title">💬 Comments (<?= count($comments) ?>)</h2>

        <?php if ($commentMsg): ?><div class="alert alert-ok"><?= e($commentMsg) ?></div><?php endif; ?>
        <?php if ($commentErr): ?><div class="alert alert-danger"><?= e($commentErr) ?></div><?php endif; ?>

        <form method="post" class="comment-form glass" id="commentForm">
            <?= csrfField() ?>
            <input type="hidden" name="form" value="comment">
            <input type="hidden" name="parent_id" id="parentId" value="">
            <div class="reply-banner" id="replyBanner" hidden>Replying to <strong id="replyName"></strong> <button type="button" id="cancelReply">✕</button></div>
            <!-- honeypot: humans never see or fill this -->
            <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="form-row">
                <input name="author_name" placeholder="Name *" required maxlength="100">
                <input name="author_email" type="email" placeholder="Email (optional, never shown)" maxlength="255">
            </div>
            <textarea name="content" placeholder="Share your thoughts… (moderated before appearing)" required minlength="3" maxlength="3000" rows="4"></textarea>
            <button class="btn btn-primary">Post comment</button>
        </form>

        <div class="comment-thread">
            <?php foreach ($byParent[0] ?? [] as $c): ?>
            <div class="comment glass">
                <div class="comment-head">
                    <span class="avatar mono"><?= e(strtoupper(mb_substr($c['author_name'], 0, 1))) ?></span>
                    <strong><?= e($c['author_name']) ?></strong>
                    <span class="muted"><?= e(timeAgo($c['created_at'])) ?></span>
                    <button class="upvote-btn" data-cid="<?= (int)$c['id'] ?>" title="Upvote">▲ <span class="upvote-count"><?= (int)$c['votes'] ?></span></button>
                    <button class="reply-btn" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['author_name']) ?>">Reply</button>
                </div>
                <p><?= nl2br(e($c['content'])) ?></p>
                <?php foreach ($byParent[$c['id']] ?? [] as $r): ?>
                <div class="comment comment-reply">
                    <div class="comment-head">
                        <span class="avatar mono"><?= e(strtoupper(mb_substr($r['author_name'], 0, 1))) ?></span>
                        <strong><?= e($r['author_name']) ?></strong>
                        <span class="muted"><?= e(timeAgo($r['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(e($r['content'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$comments): ?><p class="muted">Be the first to comment.</p><?php endif; ?>
        </div>
    </section>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
