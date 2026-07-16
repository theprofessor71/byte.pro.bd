<?php
/**
 * Backup — stream a full SQL dump of the site's tables as a download.
 * Pure-PHP dump (no shell/exec — InfinityFree disables it). POST + CSRF.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

$tables = ['admins', 'posts', 'comments', 'comment_votes', 'downloads', 'security_logs',
           'contact_messages', 'subscribers', 'post_views_daily', 'post_reactions', 'post_revisions'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download') {
    requireCsrf();
    logSecurity('backup_downloaded', 'SQL dump by ' . currentAdminName());

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="cyberblogs-backup-' . date('Y-m-d-His') . '.sql"');
    header('X-Content-Type-Options: nosniff');

    echo "-- CyberBlogs backup " . date('c') . "\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        // Table names come from the fixed list above — never from user input
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
        if (!$create) {
            continue;
        }
        echo "DROP TABLE IF EXISTS `$t`;\n" . $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`");
        $batch = [];
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values(array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY)));
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 50) { // stream in chunks — free-tier memory guard
                echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
                flush();
            }
        }
        if ($batch) {
            echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n-- done\n";
    exit;
}

/* Table sizes for the overview */
$sizes = [];
foreach ($tables as $t) {
    try {
        $sizes[$t] = (int)$pdo->query("SELECT COUNT(*) c FROM `$t`")->fetch()['c'];
    } catch (PDOException $e) {
        $sizes[$t] = -1; // table missing (pre-upgrade DB)
    }
}

$pageTitle = 'Backup';
require __DIR__ . '/_header.php';
?>
<div class="panel">
    <h2>💾 Database backup</h2>
    <p class="muted">Downloads a full SQL dump (schema + data) of all <?= count($tables) ?> tables.
       Restore by importing the file in phpMyAdmin. Do this before big changes and on a schedule —
       InfinityFree has no automatic backups.</p>
    <form method="post"><?= csrfField() ?>
        <button name="action" value="download" class="btn btn-primary">⬇ Download SQL dump</button>
    </form>
</div>
<div class="panel">
    <h3>Tables</h3>
    <table class="data-table">
        <thead><tr><th>Table</th><th>Rows</th></tr></thead>
        <tbody>
        <?php foreach ($sizes as $t => $c): ?>
        <tr>
            <td><code><?= e($t) ?></code></td>
            <td><?= $c < 0 ? '<span class="badge badge-red">missing — run the ALTER/CREATE from sql/schema.sql</span>' : number_format($c) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
