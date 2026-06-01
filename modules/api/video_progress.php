<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$videoId = $_POST['video_id'] ?? $_GET['video_id'] ?? '';
$userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

if (empty($action) || empty($videoId) || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    switch ($action) {
        case 'get':
            // Get video progress
            $stmt = $pdo->prepare("
                SELECT progress, completed, current_time, duration, updated_at 
                FROM video_progress 
                WHERE video_id = ? AND user_id = ?
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$videoId, $userId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'progress' => $progress ?: ['progress' => 0, 'completed' => false]
            ]);
            break;
            
        case 'save':
            // Save video progress
            $progress = (float)($_POST['progress'] ?? 0);
            $currentTime = (float)($_POST['current_time'] ?? 0);
            $duration = (float)($_POST['duration'] ?? 0);
            $completed = ($_POST['completed'] ?? '0') === '1';
            
            // Insert or update progress
            $stmt = $pdo->prepare("
                INSERT INTO video_progress (video_id, user_id, progress, current_time, duration, completed, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                progress = VALUES(progress),
                current_time = VALUES(current_time),
                duration = VALUES(duration),
                completed = VALUES(completed),
                updated_at = NOW()
            ");
            $stmt->execute([$videoId, $userId, $progress, $currentTime, $duration, $completed]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Progress saved',
                'progress' => $progress,
                'completed' => $completed
            ]);
            break;
            
        case 'complete':
            // Mark video as completed
            $stmt = $pdo->prepare("
                INSERT INTO video_progress (video_id, user_id, progress, completed, updated_at)
                VALUES (?, ?, 100, 1, NOW())
                ON DUPLICATE KEY UPDATE
                progress = 100,
                completed = 1,
                updated_at = NOW()
            ");
            $stmt->execute([$videoId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Video marked as completed',
                'completed' => true
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Video progress API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Video progress API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
