<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    die('Not logged in. Please log in first.');
}

$meId = (int)($_SESSION['user_id'] ?? 0);

echo "<h2>Chat Debugging</h2>";
echo "<p><strong>Your Session Data:</strong></p>";
echo "<ul>";
echo "<li>user_id: " . $meId . "</li>";
echo "<li>user_name: " . ($_SESSION['user_name'] ?? 'NOT SET') . "</li>";
echo "<li>user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "</li>";
echo "</ul>";

// Get all users
echo "<p><strong>Available Users (for chatting):</strong></p>";
try {
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id <> ? ORDER BY name ASC');
    $stmt->execute([$meId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($users) {
        echo "<ul>";
        foreach ($users as $u) {
            echo "<li>ID: " . $u['id'] . ", Name: " . $u['name'] . ", Email: " . $u['email'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No other users found.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// Test message insert
echo "<p><strong>Testing Message Insert:</strong></p>";
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $testText = "Test message from " . date('Y-m-d H:i:s');
    
    if ($receiverId <= 0) {
        echo "<p style='color:red;'>Please select a valid receiver ID.</p>";
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)');
            $stmt->execute([$meId, $receiverId, $testText]);
            echo "<p style='color:green;'>✓ Test message sent successfully! Message ID: " . $pdo->lastInsertId() . "</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Show recent messages
echo "<p><strong>Recent Messages in Database:</strong></p>";
try {
    $stmt = $pdo->query('SELECT m.id, u1.name as sender, u2.name as receiver, m.message_text, m.created_at FROM messages m JOIN users u1 ON m.sender_id = u1.id JOIN users u2 ON m.receiver_id = u2.id ORDER BY m.created_at DESC LIMIT 10');
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($messages) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>From</th><th>To</th><th>Message</th><th>Time</th></tr>";
        foreach ($messages as $m) {
            echo "<tr>";
            echo "<td>" . $m['id'] . "</td>";
            echo "<td>" . $m['sender'] . "</td>";
            echo "<td>" . $m['receiver'] . "</td>";
            echo "<td>" . substr($m['message_text'], 0, 50) . "</td>";
            echo "<td>" . $m['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No messages found.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

?>

<hr>
<h3>Send Test Message</h3>
<form method="POST">
    <label for="receiver_id">Receiver ID:</label>
    <input type="number" name="receiver_id" id="receiver_id" required>
    <button type="submit">Send Test Message</button>
</form>

<p><a href="chat.php">Back to Chat</a> | <a href="member_dashboard.php">Dashboard</a></p>
