<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (ob_get_level() > 0) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$senderId = (int)($_GET['sender_id'] ?? $_POST['sender_id'] ?? 0);
if ($senderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid sender_id']);
    exit;
}

try {
    // Mark all unread messages from this sender as read
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET status = 'read' 
        WHERE sender_id = :sender_id 
        AND receiver_id = :receiver_id 
        AND status = 'sent'
    ");
    $stmt->execute([
        ':sender_id' => $senderId,
        ':receiver_id' => $currentUserId
    ]);
    
    $rowsUpdated = $stmt->rowCount();
    
    echo json_encode([
        'ok' => true,
        'rows_updated' => $rowsUpdated,
        'message' => "$rowsUpdated message(s) marked as read"
    ]);
} catch (Throwable $e) {
    error_log('mark_as_read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
