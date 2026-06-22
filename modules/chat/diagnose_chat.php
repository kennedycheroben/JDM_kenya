<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

echo "<h2>Chat System Diagnosis</h2>";

// Check if messages table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() > 0) {
        echo "<p><strong style='color:green;'>✓ Messages table EXISTS</strong></p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE messages");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p><strong>Table columns:</strong></p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li>" . $col['Field'] . " - " . $col['Type'] . (isset($col['Default']) ? " (default: " . $col['Default'] . ")" : "") . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p><strong style='color:red;'>✗ Messages table NOT FOUND - Creating it now...</strong></p>";
        
        // Create the messages table
        $sql = "CREATE TABLE IF NOT EXISTS messages (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          sender_id INT UNSIGNED NOT NULL,
          receiver_id INT UNSIGNED NOT NULL,
          message_text TEXT NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_messages_pair (sender_id, receiver_id, id),
          KEY idx_messages_receiver (receiver_id, id),
          CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p><strong style='color:green;'>✓ Messages table created successfully</strong></p>";
    }
} catch (Exception $e) {
    echo "<p><strong style='color:red;'>Error: " . $e->getMessage() . "</strong></p>";
}

// Check if users table exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total users:</strong> " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p><strong style='color:red;'>Error checking users: " . $e->getMessage() . "</strong></p>";
}

// Test insert
try {
    echo "<p><strong>Testing message insert...</strong></p>";
    $testStmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
    $testStmt->execute([1, 2, "Test message"]);
    echo "<p><strong style='color:green;'>✓ Test message inserted successfully (ID: " . $pdo->lastInsertId() . ")</strong></p>";
    
    // Delete test message
    $pdo->exec("DELETE FROM messages WHERE message_text = 'Test message'");
} catch (Exception $e) {
    echo "<p><strong style='color:red;'>✗ Error inserting test message: " . $e->getMessage() . "</strong></p>";
}

echo "<p><a href='chat.php'>Back to Chat</a></p>";
?>
