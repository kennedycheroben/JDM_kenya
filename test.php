<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/db_connect.php';

echo "DB connected OK<br>";

// Test the view_member query
$id = 10;
$stmt = $pdo->prepare('
    SELECT u.id, u.name, u.email, u.role, u.category,
           u.is_staff, u.staff_type, u.missionary_type, u.country
    FROM users u
    WHERE u.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User query OK: " . ($u['name'] ?? 'none') . "<br>";

// Test leaders table
$stmt2 = $pdo->prepare('SELECT leader_type, campus_name FROM leaders WHERE user_id = ?');
$stmt2->execute([$id]);
$leaders = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Leaders query OK: found " . count($leaders) . " records<br>";

echo "All checks passed!";
?>