<?php

function sports_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function sports_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$column]);return (bool)$stmt->fetchColumn();
}

function sports_is_super_admin(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'super_admin' LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function sports_is_admin(PDO $pdo, int $userId): bool
{
    if ($userId <= 0 || !sports_table_exists($pdo, 'sports_admin_assignments')) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM sports_admin_assignments WHERE user_id = ? AND status = 'active' AND ended_at IS NULL LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function sports_can_admin(PDO $pdo, int $userId): bool
{
    return sports_is_super_admin($pdo, $userId) || sports_is_admin($pdo, $userId);
}

function sports_application_for_user(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !sports_table_exists($pdo, 'sports_applications')) return null;
    if (sports_column_exists($pdo,'sports_applications','application_source')) {
        $stmt=$pdo->prepare('SELECT id,user_id,application_source,status,submitted_at,applicant_message FROM sports_applications WHERE user_id=? ORDER BY id DESC LIMIT 1');
    } else {
        $stmt=$pdo->prepare("SELECT a.id,a.user_id,CASE WHEN u.category='sports_ministry' THEN 'new_user' ELSE 'existing_user' END application_source,a.status,a.submitted_at,a.applicant_message FROM sports_applications a JOIN users u ON u.id=a.user_id WHERE a.user_id=? ORDER BY a.id DESC LIMIT 1");
    }
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function sports_require_admin(PDO $pdo): int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !sports_can_admin($pdo, $userId)) {
        http_response_code(403);
        exit('You are not authorized to manage Sports Ministry.');
    }
    return $userId;
}

function sports_application_restricts_portal(?array $application): bool
{
    return $application !== null
        && ($application['application_source'] ?? '') === 'new_user'
        && in_array($application['status'] ?? '', ['pending','rejected','withdrawn'], true);
}

function sports_apply_central_access_gate(PDO $pdo): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return;
    $application = sports_application_for_user($pdo, $userId);
    if (!sports_application_restricts_portal($application)) return;

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = ['sports_application_status.php', 'join_sports_ministry.php', 'sports.php', 'sports_player.php', 'sports_rules.php', 'logout.php'];
    if (!in_array($script, $allowed, true)) {
        header('Location: ' . BASE_PATH . '/sports_application_status.php');
        exit;
    }
}
