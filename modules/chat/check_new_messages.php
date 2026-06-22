<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (ob_get_level() > 0) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get total unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_id = :uid AND status = 'sent'
    ");
    $stmt->execute([':uid' => $userId]);
    $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = (int)($totalRow['unread_count'] ?? 0);

    // Get most recent sender name (for backward compatibility)
    $stmt = $pdo->prepare("
        SELECT u.name
        FROM messages m
        INNER JOIN users u ON u.id = m.sender_id
        WHERE m.receiver_id = :uid AND m.status = 'sent'
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $stmt->execute([':uid' => $userId]);
    $senderRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $recentSenderName = $senderRow['name'] ?? null;

    // Get per-sender breakdown of unread messages
    $stmt = $pdo->prepare("
        SELECT sender_id, COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_id = :uid AND status = 'sent'
        GROUP BY sender_id
        ORDER BY MAX(id) DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $perSenderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $perSenderBreakdown = [];
    foreach ($perSenderRows as $row) {
        $perSenderBreakdown[(int)$row['sender_id']] = (int)$row['unread_count'];
    }

    echo json_encode([
        'ok' => true,
        'unread_count' => $totalCount,
        'recent_sender_name' => $recentSenderName,
        'per_sender_breakdown' => $perSenderBreakdown,
    ]);
} catch (Throwable $e) {
    error_log('check_new_messages error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}

