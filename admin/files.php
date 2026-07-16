<?php
/**
 * File upload manager. Uploads land in protected_uploads/ under a random
 * name; the public only ever sees download.php?file=<hash>. Strict
 * extension + MIME allowlist, size cap, images verified as real images.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sendSecurityHeaders();
requireAdmin();
$pdo = db();

const MAX_UPLOAD = 10 * 1024 * 1024; // 10 MB — matches InfinityFree's upload_max_filesize
const ALLOWED = [
    'pdf'  => ['application/pdf'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
    'txt'  => ['text/plain'],
    'md'   => ['text/plain', 'text/markdown'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'webp' => ['image/webp'],
    'gif'  => ['image/gif'],
];

$error = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT file_path FROM downloads WHERE id = ?');
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            $full = CB_UPLOADS . '/' . basename($row['file_path']);
            if (is_file($full)) {
                unlink($full);
            }
            $pdo->prepare('DELETE FROM downloads WHERE id = ?')->execute([$id]);
            logSecurity('file_deleted', "Download #$id by " . currentAdminName());
            $ok = 'File deleted.';
        }
    } elseif (!empty($_FILES['upload']['name'])) {
        $f = $_FILES['upload'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'File too large — the server rejected it (host limit ~10 MB).';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed (error code ' . (int)$f['error'] . ').';
        } elseif ($f['size'] > MAX_UPLOAD) {
            $error = 'File too large (max 10 MB).';
        } elseif (!isset(ALLOWED[$ext])) {
            $error = 'File type not allowed. Allowed: ' . implode(', ', array_keys(ALLOWED));
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
            $imgExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
            if (!in_array($mime, ALLOWED[$ext], true)) {
                $error = "Content doesn't match extension ($mime).";
            } elseif (in_array($ext, $imgExts, true) && getimagesize($f['tmp_name']) === false) {
                $error = 'File is not a valid image.';
            } else {
                $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = CB_UPLOADS . '/' . $storedName;
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    $error = 'Could not move uploaded file — check protected_uploads/ permissions.';
                } else {
                    chmod($dest, 0644);
                    $hash = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO downloads (file_hash, real_filename, file_path, mime_type, file_size)
                                   VALUES (?,?,?,?,?)')
                        ->execute([$hash, basename($f['name']), $storedName, $mime, (int)$f['size']]);
                    logSecurity('file_uploaded', basename($f['name']) . ' by ' . currentAdminName());
                    $ok = 'Uploaded. Public link: /download.php?file=' . $hash;
                }
            }
        }
    }
}

$files = $pdo->query('SELECT * FROM downloads ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Files';
require __DIR__ . '/_header.php';
?>
<?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
    <h2>⬆️ Upload file</h2>
    <form method="post" enctype="multipart/form-data"><?= csrfField() ?>
        <input type="file" name="upload" required>
        <button type="submit" class="btn btn-primary">Upload</button>
        <p class="muted">Max 10 MB. Allowed: pdf, zip, txt, md, png, jpg, webp, gif.
           Files are stored privately — share the <code>download.php?file=…</code> link.</p>
    </form>
</div>

<div class="panel">
    <h2>📁 Files (<?= count($files) ?>)</h2>
    <table class="data-table">
        <thead><tr><th>Name</th><th>Size</th><th>Downloads</th><th>Link</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
        <tr>
            <td><?= e($f['real_filename']) ?></td>
            <td class="nowrap"><?= number_format((int)$f['file_size'] / 1024, 1) ?> KB</td>
            <td><?= (int)$f['download_count'] ?></td>
            <td><input class="copy-input" readonly value="<?= e(SITE_URL) ?>/download.php?file=<?= e($f['file_hash']) ?>" data-select-on-click></td>
            <td>
                <form method="post" class="inline"><?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <button class="btn btn-sm btn-danger" data-confirm="Delete this file?">🗑</button>
                </form>
            </td>
        </tr>
        <?php endforeach; if (!$files): ?>
        <tr><td colspan="5" class="muted">No files uploaded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
