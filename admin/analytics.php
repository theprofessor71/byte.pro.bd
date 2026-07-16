<?php
/**
 * Analytics — privacy-safe aggregates only (no per-visitor tracking):
 * top posts, 30-day views, reaction totals, attack summary from security_logs.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

/* Views: last 30 days, all posts */
$daily = $pdo->query("SELECT view_date, SUM(views) v FROM post_views_daily
    WHERE view_date > (CURDATE() - INTERVAL 30 DAY)
    GROUP BY view_date ORDER BY view_date")->fetchAll();

/* Top posts by lifetime views */
$top = $pdo->query("SELECT title, slug, views, reading_time FROM posts
    WHERE status='published' ORDER BY views DESC LIMIT 10")->fetchAll();

/* Reactions per type across the site */
$reactTotals = $pdo->query("SELECT reaction, COUNT(*) c FROM post_reactions
    GROUP BY reaction ORDER BY c DESC")->fetchAll();

/* Most-upvoted comments */
$topComments = $pdo->query("SELECT c.author_name, c.content, p.title, p.slug, COUNT(v.ip_hash) votes
    FROM comment_votes v
    JOIN comments c ON c.id = v.comment_id
    JOIN posts p ON p.id = c.post_id
    GROUP BY v.comment_id ORDER BY votes DESC LIMIT 5")->fetchAll();

/* Attack summary — last 7 days of hostile log actions */
$hostile = ['failed_login', 'failed_2fa', 'blocked_attempt', 'csrf_failure',
            'honeypot_comment', 'honeypot_contact', 'honeypot_subscribe',
            'rate_limited', 'download_bad_hash', 'totp_replay'];
$ph = implode(',', array_fill(0, count($hostile), '?'));
$stmt = $pdo->prepare("SELECT action, COUNT(*) c FROM security_logs
    WHERE action IN ($ph) AND created_at > (NOW() - INTERVAL 7 DAY)
    GROUP BY action ORDER BY c DESC");
$stmt->execute($hostile);
$attacks = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT ip_address, COUNT(*) c FROM security_logs
    WHERE action IN ($ph) AND created_at > (NOW() - INTERVAL 7 DAY)
    GROUP BY ip_address ORDER BY c DESC LIMIT 10");
$stmt->execute($hostile);
$attackers = $stmt->fetchAll();

$pageTitle = 'Analytics';
require __DIR__ . '/_header.php';
?>
<div class="stat-grid">
    <div class="panel stat-card">
        <div class="stat-num"><?= number_format(array_sum(array_column($daily, 'v'))) ?></div>
        <div class="muted">views · 30 days</div>
    </div>
    <div class="panel stat-card">
        <div class="stat-num"><?= number_format(array_sum(array_column($reactTotals, 'c'))) ?></div>
        <div class="muted">total reactions</div>
    </div>
    <div class="panel stat-card">
        <div class="stat-num"><?= number_format(array_sum(array_column($attacks, 'c'))) ?></div>
        <div class="muted">hostile events · 7 days</div>
    </div>
</div>

<div class="panel">
    <h2>📈 Daily views (30 days)</h2>
    <div class="bar-chart">
        <?php $max = max(1, ...array_map(fn($d) => (int)$d['v'], $daily ?: [['v' => 1]])); ?>
        <?php foreach ($daily as $d): ?>
        <div class="bar" style="--h: <?= (int)(((int)$d['v'] / $max) * 100) ?>%"
             title="<?= e($d['view_date']) ?>: <?= (int)$d['v'] ?> views"></div>
        <?php endforeach; ?>
        <?php if (!$daily): ?><p class="muted">No view data yet.</p><?php endif; ?>
    </div>
</div>

<div class="two-col">
    <div class="panel">
        <h2>🏆 Top posts</h2>
        <table class="data-table">
            <thead><tr><th>Post</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($top as $t): ?>
            <tr>
                <td><a href="/post/<?= e($t['slug']) ?>" target="_blank"><?= e($t['title']) ?></a></td>
                <td><?= number_format((int)$t['views']) ?></td>
            </tr>
            <?php endforeach; if (!$top): ?>
            <tr><td colspan="2" class="muted">No published posts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>🛡️ Attack summary (7 days)</h2>
        <table class="data-table">
            <thead><tr><th>Event</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($attacks as $a): ?>
            <tr><td><span class="badge badge-red"><?= e($a['action']) ?></span></td><td><?= (int)$a['c'] ?></td></tr>
            <?php endforeach; if (!$attacks): ?>
            <tr><td colspan="2" class="muted">Quiet week — nothing hostile logged. 🎉</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if ($attackers): ?>
        <h3>Noisiest IPs</h3>
        <table class="data-table">
            <tbody>
            <?php foreach ($attackers as $a): ?>
            <tr><td><code><?= e($a['ip_address']) ?></code></td><td><?= (int)$a['c'] ?> events</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted">Block repeat offenders in Cloudflare → Security → WAF → Tools.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($reactTotals || $topComments): ?>
<div class="two-col">
    <div class="panel">
        <h2>💛 Reactions</h2>
        <table class="data-table"><tbody>
        <?php foreach ($reactTotals as $r): ?>
        <tr><td><?= e(ucfirst($r['reaction'])) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="panel">
        <h2>⬆ Top comments</h2>
        <?php foreach ($topComments as $tc): ?>
        <div class="mini-comment">
            <strong><?= e($tc['author_name']) ?></strong>
            <span class="badge badge-green">▲ <?= (int)$tc['votes'] ?></span>
            on <a href="/post/<?= e($tc['slug']) ?>#comments" target="_blank"><?= e($tc['title']) ?></a>
            <p class="muted truncate"><?= e(mb_substr($tc['content'], 0, 120)) ?></p>
        </div>
        <?php endforeach; if (!$topComments): ?><p class="muted">No comment votes yet.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
