<?php
// Production error reporting: log errors to server logs instead of displaying in HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/contact_messages.php';
require_once dirname(__FILE__) . '/../../core/downloads.php';

if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

ensureContactMessagesTable($pdo);

// Helper to validate and store an uploaded image file
function handleUploadedImage(array $file, string $subDir, string $prefix, string &$errorOut): ?string {
    $errorOut = '';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorOut = 'Image upload failed. Please try again.';
        return null;
    }
    $maxSize = 5 * 1024 * 1024; // 5MB
    if (($file['size'] ?? 0) > $maxSize) {
        $errorOut = 'Image file is too large. Maximum allowed size is 5MB.';
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        $errorOut = 'Invalid image format. Only JPG, PNG, or WEBP files are allowed.';
        return null;
    }
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMime, true)) {
            $errorOut = 'Invalid image file content. Please upload a valid JPG, PNG, or WEBP image.';
            return null;
        }
    }
    $cleanSubDir = trim($subDir, '/') . '/';
    $destinationDir = UPLOAD_DIR . $cleanSubDir;
    if (!is_dir($destinationDir)) {
        @mkdir($destinationDir, 0777, true);
        @chmod($destinationDir, 0777);
    }
    if (!is_writable($destinationDir)) {
        @chmod($destinationDir, 0777);
    }
    if (!is_dir($destinationDir) || !is_writable($destinationDir)) {
        $errorOut = 'Upload directory is not writable. Please check permissions for uploads/' . $cleanSubDir;
        return null;
    }
    $safeName = basename($file['name']);
    $targetName = uniqid($prefix, true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeName);
    $destination = $destinationDir . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errorOut = 'Unable to save the uploaded image. Please try again.';
        return null;
    }
    return 'uploads/' . $cleanSubDir . $targetName;
}

// Helper to safely delete an old uploaded file within UPLOAD_DIR
function deleteUploadedFileIfLocal(?string $relativePath, string $expectedSubDir): void {
    if (empty($relativePath) || !is_string($relativePath)) {
        return;
    }
    $cleanRel = ltrim($relativePath, '/');
    $expectedPrefix = 'uploads/' . trim($expectedSubDir, '/') . '/';
    if (strpos($cleanRel, $expectedPrefix) !== 0) {
        return;
    }
    $subPath = substr($cleanRel, strlen('uploads/'));
    $fullPath = UPLOAD_DIR . $subPath;
    $realUploadDir = realpath(UPLOAD_DIR);
    $realFile = realpath($fullPath);
    if ($realUploadDir && $realFile && strpos($realFile, $realUploadDir . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
        @unlink($realFile);
    }
}

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_activity') {
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $uploadError = '';
        $imagePath = null;

        if (!empty($_FILES['activity_image']['name']) || (($_FILES['activity_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $imagePath = handleUploadedImage($_FILES['activity_image'], 'activities', 'act_', $uploadError);
        }

        if ($uploadError !== '') {
            $message = $uploadError;
            $tab = 'activities';
        } elseif ($title && $date && $content) {
            try {
                $stmt = $pdo->prepare('INSERT INTO activities (title, date, content, image, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$title, $date, $content, $imagePath, $_SESSION['user_id']]);
                $message = 'Activity added successfully.';
            } catch (PDOException $e) {
                if ($imagePath) {
                    deleteUploadedFileIfLocal($imagePath, 'activities');
                }
                error_log('Admin dashboard add activity error: ' . $e->getMessage());
                $message = 'A database error occurred while saving the activity. Please try again.';
            }
            $tab = 'activities';
        } else {
            if ($imagePath) {
                deleteUploadedFileIfLocal($imagePath, 'activities');
            }
            $message = 'Please complete all required fields for the activity.';
            $tab = 'activities';
        }
    }

    if ($action === 'update_activity') {
        $id = intval($_POST['activity_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $removeImage = !empty($_POST['remove_image']);

        if ($id && $title && $date && $content) {
            $existingStmt = $pdo->prepare('SELECT image FROM activities WHERE id = ? LIMIT 1');
            $existingStmt->execute([$id]);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);
            $currentImage = $existingRow['image'] ?? null;

            $uploadError = '';
            $newImagePath = null;
            $hasNewUpload = !empty($_FILES['activity_image']['name']) || (($_FILES['activity_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

            if ($hasNewUpload) {
                $newImagePath = handleUploadedImage($_FILES['activity_image'], 'activities', 'act_', $uploadError);
            }

            if ($uploadError !== '') {
                $message = $uploadError;
            } else {
                $finalImage = $currentImage;
                if ($newImagePath !== null) {
                    $finalImage = $newImagePath;
                } elseif ($removeImage) {
                    $finalImage = null;
                }

                $stmt = $pdo->prepare('UPDATE activities SET title = ?, date = ?, content = ?, image = ? WHERE id = ?');
                $stmt->execute([$title, $date, $content, $finalImage, $id]);

                if (($newImagePath !== null || $removeImage) && !empty($currentImage) && $currentImage !== $finalImage) {
                    deleteUploadedFileIfLocal($currentImage, 'activities');
                }
                $message = 'Activity updated successfully.';
            }
        } else {
            $message = 'Please provide valid activity details.';
        }
        $tab = 'activities';
    }

    if ($action === 'delete_activity') {
        $id = intval($_POST['activity_id'] ?? 0);
        if ($id) {
            $existingStmt = $pdo->prepare('SELECT image FROM activities WHERE id = ? LIMIT 1');
            $existingStmt->execute([$id]);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare('DELETE FROM activities WHERE id = ?');
            $stmt->execute([$id]);

            if (!empty($existingRow['image'])) {
                deleteUploadedFileIfLocal($existingRow['image'], 'activities');
            }
            $message = 'Activity deleted successfully.';
        }
        $tab = 'activities';
    }

    if ($action === 'upload_resource' && !empty($_FILES['resource_file']['name'])) {
        $file = $_FILES['resource_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $category = strtolower(trim($_POST['resource_category'] ?? 'pdf_resource'));
        $allowedCategories = ['study_material', 'pdf_resource', 'video_content'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'pdf_resource';
        }

        $allowedExt = ($category === 'video_content') ? ['mp4'] : ['pdf'];

        if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, $allowedExt, true)) {
            $safeName = basename($file['name']);
            $targetName = uniqid('jdm_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeName);
            $destinationDir = UPLOAD_DIR . 'resources/';
            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0777, true);
                @chmod($destinationDir, 0777);
            }
            if (!is_writable($destinationDir)) {
                @chmod($destinationDir, 0777);
            }
            if (!is_dir($destinationDir) || !is_writable($destinationDir)) {
                $message = 'Upload directory is not writable. Please fix permissions for uploads/resources/.';
            } else {
            $destination = $destinationDir . $targetName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $title = trim($_POST['resource_title'] ?? '');
                if ($title === '') {
                    $title = pathinfo($safeName, PATHINFO_FILENAME);
                }
                $fileType = ($category === 'video_content') ? 'mp4' : 'pdf';
                try {
                    $stmt = $pdo->prepare('INSERT INTO resources (title, category, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $category, 'uploads/resources/' . $targetName, $fileType, $_SESSION['user_id']]);
                    $message = 'Resource uploaded successfully.';
                } catch (PDOException $e) {
                    error_log('Admin dashboard upload resource error: ' . $e->getMessage());
                    $message = 'A database error occurred while saving the resource. Please try again.';
                }
            } else {
                $message = 'Unable to upload the file. Please try again.';
            }
            }
        } else {
            $message = 'Invalid file type for selected category. PDFs for Study/PDF, MP4 for Video.';
        }
    }

    if ($action === 'add_announcement') {
        $title = trim($_POST['announcement_title'] ?? '');
        $content = trim($_POST['announcement_content'] ?? '');
        if ($title === '' || $content === '') {
            $message = 'Please provide both title and content for the announcement.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)');
                $stmt->execute([$title, $content, $_SESSION['user_id']]);
                $message = 'Announcement posted successfully.';
            } catch (PDOException $e) {
                error_log('Admin dashboard add activity error: ' . $e->getMessage());
                $message = 'A database error occurred while saving the activity. Please try again.';
            }
        }
    }

    if ($action === 'upload_gallery') {
        if (empty($_FILES['gallery_image']['name']) && (($_FILES['gallery_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            $message = 'Please select an image file to upload.';
        } else {
            $uploadError = '';
            $galleryPath = handleUploadedImage($_FILES['gallery_image'], 'gallery', 'gal_', $uploadError);
            if ($uploadError !== '') {
                $message = $uploadError;
            } elseif ($galleryPath !== null) {
                $title = trim($_POST['gallery_title'] ?? '');
                try {
                    $stmt = $pdo->prepare('INSERT INTO gallery_images (title, file_path, uploaded_by) VALUES (?, ?, ?)');
                    $stmt->execute([$title !== '' ? $title : null, $galleryPath, $_SESSION['user_id']]);
                    $message = 'Gallery image uploaded successfully.';
                } catch (PDOException $e) {
                    deleteUploadedFileIfLocal($galleryPath, 'gallery');
                    error_log('Admin dashboard upload gallery error: ' . $e->getMessage());
                    $message = 'A database error occurred while saving the gallery image. Please try again.';
                }
            }
        }
        $tab = 'gallery';
    }

    if ($action === 'mark_contact_read') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = COALESCE(read_at, NOW()) WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message marked as read.';
            $tab = 'messages';
        }
    }

    if ($action === 'mark_contact_unread') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'unread', read_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message marked as unread.';
            $tab = 'messages';
        }
    }

    if ($action === 'archive_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'archived', archived_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message archived.';
            $tab = 'messages';
        }
    }

    if ($action === 'unarchive_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read', archived_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message moved back to inbox.';
            $tab = 'messages';
        }
    }

    if ($action === 'trash_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message moved to Trash.';
            $tab = 'messages';
        }
    }

    if ($action === 'restore_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Contact message restored.';
            $tab = 'messages';
        }
    }

    if ($action === 'delete_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Contact message permanently deleted.';
            $tab = 'messages';
        }
    }

    if ($action === 'bulk_contact_messages') {
        $bulkAction = trim($_POST['bulk_action'] ?? '');
        $rawIds = (array)($_POST['message_ids'] ?? []);
        $cleanIds = array_values(array_filter(array_map('intval', $rawIds), fn($val) => $val > 0));

        if (empty($cleanIds)) {
            $message = 'No messages selected for bulk operation.';
            $tab = 'messages';
        } else {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $pdo->beginTransaction();
            try {
                if ($bulkAction === 'mark_read') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = COALESCE(read_at, NOW()) WHERE id IN ($placeholders) AND deleted_at IS NULL");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) marked as read.";
                } elseif ($bulkAction === 'mark_unread') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'unread', read_at = NULL WHERE id IN ($placeholders) AND deleted_at IS NULL");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) marked as unread.";
                } elseif ($bulkAction === 'archive') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'archived', archived_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) archived.";
                } elseif ($bulkAction === 'unarchive') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read', archived_at = NULL WHERE id IN ($placeholders) AND deleted_at IS NULL");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) moved to inbox.";
                } elseif ($bulkAction === 'trash') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET deleted_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) moved to Trash.";
                } elseif ($bulkAction === 'restore') {
                    $stmt = $pdo->prepare("UPDATE contact_messages SET deleted_at = NULL WHERE id IN ($placeholders)");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) restored.";
                } elseif ($bulkAction === 'delete_permanent') {
                    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders)");
                    $stmt->execute($cleanIds);
                    $affected = $stmt->rowCount();
                    $message = "$affected message(s) permanently deleted.";
                } else {
                    $message = 'Invalid bulk operation.';
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Bulk contact messages error: ' . $e->getMessage());
                $message = 'An error occurred during the bulk operation. Please try again.';
            }
            $tab = 'messages';
        }
    }
}

// Fetch data
$activities = $pdo->query('SELECT * FROM activities ORDER BY date DESC')->fetchAll(PDO::FETCH_ASSOC);
$resources = $pdo->query('SELECT * FROM resources ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);
$announcements = $pdo->query('SELECT *, COALESCE(date_created, date_posted) AS date_created_safe FROM announcements ORDER BY COALESCE(date_created, date_posted) DESC, id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$galleryImages = $pdo->query('SELECT * FROM gallery_images ORDER BY uploaded_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
// Contact messages filtering, searching, and pagination
$msgFilter = trim($_GET['msg_filter'] ?? 'all');
$validFilters = ['all', 'unread', 'read', 'archived', 'trash'];
if (!in_array($msgFilter, $validFilters, true)) {
    $msgFilter = 'all';
}
$msgSearch = trim($_GET['search'] ?? '');
$msgPage = max(1, (int)($_GET['p'] ?? 1));
$perPage = 15;

$whereClauses = [];
$queryParams = [];

if ($msgFilter === 'unread') {
    $whereClauses[] = "deleted_at IS NULL AND status = 'unread'";
} elseif ($msgFilter === 'read') {
    $whereClauses[] = "deleted_at IS NULL AND status = 'read' AND archived_at IS NULL";
} elseif ($msgFilter === 'archived') {
    $whereClauses[] = "deleted_at IS NULL AND (status = 'archived' OR archived_at IS NOT NULL)";
} elseif ($msgFilter === 'trash') {
    $whereClauses[] = "deleted_at IS NOT NULL";
} else {
    // 'all' / inbox: active, non-archived, non-trash messages
    $whereClauses[] = "deleted_at IS NULL AND (status != 'archived' AND archived_at IS NULL)";
}

if ($msgSearch !== '') {
    $whereClauses[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $likePattern = '%' . $msgSearch . '%';
    $queryParams[] = $likePattern;
    $queryParams[] = $likePattern;
    $queryParams[] = $likePattern;
    $queryParams[] = $likePattern;
}

$whereSql = implode(' AND ', $whereClauses);

// Count matching records for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE $whereSql");
$countStmt->execute($queryParams);
$totalContactMessages = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalContactMessages / $perPage));
if ($msgPage > $totalPages) {
    $msgPage = $totalPages;
}
$offset = ($msgPage - 1) * $perPage;

// Fetch current page of messages
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?");
foreach ($queryParams as $i => $paramVal) {
    $stmt->bindValue($i + 1, $paramVal, PDO::PARAM_STR);
}
$stmt->bindValue(count($queryParams) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($queryParams) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$contactMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for filter tab badges
$tabCounts = [
    'all' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NULL AND (status != 'archived' AND archived_at IS NULL)")->fetchColumn(),
    'unread' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NULL AND status = 'unread'")->fetchColumn(),
    'read' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NULL AND status = 'read' AND archived_at IS NULL")->fetchColumn(),
    'archived' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NULL AND (status = 'archived' OR archived_at IS NOT NULL)")->fetchColumn(),
    'trash' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NOT NULL")->fetchColumn(),
];
$unreadContactMessages = $tabCounts['unread'];

// Get editing activity
$editing = null;
if (isset($_GET['edit']) && intval($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM activities WHERE id = ? LIMIT 1');
    $stmt->execute([intval($_GET['edit'])]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-md-12">
        <h2><img src="<?= BASE_PATH ?>/images/jdm_logo.png" alt="JDM Logo" class="logo-img" style="height: 40px; width: auto;"> Admin Dashboard</h2>
        <p class="text-muted">Manage Dashboard content, members, and resources</p>
    </div>
</div>

<?php 
$is_link_valid = !isset($_GET['exp']) || (is_numeric($_GET['exp']) && (int)$_GET['exp'] >= time());
if (isset($_GET['new_admin']) && $is_link_valid): 
?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f8d7da 0%, #f8f9fa 100%);">
        <div class="d-flex align-items-center">
            <div class="display-6 me-3">🛡️</div>
            <div>
                <h4 class="alert-heading fw-bold mb-1" style="color: #842029;">Welcome, Admin!</h4>
                <p class="mb-0 fw-medium" style="color: #842029;">You have been promoted to Admin. You can now manage dashboard activities, resources, and announcements.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="btn-group" role="group">
            <a href="admin_dashboard.php?tab=dashboard" class="btn btn-<?= $tab === 'dashboard' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <a href="admin_dashboard.php?tab=activities" class="btn btn-<?= $tab === 'activities' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-calendar-event"></i> Activities
            </a>
            <a href="admin_dashboard.php?tab=resources" class="btn btn-<?= $tab === 'resources' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-file-pdf"></i> Resources
            </a>
            <a href="admin_dashboard.php?tab=announcements" class="btn btn-<?= $tab === 'announcements' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-megaphone"></i> Announcements
            </a>
            <a href="admin_dashboard.php?tab=gallery" class="btn btn-<?= $tab === 'gallery' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-images"></i> Gallery
            </a>
            <a href="admin_dashboard.php?tab=messages" class="btn btn-<?= $tab === 'messages' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-envelope"></i> Messages
                <?php if ($unreadContactMessages > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unreadContactMessages ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle"></i> <?= escape($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Dashboard Tab -->
<?php if ($tab === 'dashboard'): ?>
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card stat-card">
                <i class="bi bi-calendar-event text-info"></i>
                <h5>Total Activities</h5>
                <h3 class="text-info"><?= count($activities) ?></h3>
                <p>Upcoming events</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card">
                <i class="bi bi-file-pdf text-success"></i>
                <h5>Resources</h5>
                <h3 class="text-success"><?= count($resources) ?></h3>
                <p>PDF files</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card">
                <i class="bi bi-megaphone text-warning"></i>
                <h5>Announcements</h5>
                <h3 class="text-warning"><?= count($announcements) ?></h3>
                <p>Newsroom</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card">
                <i class="bi bi-images text-danger"></i>
                <h5>Gallery</h5>
                <h3 class="text-danger"><?= count($galleryImages) ?></h3>
                <p>Photos</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card">
                <i class="bi bi-envelope text-primary"></i>
                <h5>Contact Messages</h5>
                <h3 class="text-primary"><?= $unreadContactMessages ?></h3>
                <p>Unread messages</p>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <a href="view_members.php" class="btn btn-primary me-2 mb-2">
                        <i class="bi bi-people"></i> View Members
                    </a>
                    <a href="admin_dashboard.php?tab=activities" class="btn btn-info me-2 mb-2">
                        <i class="bi bi-plus-circle"></i> Add Activity
                    </a>
                    <a href="admin_dashboard.php?tab=resources" class="btn btn-success me-2 mb-2">
                        <i class="bi bi-upload"></i> Upload Resource
                    </a>
                    <a href="admin_dashboard.php?tab=messages" class="btn btn-outline-primary me-2 mb-2">
                        <i class="bi bi-envelope"></i> View Messages
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Activities Tab -->
<?php if ($tab === 'activities'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> <?= $editing ? 'Edit Activity' : 'Add New Activity' ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="needs-validation">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $editing ? 'update_activity' : 'add_activity' ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="activity_id" value="<?= $editing['id'] ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" value="<?= escape($editing['title'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= escape($editing['date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Activity Image (Optional)</label>
                                <input type="file" name="activity_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                                <small class="text-muted d-block mt-1">JPG, PNG, or WEBP (Max 5MB)</small>
                                <?php
                                $editingImageExists = !empty($editing['image']) && is_file(dirname(__DIR__, 2) . '/' . ltrim($editing['image'], '/'));
                                if (!empty($editing['image'])):
                                ?>
                                    <div class="mt-2 d-flex align-items-center gap-2 p-2 bg-light rounded border">
                                        <?php if ($editingImageExists): ?>
                                            <img src="<?= escape($editing['image']) ?>" alt="Current activity image" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                                <i class="bi bi-image text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1 small">
                                            <div class="text-truncate"><?= escape(basename($editing['image'])) ?></div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeActivityImage">
                                                <label class="form-check-label text-danger" for="removeActivityImage">Remove current image</label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="content" rows="4" class="form-control" required><?= escape($editing['content'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> <?= $editing ? 'Update Activity' : 'Add Activity' ?>
                            </button>
                            <?php if ($editing): ?>
                                <a href="admin_dashboard.php?tab=activities" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Activities List (<?= count($activities) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 shadow-sm">
                                        <?php if (!empty($activity['image']) && is_file(dirname(__DIR__, 2) . '/' . ltrim($activity['image'], '/'))): ?>
                                            <img src="<?= escape($activity['image']) ?>" class="card-img-top" alt="Activity" style="height: 150px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                                <i class="bi bi-calendar-event text-secondary" style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= escape($activity['title']) ?></h6>
                                            <p class="card-text small text-muted"><?= date('M d, Y', strtotime($activity['date'])) ?></p>
                                            <p class="card-text small"><?= escape(mb_strimwidth($activity['content'], 0, 80, '...')) ?></p>
                                        </div>
                                        <div class="card-footer bg-transparent border-top">
                                            <a href="admin_dashboard.php?tab=activities&edit=<?= $activity['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                                                                        <form method="post" style="display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_activity">

                                            
                                                <input type="hidden" name="activity_id" value="<?= $activity['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">No activities yet. Create one above!</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Resources Tab -->
<?php if ($tab === 'resources'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-upload"></i> Upload Resource</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_resource">
                        <div class="mb-3">
                            <label class="form-label">Resource Title</label>
                            <input type="text" name="resource_title" class="form-control" placeholder="e.g. Discipleship Guide Week 1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="resource_category" class="form-select" required>
                                <option value="study_material">Study Materials (PDF)</option>
                                <option value="pdf_resource" selected>PDF Resources (PDF)</option>
                                <option value="video_content">Video Content (MP4)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" name="resource_file" class="form-control" accept="application/pdf,video/mp4" required>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-cloud-upload"></i> Upload Resource
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Resources (<?= count($resources) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($resources) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Uploaded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $icon = 'bi-file-earmark-pdf text-danger';
                                                switch(($resource['category'] ?? 'pdf_resource')) {
                                                    case 'study_material':
                                                        $icon = 'bi-book text-primary';
                                                        break;
                                                    case 'video_content':
                                                        $icon = 'bi-play-circle text-success';
                                                        break;
                                                }
                                                ?>
                                                <i class="bi <?= $icon ?>"></i> <?= escape($resource['title']) ?>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= escape($resource['category'] ?? 'pdf_resource') ?></span></td>
                                            <td><?= date('M d, Y', strtotime($resource['upload_date'])) ?></td>
                                            <td>
                                                <a href="<?= escape(download_url($resource['file_path'], $resource['title'])) ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No resources uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Announcements Tab -->
<?php if ($tab === 'announcements'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-megaphone"></i> Post New Announcement</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_announcement">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="announcement_title" class="form-control" placeholder="Announcement title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="announcement_content" class="form-control" rows="5" placeholder="Announcement content" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Post Announcement
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Recent Announcements</h5>
                </div>
                <div class="card-body">
                    <?php if (count($announcements) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $ann): ?>
                                <div class="list-group-item announcement-item p-3">
                                    <h6 class="mb-2 fw-bold"><?= escape($ann['title']) ?></h6>
                                    <?php if (mb_strlen($ann['content']) > 150): ?>
                                        <details class="announcement-toggle mb-1">
                                            <summary>
                                                <span class="announcement-preview"><?= escape(mb_substr($ann['content'], 0, 150)) ?>&hellip;</span>
                                                <span class="mt-1 text-primary fw-semibold announcement-more">View more</span>
                                                <span class="mt-1 text-primary fw-semibold announcement-less">View less</span>
                                            </summary>
                                            <div class="announcement-full mt-2"><?= nl2br(escape($ann['content'])) ?></div>
                                        </details>
                                    <?php else: ?>
                                        <div class="announcement-full mb-1"><?= nl2br(escape($ann['content'])) ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($ann['date_created_safe'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No announcements yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Contact Messages Tab (Upgraded Inbox) -->
<?php if ($tab === 'messages'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0"><i class="bi bi-inbox-fill"></i> Contact Messages Inbox</h5>
                        <?php if ($unreadContactMessages > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $unreadContactMessages ?> unread</span>
                        <?php endif; ?>
                    </div>
                    <form method="get" class="d-flex align-items-center gap-2 m-0" style="max-width: 380px;">
                        <input type="hidden" name="tab" value="messages">
                        <input type="hidden" name="msg_filter" value="<?= escape($msgFilter) ?>">
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Search sender, email, subject..." value="<?= escape($msgSearch) ?>">
                            <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
                            <?php if ($msgSearch !== ''): ?>
                                <a href="admin_dashboard.php?tab=messages&msg_filter=<?= escape($msgFilter) ?>" class="btn btn-outline-light" title="Clear search"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="card-body p-3 p-md-4">
                    <!-- Filter Pills -->
                    <ul class="nav nav-pills mb-3 border-bottom pb-3 gap-1">
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $msgFilter === 'all' ? 'active' : '' ?>" href="admin_dashboard.php?tab=messages&msg_filter=all<?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">
                                <i class="bi bi-inbox"></i> Inbox <span class="badge bg-<?= $msgFilter === 'all' ? 'light text-dark' : 'secondary' ?> ms-1"><?= $tabCounts['all'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $msgFilter === 'unread' ? 'active' : '' ?>" href="admin_dashboard.php?tab=messages&msg_filter=unread<?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">
                                <i class="bi bi-envelope"></i> Unread <span class="badge bg-<?= $msgFilter === 'unread' ? 'light text-dark' : 'danger' ?> ms-1"><?= $tabCounts['unread'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $msgFilter === 'read' ? 'active' : '' ?>" href="admin_dashboard.php?tab=messages&msg_filter=read<?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">
                                <i class="bi bi-envelope-open"></i> Read <span class="badge bg-<?= $msgFilter === 'read' ? 'light text-dark' : 'secondary' ?> ms-1"><?= $tabCounts['read'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $msgFilter === 'archived' ? 'active' : '' ?>" href="admin_dashboard.php?tab=messages&msg_filter=archived<?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">
                                <i class="bi bi-archive"></i> Archived <span class="badge bg-<?= $msgFilter === 'archived' ? 'light text-dark' : 'secondary' ?> ms-1"><?= $tabCounts['archived'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $msgFilter === 'trash' ? 'active' : '' ?>" href="admin_dashboard.php?tab=messages&msg_filter=trash<?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">
                                <i class="bi bi-trash"></i> Trash <span class="badge bg-<?= $msgFilter === 'trash' ? 'light text-dark' : 'secondary' ?> ms-1"><?= $tabCounts['trash'] ?></span>
                            </a>
                        </li>
                    </ul>

                    <?php if (count($contactMessages) > 0): ?>
                        <form id="bulkMessagesForm" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="bulk_contact_messages">
                            <input type="hidden" id="bulkActionInput" name="bulk_action" value="">

                            <!-- Bulk Toolbar -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 mb-3 bg-light rounded border">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check ms-1 mb-0">
                                        <input class="form-check-input" type="checkbox" id="selectAllMessages">
                                        <label class="form-check-label small fw-semibold user-select-none" for="selectAllMessages">
                                            Select all visible
                                        </label>
                                    </div>
                                    <span id="selectedCountInfo" class="badge bg-secondary d-none">0 selected</span>
                                    <button type="button" id="btnClearSelection" class="btn btn-link btn-sm p-0 text-decoration-none d-none small">Clear</button>
                                </div>
                                <div id="bulkActionsGroup" class="d-flex flex-wrap align-items-center gap-1 d-none">
                                    <?php if ($msgFilter !== 'trash'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="submitBulkAction('mark_read');">
                                            <i class="bi bi-check2-all"></i> Mark Read
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="submitBulkAction('mark_unread');">
                                            <i class="bi bi-envelope"></i> Mark Unread
                                        </button>
                                        <?php if ($msgFilter === 'archived'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="submitBulkAction('unarchive');">
                                                <i class="bi bi-inbox"></i> Move to Inbox
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning text-dark" onclick="submitBulkAction('archive');">
                                                <i class="bi bi-archive"></i> Archive
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Move selected message(s) to Trash?')) submitBulkAction('trash');">
                                            <i class="bi bi-trash"></i> Move to Trash
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="submitBulkAction('restore');">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="if(confirm('Permanently delete selected message(s)? This action cannot be undone.')) submitBulkAction('delete_permanent');">
                                            <i class="bi bi-trash-fill"></i> Delete Permanently
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th style="width: 100px;">Status</th>
                                            <th>Sender</th>
                                            <th>Subject</th>
                                            <th style="min-width: 280px;">Message</th>
                                            <th style="width: 150px;">Received</th>
                                            <th class="text-end" style="min-width: 140px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contactMessages as $contactMessage): ?>
                                            <?php
                                                $isMsgUnread = ($contactMessage['status'] === 'unread' && empty($contactMessage['deleted_at']));
                                                $isMsgArchived = (!empty($contactMessage['archived_at']) || $contactMessage['status'] === 'archived');
                                                $isMsgTrash = !empty($contactMessage['deleted_at']);
                                            ?>
                                            <tr class="<?= $isMsgUnread ? 'table-warning' : '' ?>">
                                                <td>
                                                    <input type="checkbox" name="message_ids[]" value="<?= (int)$contactMessage['id'] ?>" class="form-check-input msg-select-box">
                                                </td>
                                                <td>
                                                    <?php if ($isMsgTrash): ?>
                                                        <span class="badge bg-dark">Trash</span>
                                                    <?php elseif ($isMsgArchived): ?>
                                                        <span class="badge bg-warning text-dark">Archived</span>
                                                    <?php elseif ($isMsgUnread): ?>
                                                        <span class="badge bg-danger">Unread</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= escape($contactMessage['name']) ?></strong><br>
                                                    <a href="mailto:<?= escape($contactMessage['email']) ?>" class="small text-decoration-none">
                                                        <?= escape($contactMessage['email']) ?>
                                                    </a>
                                                </td>
                                                <td><?= escape($contactMessage['subject']) ?></td>
                                                <td>
                                                    <details class="announcement-toggle mb-1">
                                                        <summary>
                                                            <span class="announcement-preview"><?= escape(mb_substr($contactMessage['message'], 0, 100)) ?>&hellip;</span>
                                                            <span class="mt-1 text-primary fw-semibold announcement-more">View more</span>
                                                            <span class="mt-1 text-primary fw-semibold announcement-less">View less</span>
                                                        </summary>
                                                        <div class="announcement-full mt-2"><?= nl2br(escape($contactMessage['message'])) ?></div>
                                                    </details>
                                                </td>
                                                <td><small class="text-muted"><?= date('M d, Y H:i', strtotime($contactMessage['created_at'])) ?></small></td>
                                                <td class="text-end">
                                                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                        <?php if (!$isMsgTrash): ?>
                                                            <?php if ($isMsgUnread): ?>
                                                                <button type="submit" formaction="" name="single_action_mark_read" onclick="submitSingleAction('mark_contact_read', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                                    <i class="bi bi-check2"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" formaction="" name="single_action_mark_unread" onclick="submitSingleAction('mark_contact_unread', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-secondary" title="Mark as unread">
                                                                    <i class="bi bi-envelope"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if ($isMsgArchived): ?>
                                                                <button type="submit" onclick="submitSingleAction('unarchive_contact_message', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-primary" title="Move back to inbox">
                                                                    <i class="bi bi-inbox"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" onclick="submitSingleAction('archive_contact_message', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-warning text-dark" title="Archive message">
                                                                    <i class="bi bi-archive"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <button type="submit" onclick="if(confirm('Move this message to Trash?')) submitSingleAction('trash_contact_message', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-danger" title="Move to Trash">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" onclick="submitSingleAction('restore_contact_message', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-outline-success" title="Restore message">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                            </button>
                                                            <button type="submit" onclick="if(confirm('Permanently delete this message? This cannot be undone.')) submitSingleAction('delete_contact_message', <?= (int)$contactMessage['id'] ?>); return false;" class="btn btn-sm btn-danger" title="Delete permanently">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        <!-- Pagination and summary -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
                            <small class="text-muted">
                                Showing <?= min($offset + 1, $totalContactMessages) ?> to <?= min($offset + count($contactMessages), $totalContactMessages) ?> of <?= $totalContactMessages ?> messages
                            </small>
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Messages pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= $msgPage <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admin_dashboard.php?tab=messages&msg_filter=<?= escape($msgFilter) ?>&p=<?= $msgPage - 1 ?><?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">Previous</a>
                                        </li>
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <?php if ($p === 1 || $p === $totalPages || ($p >= $msgPage - 2 && $p <= $msgPage + 2)): ?>
                                                <li class="page-item <?= $p === $msgPage ? 'active' : '' ?>">
                                                    <a class="page-link" href="admin_dashboard.php?tab=messages&msg_filter=<?= escape($msgFilter) ?>&p=<?= $p ?><?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>"><?= $p ?></a>
                                                </li>
                                            <?php elseif ($p === $msgPage - 3 || $p === $msgPage + 3): ?>
                                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $msgPage >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admin_dashboard.php?tab=messages&msg_filter=<?= escape($msgFilter) ?>&p=<?= $msgPage + 1 ?><?= $msgSearch !== '' ? '&search=' . urlencode($msgSearch) : '' ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>

                        <!-- Form for single actions -->
                        <form id="singleActionForm" method="post" style="display:none;">
                            <?= csrf_field() ?>
                            <input type="hidden" id="singleActionName" name="action" value="">
                            <input type="hidden" id="singleActionMsgId" name="contact_message_id" value="">
                        </form>

                        <script>
                        (function() {
                            const selectAll = document.getElementById('selectAllMessages');
                            const checkboxes = document.querySelectorAll('.msg-select-box');
                            const selectedCountInfo = document.getElementById('selectedCountInfo');
                            const bulkActionsGroup = document.getElementById('bulkActionsGroup');
                            const btnClear = document.getElementById('btnClearSelection');

                            function updateSelectionState() {
                                let checkedCount = 0;
                                checkboxes.forEach(cb => { if (cb.checked) checkedCount++; });

                                if (checkedCount > 0) {
                                    selectedCountInfo.textContent = checkedCount + ' selected';
                                    selectedCountInfo.classList.remove('d-none');
                                    bulkActionsGroup.classList.remove('d-none');
                                    btnClear.classList.remove('d-none');
                                } else {
                                    selectedCountInfo.classList.add('d-none');
                                    bulkActionsGroup.classList.add('d-none');
                                    btnClear.classList.add('d-none');
                                }
                                if (selectAll) {
                                    selectAll.checked = (checkedCount > 0 && checkedCount === checkboxes.length);
                                    selectAll.indeterminate = (checkedCount > 0 && checkedCount < checkboxes.length);
                                }
                            }

                            if (selectAll) {
                                selectAll.addEventListener('change', function() {
                                    checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
                                    updateSelectionState();
                                });
                            }

                            checkboxes.forEach(cb => {
                                cb.addEventListener('change', updateSelectionState);
                            });

                            if (btnClear) {
                                btnClear.addEventListener('click', function() {
                                    checkboxes.forEach(cb => { cb.checked = false; });
                                    if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
                                    updateSelectionState();
                                });
                            }

                            window.submitBulkAction = function(actionType) {
                                document.getElementById('bulkActionInput').value = actionType;
                                document.getElementById('bulkMessagesForm').submit();
                            };

                            window.submitSingleAction = function(actionName, messageId) {
                                document.getElementById('singleActionName').value = actionName;
                                document.getElementById('singleActionMsgId').value = messageId;
                                document.getElementById('singleActionForm').submit();
                            };
                        })();
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                <?php if ($msgSearch !== ''): ?>
                                    No contact messages found matching "<strong><?= escape($msgSearch) ?></strong>". <a href="admin_dashboard.php?tab=messages&msg_filter=<?= escape($msgFilter) ?>" class="alert-link">Clear search</a>
                                <?php else: ?>
                                    No messages found in <?= escape(ucfirst($msgFilter)) ?>.
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Gallery Tab -->
<?php if ($tab === 'gallery'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-upload"></i> Upload Gallery Image</h5>
                </div>
                <div class="card-body">
                                        <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_gallery">

                    
                        <div class="mb-3">
                            <label class="form-label">Image Title (Optional)</label>
                            <input type="text" name="gallery_title" class="form-control" placeholder="e.g. Conference 2026">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Image</label>
                            <input type="file" name="gallery_image" class="form-control" accept="image/png,image/jpeg,image/webp" required>
                            <small class="text-muted">PNG, JPEG, or WEBP - Max 5MB</small>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-cloud-upload"></i> Upload Image
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-images"></i> Gallery (<?= count($galleryImages) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($galleryImages) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($galleryImages as $image): ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="card shadow-sm">
                                        <img src="<?= escape($image['file_path']) ?>" class="card-img-top" alt="Gallery" style="height: 150px; object-fit: cover;">
                                        <div class="card-body p-2">
                                            <small class="text-muted"><?= date('M d, Y', strtotime($image['uploaded_at'])) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No gallery images yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Get buffered content
$content = ob_get_clean();
$page_title = "Admin Dashboard - JDM Kenya";
include(__DIR__ . "/layout.php");
?>
