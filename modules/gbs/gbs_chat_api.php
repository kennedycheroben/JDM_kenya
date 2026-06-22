<?php
ob_start();
require_once dirname(__FILE__) . '/../../core/db_connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$is_super_admin = ($user_role === 'super_admin');
$gbs_id = (int)($_REQUEST['gbs_id'] ?? 0);
$action = $_REQUEST['action'] ?? 'fetch';

if ($gbs_id <= 0) {
    ob_clean();
    echo json_encode(['ok' => false, 'error' => 'Invalid GBS ID']);
    exit;
}

// Check if user is a member, unless they are the JDM Leader.
if ($is_super_admin) {
    $stmt = $pdo->prepare("SELECT id FROM gbs_groups WHERE id = ? LIMIT 1");
    $stmt->execute([$gbs_id]);
    $membership = $stmt->fetch() ? ['role' => 'leader'] : false;
} else {
    $stmt = $pdo->prepare("SELECT role FROM gbs_members WHERE gbs_id = ? AND user_id = ?");
    $stmt->execute([$gbs_id, $user_id]);
    $membership = $stmt->fetch();
}

if (!$membership) {
    ob_clean();
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$is_leader = ($membership['role'] === 'leader') || $is_super_admin;

if ($action === 'fetch') {
    $last_id = (int)($_GET['last_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.message_text, m.created_at, u.name as sender_name, u.pfp_path
        FROM gbs_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.gbs_id = ? AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 100
    ");
    $stmt->execute([$gbs_id, $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_clean();
    echo json_encode(['ok' => true, 'messages' => $messages]);
    exit;
}

if ($action === 'send') {
    // Check if chat is restricted
    $stmt = $pdo->prepare("SELECT chat_restricted FROM gbs_groups WHERE id = ?");
    $stmt->execute([$gbs_id]);
    $restricted = (bool)$stmt->fetchColumn();
    
    if ($restricted && !$is_leader) {
        ob_clean();
        echo json_encode(['ok' => false, 'error' => 'Only the group leader can send messages at this time.']);
        exit;
    }
    
    $text = trim($_POST['message'] ?? '');
    if (empty($text)) {
        ob_clean();
        echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO gbs_messages (gbs_id, sender_id, message_text) VALUES (?, ?, ?)");
    $stmt->execute([$gbs_id, $user_id, $text]);
    
    ob_clean();
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}
