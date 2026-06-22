<?php
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/users.php';

// Only super_admin can delete members
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}

$selfId = (int)($_SESSION['user_id'] ?? 0);
if ($id === $selfId) {
    header('Location: view_members.php?error=cannot_delete_self');
    exit;
}

// Protect other super_admin accounts
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$role = $stmt->fetchColumn();
if (!$role || $role === 'super_admin') {
    header('Location: view_members.php?error=protected');
    exit;
}

try {
    deleteUser($pdo, $id);
    header('Location: view_members.php?deleted=1');
    exit;
} catch (Throwable $e) {
    error_log('delete_member failed: ' . $e->getMessage());
    header('Location: view_members.php?error=delete_failed');
    exit;
}
