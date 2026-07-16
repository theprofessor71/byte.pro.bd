<?php
/**
 * Subscribers — list, delete, CSV export. Collect-only newsletter:
 * export the CSV and send from any external service (Brevo, Buttondown…).
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

/* CSV export — POST + CSRF so a link can't trigger it */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    requireCsrf();
    logSecurity('subscribers_export', 'CSV by ' . currentAdminName());
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    foreach ($pdo->query('SELECT email, created_at FROM subscribers ORDER BY id') as $row) {
        fputcsv($out, [$row['email'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
        logSecurity('subscriber_deleted', "Subscriber #$id by " . currentAdminName());
    }
    header('Location: /admin/subscribers.php');
    exit;
}

$total = (int)$pdo->query('SELECT COUNT(*) c FROM subscribers')->fetch()['c'];
$pg = paginate($total, 50, (int)($_GET['page'] ?? 1));
$stmt = $pdo->prepare('SELECT * FROM subscribers ORDER BY id DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $pg['perPage'], PDO::PARAM_INT);
$stmt->bindValue(2, $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle = 'Subscribers';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form method="post" class="inline"><?= csrfField() ?>
        <button name="action" value="export" class="btn btn-primary">⬇ Export CSV</button>
    </form>
    <span class="muted"><?= number_format($total) ?> subscriber<?= $total === 1 ? '' : 's' ?></span>
</div>
<div class="panel">
<table class="data-table">
    <thead><tr><th>#</th><th>Email</th><th>Signed up</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['email']) ?></td>
        <td class="nowrap"><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
        <td>
            <form method="post" class="inline"><?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button name="action" value="delete" class="btn btn-sm btn-danger"
                    data-confirm="Remove this subscriber?">🗑</button>
            </form>
        </td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="4" class="muted">No subscribers yet — the form lives in the site footer.</td></tr>
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
