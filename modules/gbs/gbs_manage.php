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

// --- Permission Check: Authorized GBS Leaders manage their own group; Super Admin can manage any group ---
$is_super_admin = ($user_role === 'super_admin');
$membership = getGbsGroupMembership($pdo, $gbs_id, $user_id, $user_role);

if (!$membership || empty($membership['can_manage'])) {
    header('Location: gbs_dashboard.php?error=' . urlencode('You are not authorized to manage that GBS group.'));
    exit;
}

$leadership = $membership;

// --- Handle Form Submissions ---
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) {
        header('Location: gbs_group.php?id=' . $gbs_id . '&error=' . urlencode('Security validation failed. Please refresh the page and try again.'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'add_member':
                $member_id = (int)($_POST['user_id'] ?? 0);
                addGbsMember($pdo, $gbs_id, $user_id, $user_role, $member_id);
                $message = 'Member added successfully!';
                break;

            case 'remove_member':
                $member_id = (int)($_POST['user_id'] ?? 0);
                removeGbsMember($pdo, $gbs_id, $user_id, $user_role, $member_id);
                $message = 'Member removed successfully!';
                break;

            case 'update_group':
                $groupName = trim((string)($_POST['name'] ?? ''));
                $slogan = trim((string)($_POST['slogan'] ?? ''));
                $newPfpPath = null;

                if (isset($_FILES['pfp']) && ($_FILES['pfp']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['pfp']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Failed to upload group image.';
                        break;
                    }
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                    $mimeType = ($finfo && is_uploaded_file($_FILES['pfp']['tmp_name']))
                        ? finfo_file($finfo, $_FILES['pfp']['tmp_name'])
                        : ($_FILES['pfp']['type'] ?? '');
                    if ($finfo) {
                        finfo_close($finfo);
                    }
                    if (!in_array($mimeType, $allowedTypes, true)) {
                        $error = 'Invalid image type. Only JPG, PNG, and WebP are allowed.';
                        break;
                    }
                    if (($_FILES['pfp']['size'] ?? 0) > 2 * 1024 * 1024) {
                        $error = 'Group image must be smaller than 2MB.';
                        break;
                    }
                    $upload_dir = UPLOAD_DIR . 'gbs/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $ext = strtolower(pathinfo((string)$_FILES['pfp']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $ext = 'jpg';
                    }
                    $filename = 'gbs_' . $gbs_id . '_' . time() . '.' . $ext;
                    $target_path = $upload_dir . $filename;
                    if (!move_uploaded_file($_FILES['pfp']['tmp_name'], $target_path)) {
                        $error = 'Failed to save uploaded group image.';
                        break;
                    }
                    $newPfpPath = 'uploads/gbs/' . $filename;
                }

                updateGbsGroup($pdo, $gbs_id, $user_id, $user_role, $groupName, $slogan, $newPfpPath);
                $message = 'Group information updated successfully!';
                break;

            case 'upload_resource':
            case 'add_resource':
                $title = trim($_POST['title'] ?? '');

                if (empty($title)) {
                    $error = 'Resource title is required';
                } elseif (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                    $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'mp4', 'mp3'];
                    $ext = strtolower(pathinfo((string)$_FILES['resource_file']['name'], PATHINFO_EXTENSION));
                    $fileSize = (int)($_FILES['resource_file']['size'] ?? 0);

                    if (!in_array($ext, $allowedExt, true)) {
                        $error = 'File type not allowed. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG, MP4, MP3.';
                        break;
                    }
                    if ($fileSize <= 0 || $fileSize > 10 * 1024 * 1024) {
                        $error = 'Resource file must be smaller than 10MB.';
                        break;
                    }

                    $upload_dir = UPLOAD_DIR . 'gbs_resources/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo((string)$_FILES['resource_file']['name'], PATHINFO_FILENAME));
                    $safeBase = substr(trim((string)$safeBase, '_'), 0, 60) ?: 'resource';
                    $file_name = 'gbs_' . $gbs_id . '_' . time() . '_' . $safeBase . '.' . $ext;
                    $target_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_path)) {
                        $file_path = 'uploads/gbs_resources/' . $file_name;

                        $stmt = $pdo->prepare("
                            INSERT INTO gbs_resources (gbs_id, title, file_path, file_type, uploaded_by, uploaded_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$gbs_id, $title, $file_path, $ext, $user_id]);
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
                    $stmt = $pdo->prepare("
                        SELECT file_path FROM gbs_resources WHERE id = ? AND gbs_id = ?
                    ");
                    $stmt->execute([$resource_id, $gbs_id]);
                    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($resource) {
                        $stmt = $pdo->prepare("
                            DELETE FROM gbs_resources WHERE id = ? AND gbs_id = ?
                        ");
                        $stmt->execute([$resource_id, $gbs_id]);

                        $rawPath = (string)($resource['file_path'] ?? '');
                        if (BASE_PATH !== '' && strpos($rawPath, BASE_PATH . '/') === 0) {
                            $rawPath = substr($rawPath, strlen(BASE_PATH) + 1);
                        }
                        $rawPath = ltrim($rawPath, '/');
                        $baseUploadsDir = realpath(UPLOAD_DIR . 'gbs_resources');
                        $resolvedPath = $rawPath !== '' ? realpath(__DIR__ . '/../../' . $rawPath) : false;
                        if ($baseUploadsDir && $resolvedPath && strpos($resolvedPath, $baseUploadsDir) === 0 && is_file($resolvedPath)) {
                            @unlink($resolvedPath);
                        }

                        $message = 'Resource deleted successfully!';
                    }
                }
                break;

            case 'add_announcement':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if ($title === '' || $content === '') {
                    $error = 'Title and content are required.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO gbs_announcements (gbs_id, author_id, title, content, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$gbs_id, $user_id, $title, $content]);
                    $message = 'Announcement added successfully!';
                }
                break;

            case 'toggle_chat_restriction':
                $restricted = (int)($_POST['restricted'] ?? 0) ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE gbs_groups SET chat_restricted = ? WHERE id = ?");
                $stmt->execute([$restricted, $gbs_id]);
                $message = $restricted ? 'Chat restricted to leader only.' : 'Chat opened for all members.';
                break;

            case 'delete_gbs_group':
                if (empty($membership['can_delete'])) {
                    $error = 'You can only delete GBS groups you own.';
                    break;
                }

                deleteGbsGroup($pdo, $gbs_id);
                header('Location: gbs_dashboard.php?message=' . urlencode('GBS group deleted successfully.'));
                exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
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
