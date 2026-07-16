<?php
/**
 * Admin dashboard — stats cards, 30-day views chart (Chart.js),
 * top posts, security alerts, recent activity, category breakdown.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();

$pdo = db();

/* ---- Stats ---- */
$posts = $pdo->query("SELECT COUNT(*) total,
        SUM(status='published') published, SUM(status='draft') drafts,
        COALESCE(SUM(views),0) views_total FROM posts")->fetch();
$commentStats = $pdo->query("SELECT COUNT(*) total, SUM(status=0) pending, SUM(status=1) approved FROM comments")->fetch();
$msgStats = $pdo->query("SELECT COUNT(*) total, SUM(is_read=0) unread FROM contact_messages")->fetch();

$viewsToday = (int)$pdo->query("SELECT COALESCE(SUM(views),0) c FROM post_views_daily WHERE view_date = CURDATE()")->fetch()['c'];
$viewsWeek  = (int)$pdo->query("SELECT COALESCE(SUM(views),0) c FROM post_views_daily WHERE view_date > (CURDATE() - INTERVAL 7 DAY)")->fetch()['c'];

/* ---- 30-day chart data ---- */
$chartRows = $pdo->query("SELECT view_date, SUM(views) v FROM post_views_daily
    WHERE view_date > (CURDATE() - INTERVAL 30 DAY)
    GROUP BY view_date ORDER BY view_date")->fetchAll();
$byDate = array_column($chartRows, 'v', 'view_date');
$chartLabels = $chartData = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M j', strtotime($d));
    $chartData[]   = (int)($byDate[$d] ?? 0);
}

/* ---- Top posts / categories / security / activity ---- */
$topPosts = $pdo->query("SELECT title, slug, views FROM posts WHERE status='published' ORDER BY views DESC LIMIT 10")->fetchAll();
$categories = $pdo->query("SELECT category, COUNT(*) c FROM posts WHERE status='published' GROUP BY category ORDER BY c DESC")->fetchAll();
$failedLogins24h = (int)$pdo->query("SELECT COUNT(*) c FROM security_logs
    WHERE action IN ('failed_login','failed_2fa') AND created_at > (NOW() - INTERVAL 24 HOUR)")->fetch()['c'];
$blockedIps = $pdo->query("SELECT ip_address, COUNT(*) c FROM security_logs
    WHERE action IN ('failed_login','failed_2fa') AND created_at > (NOW() - INTERVAL " . LOGIN_WINDOW_MIN . " MINUTE)
    GROUP BY ip_address HAVING c >= " . LOGIN_MAX_ATTEMPTS)->fetchAll();
$activity = $pdo->query("SELECT action, details, ip_address, created_at FROM security_logs ORDER BY id DESC LIMIT 20")->fetchAll();

/* New-IP login alert: successful logins in the last 7 days from IPs that had
   never logged in before that window — flags a possible account compromise. */
$newIpLogins = $pdo->query("SELECT s.ip_address, MIN(s.created_at) first_seen
    FROM security_logs s
    WHERE s.action = 'login_success' AND s.created_at > (NOW() - INTERVAL 7 DAY)
      AND NOT EXISTS (SELECT 1 FROM security_logs o
                      WHERE o.action = 'login_success' AND o.ip_address = s.ip_address
                        AND o.created_at <= (NOW() - INTERVAL 7 DAY))
    GROUP BY s.ip_address ORDER BY first_seen DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard';
$adminScripts = ['/assets/vendor/chart.min.js', '/assets/js/admin-dashboard.js'];
require __DIR__ . '/_header.php';
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon">📝</div>
        <div class="stat-value"><?= (int)$posts['total'] ?></div><div class="stat-label">Posts</div>
        <div class="stat-sub"><?= (int)$posts['published'] ?> published · <?= (int)$posts['drafts'] ?> drafts</div></div>
    <div class="stat-card"><div class="stat-icon">👁️</div>
        <div class="stat-value"><?= number_format((int)$posts['views_total']) ?></div><div class="stat-label">Total Views</div>
        <div class="stat-sub"><?= $viewsToday ?> today · <?= $viewsWeek ?> this week</div></div>
    <div class="stat-card <?= (int)$commentStats['pending'] ? 'stat-warn' : '' ?>"><div class="stat-icon">💬</div>
        <div class="stat-value"><?= (int)$commentStats['total'] ?></div><div class="stat-label">Comments</div>
        <div class="stat-sub"><?= (int)$commentStats['pending'] ?> pending · <?= (int)$commentStats['approved'] ?> approved</div></div>
    <div class="stat-card <?= (int)$msgStats['unread'] ? 'stat-warn' : '' ?>"><div class="stat-icon">📩</div>
        <div class="stat-value"><?= (int)$msgStats['total'] ?></div><div class="stat-label">Messages</div>
        <div class="stat-sub"><?= (int)$msgStats['unread'] ?> unread</div></div>
</div>

<div class="dash-row">
    <div class="panel panel-wide">
        <h2>📈 Views — last 30 days</h2>
        <canvas id="viewsChart" height="90"
            data-labels='<?= e(json_encode($chartLabels)) ?>'
            data-values='<?= e(json_encode($chartData)) ?>'></canvas>
    </div>
    <div class="panel">
        <h2>📊 Categories</h2>
        <canvas id="catChart" height="180"
            data-labels='<?= e(json_encode(array_column($categories, 'category'))) ?>'
            data-values='<?= e(json_encode(array_map('intval', array_column($categories, 'c')))) ?>'></canvas>
    </div>
</div>

<div class="dash-row">
    <div class="panel">
        <h2>🔥 Top posts</h2>
        <ol class="top-posts">
            <?php foreach ($topPosts as $p): ?>
            <li><a href="/post/<?= e($p['slug']) ?>" target="_blank"><?= e($p['title']) ?></a>
                <span class="views-pill"><?= number_format((int)$p['views']) ?> views</span></li>
            <?php endforeach; if (!$topPosts): ?><li class="muted">No published posts yet.</li><?php endif; ?>
        </ol>
    </div>
    <div class="panel">
        <h2>🚨 Security (24h)</h2>
        <p class="sec-stat"><?= $failedLogins24h ?> failed login attempt<?= $failedLogins24h === 1 ? '' : 's' ?></p>
        <?php if ($blockedIps): ?>
            <p class="alert alert-danger">Currently blocked IPs:</p>
            <ul><?php foreach ($blockedIps as $b): ?>
                <li><code><?= e($b['ip_address']) ?></code> (<?= (int)$b['c'] ?> fails)</li>
            <?php endforeach; ?></ul>
        <?php else: ?><p class="alert alert-ok">✅ No blocked IPs right now.</p><?php endif; ?>
        <?php if ($newIpLogins): ?>
        <p class="alert alert-warn">🔔 Successful logins from <strong>new IPs</strong> this week — was this you?</p>
        <ul>
            <?php foreach ($newIpLogins as $n): ?>
            <li><code><?= e($n['ip_address']) ?></code> <span class="muted">first seen <?= e(timeAgo($n['first_seen'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a href="/admin/logs.php" class="btn btn-ghost">View all logs →</a>
    </div>
</div>

<div class="panel">
    <h2>🕐 Recent activity</h2>
    <table class="data-table">
        <thead><tr><th>Time</th><th>Action</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($activity as $a): ?>
        <tr>
            <td class="nowrap"><?= e(timeAgo($a['created_at'])) ?></td>
            <td><span class="badge badge-<?= str_starts_with($a['action'], 'failed') || $a['action'] === 'blocked_attempt' || $a['action'] === 'csrf_failure' ? 'red' : 'green' ?>"><?= e($a['action']) ?></span></td>
            <td><code><?= e($a['ip_address']) ?></code></td>
            <td class="truncate"><?= e($a['details'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="quick-actions">
    <a class="btn btn-primary" href="/admin/post-editor.php">✏️ New Post</a>
    <a class="btn btn-ghost" href="/admin/comments.php">💬 Moderate Comments</a>
    <a class="btn btn-ghost" href="/admin/messages.php">📩 Messages</a>
    <a class="btn btn-ghost" href="/admin/logs.php">🔍 Logs</a>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
