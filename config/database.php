<?php
/**
 * PDO connection — single shared instance.
 * Real prepares only (no emulation) so SQL and data never mix.
 */
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            // Never leak credentials/DSN details to visitors
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            exit('Service temporarily unavailable.');
        }
    }
    return $pdo;
}
