<?php
/**
 * Migration: create contact_messages table for admin dashboard inbox.
 */

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/contact_messages.php';

$success = true;
$messages = [];

try {
    ensureContactMessagesTable($pdo);
    $messages[] = 'Contact messages table is ready.';
} catch (PDOException $e) {
    $success = false;
    $messages[] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Contact Messages Migration ===\n\n";
foreach ($messages as $msg) {
    echo "- $msg\n";
}
echo "\n" . ($success ? "Migration completed successfully." : "Migration failed.");
