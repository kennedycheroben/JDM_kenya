<?php
// Enable temporary error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_activity') {
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if ($title && $date && $content) {
            try {
                $stmt = $pdo->prepare('INSERT INTO activities (title, date, content, image, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$title, $date, $content, $image, $_SESSION['user_id']]);
                $message = 'Activity added successfully.';
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
            }
        } else {
            $message = 'Please complete all required fields for the activity.';
        }
    }

    if ($action === 'update_activity') {
        $id = intval($_POST['activity_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if ($id && $title && $date && $content) {
            $stmt = $pdo->prepare('UPDATE activities SET title = ?, date = ?, content = ?, image = ? WHERE id = ?');
            $stmt->execute([$title, $date, $content, $image, $id]);
            $message = 'Activity updated successfully.';
        } else {
            $message = 'Please provide valid activity details.';
        }
    }

    if ($action === 'delete_activity') {
        $id = intval($_POST['activity_id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM activities WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Activity deleted successfully.';
        }
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
                @mkdir($destinationDir, 0775, true);
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
                    $message = 'Database error: ' . $e->getMessage();
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
                $message = 'Database error: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'upload_gallery' && !empty($_FILES['gallery_image']['name'])) {
        $file = $_FILES['gallery_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Gallery upload failed. Please try again.';
        } elseif (!in_array($ext, $allowed, true)) {
            $message = 'Gallery accepts JPG, PNG, or WEBP images only.';
        } else {
            $destinationDir = UPLOAD_DIR . 'gallery/';
            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0775, true);
            }
            $safeName = basename($file['name']);
            $targetName = uniqid('gal_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeName);
            $destination = $destinationDir . $targetName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $title = trim($_POST['gallery_title'] ?? '');
                try {
                    $stmt = $pdo->prepare('INSERT INTO gallery_images (title, file_path, uploaded_by) VALUES (?, ?, ?)');
                    $stmt->execute([$title !== '' ? $title : null, 'uploads/gallery/' . $targetName, $_SESSION['user_id']]);
                    $message = 'Gallery image uploaded successfully.';
                } catch (PDOException $e) {
                    $message = 'Database error: ' . $e->getMessage();
                }
            } else {
                $message = 'Unable to save the gallery image.';
            }
        }
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

    if ($action === 'delete_contact_message') {
        $id = (int)($_POST['contact_message_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Contact message deleted.';
            $tab = 'messages';
        }
    }
}

// Fetch data
$activities = $pdo->query('SELECT * FROM activities ORDER BY date DESC')->fetchAll(PDO::FETCH_ASSOC);
$resources = $pdo->query('SELECT * FROM resources ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);
$announcements = $pdo->query('SELECT *, COALESCE(date_created, date_posted) AS date_created_safe FROM announcements ORDER BY COALESCE(date_created, date_posted) DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$galleryImages = $pdo->query('SELECT * FROM gallery_images ORDER BY uploaded_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$contactMessages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$unreadContactMessages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();

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
                    <form method="post" class="needs-validation">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $editing ? 'update_activity' : 'add_activity' ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="activity_id" value="<?= $editing['id'] ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" value="<?= escape($editing['title'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= escape($editing['date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Image URL</label>
                                <input type="url" name="image" class="form-control" value="<?= escape($editing['image'] ?? '') ?>" placeholder="Optional">
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
                                        <?php if ($activity['image']): ?>
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
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?= escape($ann['title']) ?></h6>
                                    <p class="mb-1"><?= escape(mb_strimwidth($ann['content'], 0, 150, '...')) ?></p>
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

<!-- Contact Messages Tab -->
<?php if ($tab === 'messages'): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-envelope"></i> Contact Messages</h5>
                    <span class="badge bg-light text-primary"><?= $unreadContactMessages ?> unread</span>
                </div>
                <div class="card-body">
                    <?php if (count($contactMessages) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Sender</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Received</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contactMessages as $contactMessage): ?>
                                        <tr class="<?= $contactMessage['status'] === 'unread' ? 'table-warning' : '' ?>">
                                            <td>
                                                <?php if ($contactMessage['status'] === 'unread'): ?>
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
                                            <td style="min-width: 280px;">
                                                <?= nl2br(escape($contactMessage['message'])) ?>
                                            </td>
                                            <td><?= date('M d, Y H:i', strtotime($contactMessage['created_at'])) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <?php if ($contactMessage['status'] === 'unread'): ?>
                                                        <form method="post">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="mark_contact_read">
                                                            <input type="hidden" name="contact_message_id" value="<?= (int)$contactMessage['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                                <i class="bi bi-check2-circle"></i> Mark Read
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" onsubmit="return confirm('Delete this contact message?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_contact_message">
                                                        <input type="hidden" name="contact_message_id" value="<?= (int)$contactMessage['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No contact messages yet.</div>
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
