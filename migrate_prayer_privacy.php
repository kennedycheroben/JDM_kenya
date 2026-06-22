<?php
/**
 * Database Migration Script
 * Applies prayer wall privacy level feature
 */

require_once __DIR__ . '/core/db_connect.php';

$success = true;
$messages = [];

try {
    // Check if privacy_level column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM prayer_requests LIKE 'privacy_level'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if ($exists) {
        $messages[] = "✓ Column 'privacy_level' already exists";
    } else {
        // Add privacy_level column
        $pdo->exec("ALTER TABLE prayer_requests ADD COLUMN privacy_level ENUM('public', 'anonymous', 'private') NOT NULL DEFAULT 'public' AFTER is_private");
        $messages[] = "✓ Added 'privacy_level' column to prayer_requests table";
        
        // Migrate existing is_private data
        $pdo->exec("UPDATE prayer_requests SET privacy_level = CASE WHEN is_private = 1 THEN 'private' ELSE 'public' END WHERE privacy_level = 'public'");
        $messages[] = "✓ Migrated existing privacy data (is_private → privacy_level)";
        
        // Add index
        try {
            $pdo->exec("ALTER TABLE prayer_requests ADD INDEX idx_prayer_requests_privacy_level (privacy_level)");
            $messages[] = "✓ Added index on 'privacy_level' column";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
            $messages[] = "⚠ Index already exists";
        }
    }
    
} catch (PDOException $e) {
    $success = false;
    $messages[] = "✗ Database error: " . $e->getMessage();
}

// Output results
header('Content-Type: text/plain; charset=utf-8');
echo "=== Prayer Wall Privacy Migration ===\n\n";
foreach ($messages as $msg) {
    echo $msg . "\n";
}
echo "\n" . ($success ? "✅ Migration completed successfully!" : "❌ Migration failed!");
?>
