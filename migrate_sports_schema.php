<?php
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/sports_schema.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
        http_response_code(403);
        echo 'Access denied. Sign in as JDM Leader / super admin to run this migration.';
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
}

try {
    $pdo->exec('ALTER TABLE users ENGINE=InnoDB');
} catch (Throwable $e) {
    echo "Users table engine check skipped: {$e->getMessage()}\n";
}

try {
    ensure_sports_schema($pdo);
    echo "Sports database schema is ready.\n";
    echo "You can now open sports_admin.php again.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Sports migration failed:\n";
    echo $e->getMessage() . "\n";
    echo "\nIf this is a duplicate foreign-key/index error, open phpMyAdmin and compare existing sports_* tables with schema_sports.sql.\n";
    exit(1);
}
