<?php
/**
 * Migration: add per-user last-seen timestamps for portal notifications
 * Adds columns to `users` table and sets them to NOW() for existing users
 */

require_once __DIR__ . '/core/db_connect.php';

$messages = [];
$success = true;
try {
    $cols = [
        'last_seen_announcements',
        'last_seen_prayers',
        'last_seen_activities',
        'last_seen_resources',
        'last_seen_gallery'
    ];

    foreach ($cols as $col) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$col` DATETIME NULL AFTER updated_at");
            $messages[] = "Added column $col";
        } else {
            $messages[] = "Column $col already exists";
        }
    }

    // Initialize existing users' last-seen to now so users don't get a flood of historic notifications
    $pdo->exec("UPDATE users SET last_seen_announcements = NOW(), last_seen_prayers = NOW(), last_seen_activities = NOW(), last_seen_resources = NOW(), last_seen_gallery = NOW()");
    $messages[] = "Initialized last-seen timestamps to NOW() for existing users";

} catch (PDOException $e) {
    $success = false;
    $messages[] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== migrate_user_last_seen.php ===\n";
foreach ($messages as $m) echo "- $m\n";
echo $success ? "\n✅ Migration completed" : "\n❌ Migration failed";

?>