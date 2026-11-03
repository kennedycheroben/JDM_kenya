<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/gbs_groups.php';

// Check if user is logged in
if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

// Server-side check for GBS Leader or Super Admin access
$isGbsLeader = canUserCreateGbsGroup($pdo, $userId, $userRole);

if (!$isGbsLeader) {
    header('Location: gbs_dashboard.php');
    exit;
}

$error = '';
$success = '';
$isNewLeader = isset($_GET['new_leader']) && $_GET['new_leader'] === '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) {
        $error = $_SESSION['csrf_error'] ?? 'Session expired. Please reload the page and try again.';
        unset($_SESSION['csrf_error']);
    } else {
        $groupName = trim($_POST['group_name'] ?? '');
        $groupSlogan = trim($_POST['group_slogan'] ?? '');
        $requestedLeaderId = isset($_POST['leader_id']) && $_POST['leader_id'] !== ''
            ? (int)$_POST['leader_id']
            : $userId;

        if ($groupName === '') {
            $error = 'Group name is required.';
        } elseif ($userRole !== 'super_admin' && $requestedLeaderId !== $userId) {
            $error = 'You can only create a GBS group with yourself as the leader.';
        } else {
            try {
                $profilePicPath = null;
                if (!empty($_FILES['profile_picture']['name']) && ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['profile_picture'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Upload failed. Please try again.');
                    } elseif (!in_array($ext, $allowed, true)) {
                        throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
                    } elseif (($file['size'] ?? 0) > 4 * 1024 * 1024) {
                        throw new RuntimeException('Image too large. Max 4MB.');
                    }

                    $uploadRoot = defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(__DIR__, 2) . '/uploads/');
                    $destinationDir = rtrim($uploadRoot, '/') . '/gbs_pfps/';
                    if (!is_dir($destinationDir)) {
                        mkdir($destinationDir, 0775, true);
                    }

                    $targetName = 'gbs_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $destination = $destinationDir . $targetName;

                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        throw new RuntimeException('Failed to save uploaded group image.');
                    }
                    $profilePicPath = 'uploads/gbs_pfps/' . $targetName;
                }

                $gbsId = createGbsGroup(
                    $pdo,
                    $userId,
                    $userRole,
                    $groupName,
                    $groupSlogan !== '' ? $groupSlogan : null,
                    $userRole === 'super_admin' ? $requestedLeaderId : $userId,
                    $profilePicPath
                );

                header('Location: gbs_group.php?id=' . $gbsId . '&message=' . urlencode('GBS group created successfully!'));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your GBS - JDM Kenya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .welcome-card {
            background: linear-gradient(135deg, #2C5EAD 0%, #1591DC 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profile-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-upload-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        .file-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload-label:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .new-leader-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-light">
    <div class="setup-container">
        <?php if ($isNewLeader): ?>
            <div class="welcome-card text-center">
                <i class="bi bi-star-fill" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h2>Congratulations, <?= htmlspecialchars($userName) ?>!</h2>
                <p class="lead">You've been appointed as a GBS Leader!</p>
                <p>Let's set up your first Group Bible Study group below.</p>
                <div class="badge new-leader-badge bg-warning text-dark">
                    <i class="bi bi-star"></i> New GBS Leader
                </div>
            </div>
        <?php else: ?>
            <div class="welcome-card text-center">
                <i class="bi bi-people-fill" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h2>Create New GBS Group</h2>
                <p class="lead">Set up a new Group Bible Study group to lead.</p>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="gbsSetupForm">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-people"></i> GBS Group Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="group_name" class="form-control form-control-lg" 
                                   placeholder="Enter your GBS group name" required
                                   value="<?= htmlspecialchars($_POST['group_name'] ?? '') ?>">
                            <div class="form-text">Choose a meaningful name for your group.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-quote"></i> Group Slogan <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <textarea name="group_slogan" class="form-control form-control-lg" rows="3" 
                                      placeholder="Enter your group's slogan or mission statement (optional)"><?= htmlspecialchars($_POST['group_slogan'] ?? '') ?></textarea>
                            <div class="form-text">A short, inspiring statement about your group's purpose.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person"></i> GBS Leader
                            </label>
                            <input type="text" class="form-control form-control-lg bg-light" 
                                   value="<?= htmlspecialchars($userName) ?> (You)" readonly>
                            <div class="form-text">You will automatically be assigned as the leader of this group.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center">
                            <label class="form-label fw-bold">
                                <i class="bi bi-image"></i> Group Profile Picture <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <div class="mb-3">
                                <img id="profilePreview" class="profile-preview" 
                                     src="<?= BASE_PATH ?>/images/jdm_logo.png" 
                                     alt="Group profile picture">
                            </div>
                            <div class="file-upload-wrapper">
                                <label for="profile_picture" class="file-upload-label">
                                    <i class="bi bi-camera"></i> Choose Photo
                                </label>
                                <input type="file" name="profile_picture" id="profile_picture" 
                                       accept="image/png,image/jpeg,image/webp" onchange="previewImage(event)">
                            </div>
                            <div class="form-text small">
                                JPG, PNG, or WEBP. Max 4MB.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle"></i> Create GBS Group
                        </button>
                        <a href="gbs_dashboard.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('profilePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
        
        document.getElementById('gbsSetupForm').addEventListener('submit', function(e) {
            const groupName = document.querySelector('input[name="group_name"]').value.trim();
            if (!groupName) {
                e.preventDefault();
                alert('Please enter a group name.');
            }
        });
    </script>
</body>
</html>
