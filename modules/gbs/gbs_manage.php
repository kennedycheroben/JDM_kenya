<?php
/**
 * GBS Group Management - JDM Kenya
 *
 * Allows GBS Leaders to manage their group: add/remove members, update info.
 *
 * Permission Model:
 *   - Only GBS Leaders (role = 'leader') can access this page for their group.
 *   - Members and non-members are redirected to dashboard.
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/gbs_groups.php';

// --- Auth Check ---
if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$gbs_id = (int)($_POST['gbs_id'] ?? $_GET['id'] ?? 0);

if ($gbs_id <= 0) {
    header('Location: gbs_dashboard.php');
    exit;
}

// --- Permission Check: GBS Leaders manage their own group; JDM Leader can manage any group ---
$is_super_admin = ($user_role === 'super_admin');
if ($is_super_admin) {
    $stmt = $pdo->prepare("SELECT 'leader' AS role, name, leader_id FROM gbs_groups WHERE id = ? LIMIT 1");
    $stmt->execute([$gbs_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT gm.role, g.name, g.leader_id
        FROM gbs_members gm
        JOIN gbs_groups g ON gm.gbs_id = g.id
        WHERE gm.gbs_id = ? AND gm.user_id = ? AND gm.role = 'leader'
    ");
    $stmt->execute([$gbs_id, $user_id]);
}
$leadership = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leadership) {
    // Not a leader: redirect to dashboard
    header('Location: gbs_dashboard.php');
    exit;
}

// --- Handle Form Submissions ---
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'add_member':
                $member_id = (int)($_POST['user_id'] ?? 0); // ID of user to add
                if ($member_id > 0) {
                    // Check if user is already a member
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM gbs_members WHERE gbs_id = ? AND user_id = ?");
                    $check_stmt->execute([$gbs_id, $member_id]);
                    $count = $check_stmt->fetchColumn();
                    if ($count == 0) {
                        // Add new member as 'member' role
                        $stmt = $pdo->prepare("INSERT INTO gbs_members (gbs_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                        $stmt->execute([$gbs_id, $member_id]);
                        
                        // Notify the member via chat from the leader
                        try {
                            $groupName = $leadership['name'];
                            $notifText = "Welcome to **{$groupName}**! 👋\n\nI'm glad to have you join our Group Bible Study. We look forward to growing together in faith.\n\nYou can access our group page here:\ngbs_group.php?id={$gbs_id}";
                            
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$user_id, $member_id, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send GBS welcome message: " . $e->getMessage());
                        }

                        $message = 'Member added successfully!';
                    } else {
                        $error = 'User is already a member of this group.';
                    }
                }
                break;
                
            case 'remove_member':
                $member_id = (int)($_POST['user_id'] ?? 0);
                if ($member_id > 0) {
                    // Don't allow removing the group leader
                    if ($member_id != $leadership['leader_id']) {
                        $stmt = $pdo->prepare("
                            DELETE FROM gbs_members WHERE gbs_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$gbs_id, $member_id]);
                        $message = 'Member removed successfully!';
                    } else {
                        $error = 'Cannot remove the group leader.';
                    }
                }
                break;
                
            case 'upload_resource':
                $title = trim($_POST['title'] ?? '');
                
                if (empty($title)) {
                    $error = 'Resource title is required';
                } elseif (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = UPLOAD_DIR . 'gbs_resources/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = 'gbs_' . $gbs_id . '_' . time() . '_' . basename($_FILES['resource_file']['name']);
                    $target_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_path)) {
                        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                        $file_path = '/JDM_kenya/uploads/gbs_resources/' . $file_name;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO gbs_resources (gbs_id, title, file_path, file_type, uploaded_by, uploaded_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$gbs_id, $title, $file_path, $file_type, $user_id]);
                        $message = 'Resource uploaded successfully!';
                    } else {
                        $error = 'Failed to upload file';
                    }
                } else {
                    $error = 'Please select a file to upload';
                }
                break;
                
            case 'delete_resource':
                $resource_id = (int)($_POST['resource_id'] ?? 0);
                if ($resource_id > 0) {
                    // Get file path before deleting
                    $stmt = $pdo->prepare("
                        SELECT file_path FROM gbs_resources WHERE id = ? AND gbs_id = ?
                    ");
                    $stmt->execute([$resource_id, $gbs_id]);
                    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($resource) {
                        // Delete from database
                        $stmt = $pdo->prepare("
                            DELETE FROM gbs_resources WHERE id = ? AND gbs_id = ?
                        ");
                        $stmt->execute([$resource_id, $gbs_id]);
                        
                        // Delete file from filesystem
                        $file_path = $_SERVER['DOCUMENT_ROOT'] . $resource['file_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        
                        $message = 'Resource deleted successfully!';
                    }
                }
                break;
                
            case 'toggle_chat_restriction':
                $restricted = (int)($_POST['restricted'] ?? 0);
                $stmt = $pdo->prepare("UPDATE gbs_groups SET chat_restricted = ? WHERE id = ?");
                $stmt->execute([$restricted, $gbs_id]);
                $message = $restricted ? 'Chat restricted to leader only.' : 'Chat opened for all members.';
                break;

            case 'delete_gbs_group':
                if (!$is_super_admin && (int)$leadership['leader_id'] !== $user_id) {
                    $error = 'You can only delete GBS groups you own.';
                    break;
                }

                deleteGbsGroup($pdo, $gbs_id);
                header('Location: gbs_dashboard.php?message=' . urlencode('GBS group deleted successfully.'));
                exit;
        }
    } catch (Throwable $e) {
        $error = 'Operation failed: ' . $e->getMessage();
    }
}

// Redirect back to group page after processing
if ($message || $error) {
    header('Location: gbs_group.php?id=' . $gbs_id . '&message=' . urlencode($message) . '&error=' . urlencode($error));
    exit;
}

// If we get here, it's a GET request, redirect to group page
header('Location: gbs_group.php?id=' . $gbs_id);
exit;
?>
