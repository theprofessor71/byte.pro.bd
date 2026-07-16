<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
secureSessionStart();
// Logout must be POST + CSRF so a hostile page can't log the admin out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    logSecurity('logout', 'Admin: ' . currentAdminName());
    adminLogout();
}
header('Location: /admin/login.php');
exit;
