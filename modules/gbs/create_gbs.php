<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Check if user is logged in and is GBS leader
if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$isGbsLeader = false;

// Check if user is GBS leader
$isGbsLeader = false;
try {
    $stmt = $pdo->prepare("SELECT is_gbs_leader FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $isGbsLeader = (bool)$userData['is_gbs_leader'];
    }
} catch (PDOException $e) {
    // Column may not exist yet; fall back to gbs_members check
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gbs_members WHERE user_id = ? AND role = 'leader'");
        $stmt->execute([$userId]);
        $isGbsLeader = (bool)$stmt->fetchColumn();
    } catch (PDOException $ignored) {}
}

if (!$isGbsLeader) {
    header('Location: gbs_dashboard.php');
    exit;
}

$error = '';
$success = '';
$isNewLeader = isset($_GET['new_leader']) && $_GET['new_leader'] === '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $groupName = trim($_POST['group_name'] ?? '');
    $groupSlogan = trim($_POST['group_slogan'] ?? '');
    $leaderName = trim($_POST['leader_name'] ?? $userName);
    
    if ($groupName === '') {
        $error = 'Group name is required.';
    } elseif ($groupSlogan === '') {
        $error = 'Group slogan is required.';
    } elseif ($leaderName === '') {
        $error = 'Leader name is required.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Handle profile picture upload
            $profilePicPath = 'default_gbs.png';
            if (!empty($_FILES['profile_picture']['name'])) {
                $file = $_FILES['profile_picture'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Upload failed. Please try again.');
                } elseif (!in_array($ext, $allowed, true)) {
                    throw new Exception('Please upload a JPG, PNG, or WEBP image.');
                } elseif (($file['size'] ?? 0) > 4 * 1024 * 1024) {
                    throw new Exception('Image too large. Max 4MB.');
                }
                
                $destinationDir = UPLOAD_DIR . 'gbs_pfps/';
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0775, true);
                }
                
                $targetName = 'gbs_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destination = $destinationDir . $targetName;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $profilePicPath = 'uploads/gbs_pfps/' . $targetName;
                }
            }
            
            // Create GBS group
            $stmt = $pdo->prepare("
                INSERT INTO gbs_groups (name, slogan, leader_id, pfp_path, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$groupName, $groupSlogan, $userId, $profilePicPath]);
            $gbsId = $pdo->lastInsertId();
            
            // Add creator as leader member
            $stmt = $pdo->prepare("
                INSERT INTO gbs_members (gbs_id, user_id, role, joined_at) 
                VALUES (?, ?, 'leader', NOW())
            ");
            $stmt->execute([$gbsId, $userId]);
            
            $pdo->commit();
            
            $success = 'GBS group created successfully! Redirecting to your group...';
            
            // Redirect to the new group after 2 seconds
            header("refresh:2;url=gbs_group.php?id=$gbsId");
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                                <i class="bi bi-people"></i> GBS Group Name *
                            </label>
                            <input type="text" name="group_name" class="form-control form-control-lg" 
                                   placeholder="Enter your GBS group name" required
                                   value="<?= htmlspecialchars($_POST['group_name'] ?? '') ?>">
                            <div class="form-text">Choose a meaningful name for your group.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-quote"></i> Group Slogan *
                            </label>
                            <textarea name="group_slogan" class="form-control form-control-lg" rows="3" 
                                      placeholder="Enter your group's slogan or mission statement" required><?= htmlspecialchars($_POST['group_slogan'] ?? '') ?></textarea>
                            <div class="form-text">A short, inspiring statement about your group's purpose.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-person"></i> GBS Leader Name *
                            </label>
                            <input type="text" name="leader_name" class="form-control form-control-lg" 
                                   placeholder="Your name as GBS leader" required
                                   value="<?= htmlspecialchars($_POST['leader_name'] ?? $userName) ?>">
                            <div class="form-text">This will be displayed as the group leader's name.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center">
                            <label class="form-label fw-bold">
                                <i class="bi bi-image"></i> Group Profile Picture
                            </label>
                            <div class="mb-3">
                                <img id="profilePreview" class="profile-preview" 
                                     src="<?= BASE_PATH ?>/uploads/gbs_pfps/default_gbs.png" 
                                     alt="Group profile picture">
                            </div>
                            <div class="file-upload-wrapper">
                                <label for="profile_picture" class="file-upload-label">
                                    <i class="bi bi-camera"></i> Choose Photo
                                </label>
                                <input type="file" name="profile_picture" id="profile_picture" 
                                       accept="image/*" onchange="previewImage(event)">
                            </div>
                            <div class="form-text small">
                                JPG, PNG, or WEBP. Max 4MB.<br>
                                Default image will be used if none uploaded.
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
        
        // Add form validation feedback
        document.getElementById('gbsSetupForm').addEventListener('submit', function(e) {
            const groupName = document.querySelector('input[name="group_name"]').value.trim();
            const groupSlogan = document.querySelector('textarea[name="group_slogan"]').value.trim();
            const leaderName = document.querySelector('input[name="leader_name"]').value.trim();
            
            if (!groupName || !groupSlogan || !leaderName) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>
