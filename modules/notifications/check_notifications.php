<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $out = ['ok' => true];

    // Announcements: count newer than last_seen_announcements
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM announcements WHERE date_posted > (SELECT COALESCE(last_seen_announcements, "1970-01-01") FROM users WHERE id = ?)');
    $stmt->execute([$userId]);
    $out['announcements'] = (int)$stmt->fetchColumn();

    // Prayers: count newer than last_seen_prayers
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM prayer_requests WHERE created_at > (SELECT COALESCE(last_seen_prayers, "1970-01-01") FROM users WHERE id = ?)');
    $stmt->execute([$userId]);
    $out['prayers'] = (int)$stmt->fetchColumn();

    // Activities
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM activities WHERE created_at > (SELECT COALESCE(last_seen_activities, "1970-01-01") FROM users WHERE id = ?)');
    $stmt->execute([$userId]);
    $out['activities'] = (int)$stmt->fetchColumn();

    // Resources
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM resources WHERE upload_date > (SELECT COALESCE(last_seen_resources, "1970-01-01") FROM users WHERE id = ?)');
    $stmt->execute([$userId]);
    $out['resources'] = (int)$stmt->fetchColumn();

    // Gallery images
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM gallery_images WHERE uploaded_at > (SELECT COALESCE(last_seen_gallery, "1970-01-01") FROM users WHERE id = ?)');
    $stmt->execute([$userId]);
    $out['gallery'] = (int)$stmt->fetchColumn();

    // Total notifications (exclude chat which has own endpoint)
    $out['total'] = $out['announcements'] + $out['prayers'] + $out['activities'] + $out['resources'] + $out['gallery'];

    echo json_encode($out);
} catch (Throwable $e) {
    error_log('check_notifications error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
