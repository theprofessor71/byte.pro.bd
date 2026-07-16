<?php
/**
 * Secure download endpoint: /download.php?file=<64-hex-hash>
 * The hash maps to a row in `downloads`; real path never leaves the server.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$hash = is_string($_GET['file'] ?? '') ? ($_GET['file'] ?? '') : '';
if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
    logSecurity('download_bad_hash', 'Malformed hash: ' . substr($hash, 0, 80));
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = db()->prepare('SELECT * FROM downloads WHERE file_hash = ?');
$stmt->execute([$hash]);
$file = $stmt->fetch();

// basename() again on read — even a poisoned DB row can't traverse out
$full = $file ? CB_UPLOADS . '/' . basename($file['file_path']) : '';

if (!$file || !is_file($full)) {
    logSecurity('download_miss', 'Unknown hash requested');
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

db()->prepare('UPDATE downloads SET download_count = download_count + 1 WHERE id = ?')
    ->execute([$file['id']]);
logSecurity('download_ok', $file['real_filename']);

// Safe filename for the Content-Disposition header
$safeName = preg_replace('/[^\w.\- ]/', '_', $file['real_filename']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-transform');

// Stream in chunks — InfinityFree PHP memory limits are modest
$fp = fopen($full, 'rb');
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
