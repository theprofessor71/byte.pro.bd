<?php
/**
 * Security log viewer — filter by action, search by IP.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

$actions = $pdo->query('SELECT DISTINCT action FROM security_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$fAction = $_GET['action'] ?? '';
$fIp     = trim($_GET['ip'] ?? '');

$where = [];
$params = [];
if ($fAction !== '' && in_array($fAction, $actions, true)) {
    $where[] = 'action = ?';
    $params[] = $fAction;
}
if ($fIp !== '') {
    $where[] = 'ip_address LIKE ?';
    $params[] = addcslashes($fIp, '%_\\') . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)(function () use ($pdo, $whereSql, $params) {
    $s = $pdo->prepare("SELECT COUNT(*) c FROM security_logs $whereSql");
    $s->execute($params);
    return $s->fetch()['c'];
})();
$pg = paginate($total, 50, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT * FROM security_logs $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $v) {
    $stmt->bindValue($i + 1, $v);
}
$stmt->bindValue(count($params) + 1, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle = 'Security Logs';
require __DIR__ . '/_header.php';
?>
<form method="get" class="toolbar">
    <select name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $fAction === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
    </select>
    <input name="ip" placeholder="Filter by IP…" value="<?= e($fIp) ?>">
    <button class="btn btn-primary">Filter</button>
    <span class="muted"><?= number_format($total) ?> entries</span>
</form>
<div class="panel">
<table class="data-table">
    <thead><tr><th>#</th><th>Time</th><th>Action</th><th>IP</th><th>Details</th><th>User Agent</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= (int)$r['id'] ?></td>
        <td class="nowrap"><?= e($r['created_at']) ?></td>
        <td><span class="badge badge-<?= str_starts_with($r['action'], 'failed') || in_array($r['action'], ['blocked_attempt', 'csrf_failure'], true) ? 'red' : 'green' ?>"><?= e($r['action']) ?></span></td>
        <td><code><?= e($r['ip_address']) ?></code></td>
        <td class="truncate"><?= e($r['details'] ?? '') ?></td>
        <td class="truncate muted"><?= e($r['user_agent'] ?? '') ?></td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="6" class="muted">No log entries match.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php if ($pg['pages'] > 1): ?>
<div class="pagination">
    <?php for ($i = max(1, $pg['current'] - 5); $i <= min($pg['pages'], $pg['current'] + 5); $i++): ?>
    <a class="page-link <?= $i === $pg['current'] ? 'active' : '' ?>"
       href="?action=<?= e(urlencode($fAction)) ?>&ip=<?= e(urlencode($fIp)) ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
