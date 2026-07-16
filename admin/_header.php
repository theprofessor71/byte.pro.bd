<?php
/**
 * Shared admin chrome: header + sidebar. Include AFTER requireAdmin().
 * Set $pageTitle before including. Pair with admin-footer.php.
 */
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    exit('Direct access denied');
}
$pendingComments = (int)db()->query("SELECT COUNT(*) c FROM comments WHERE status = 0")->fetch()['c'];
$unreadMessages  = (int)db()->query("SELECT COUNT(*) c FROM contact_messages WHERE is_read = 0")->fetch()['c'];
$navItems = [
    ['dashboard.php',   '📊', 'Dashboard', 0],
    ['posts.php',       '📝', 'Posts', 0],
    ['comments.php',    '💬', 'Comments', $pendingComments],
    ['messages.php',    '📩', 'Messages', $unreadMessages],
    ['files.php',       '📁', 'Files', 0],
    ['subscribers.php', '📬', 'Subscribers', 0],
    ['analytics.php',   '📈', 'Analytics', 0],
    ['logs.php',        '🔍', 'Security Logs', 0],
    ['backup.php',      '💾', 'Backup', 0],
    ['settings.php',    '⚙️', 'Settings', 0],
];
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-logo">🛡️ <span class="grad-text"><?= e(SITE_NAME) ?></span></div>
        <nav>
            <?php foreach ($navItems as [$href, $icon, $label, $badge]): ?>
            <a href="/admin/<?= $href ?>" class="nav-item <?= $currentPage === $href ? 'active' : '' ?>">
                <span class="nav-icon"><?= $icon ?></span> <?= $label ?>
                <?php if ($badge > 0): ?><span class="badge badge-red"><?= $badge ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="/" target="_blank" class="nav-item">🌐 View Site</a>
            <form method="post" action="/admin/logout.php"><?= csrfField() ?>
                <button type="submit" class="nav-item nav-logout">🚪 Logout (<?= e(currentAdminName()) ?>)</button>
            </form>
        </div>
    </aside>
    <main class="admin-main">
        <h1 class="page-title"><?= e($pageTitle ?? 'Admin') ?></h1>
