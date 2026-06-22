<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: logout.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (!empty($_FILES['profile_picture']['name'])) {
        $file = $_FILES['profile_picture'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed. Please try again.';
        } elseif (!in_array($ext, $allowed, true)) {
            $error = 'Please upload a JPG, PNG, or WEBP image.';
        } elseif (($file['size'] ?? 0) > 4 * 1024 * 1024) {
            $error = 'Image too large. Max 4MB.';
        } else {
            $destinationDir = UPLOAD_DIR . 'profiles/';
            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0775, true);
            }

            $targetName = 'pfp_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destination = $destinationDir . $targetName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $publicPath = 'uploads/profiles/' . $targetName;
                $stmt = $pdo->prepare('UPDATE users SET pfp_path = ? WHERE id = ?');
                $stmt->execute([$publicPath, $userId]);
                $success = 'Profile picture updated.';
            } else {
                $error = 'Unable to save the uploaded file.';
            }
        }
    } else {
        $error = 'Please choose a profile picture to upload.';
    }
}


$stmt = $pdo->prepare('SELECT name, email, whatsapp_phone, category, pfp_path FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header('Location: logout.php');
    exit;
}

// Fetch associate info if associate
$associate = null;
if ($me['category'] === 'associate') {
    $stmtA = $pdo->prepare('SELECT graduation_year, current_profession FROM associates WHERE user_id = ? LIMIT 1');
    $stmtA->execute([$userId]);
    $associate = $stmtA->fetch(PDO::FETCH_ASSOC);
}

// Handle associate update
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_associate'])) {
    require_csrf();
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $currentProfession = trim($_POST['current_profession'] ?? '');
    if ($graduationYear === '' || $currentProfession === '') {
        $error = 'Please provide graduation year and current profession.';
    } else {
        $stmtU = $pdo->prepare('UPDATE associates SET graduation_year = ?, current_profession = ? WHERE user_id = ?');
        $stmtU->execute([$graduationYear, $currentProfession, $userId]);
        $success = 'Associate info updated.';
        $associate['graduation_year'] = $graduationYear;
        $associate['current_profession'] = $currentProfession;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
    <style>
        .avatar-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f3f5;
            border: 1px solid rgba(0,0,0,.08);
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="members_portal.php">JDM Members</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="members_portal.php">Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="prayer_wall.php">Prayer Wall</a></li>
                <li class="nav-item"><a class="nav-link" href="news_room.php">Newsroom</a></li>
                <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
            </ul>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Sign out</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if (!empty($me['pfp_path'])): ?>
                            <img class="avatar-lg" src="<?= escape($me['pfp_path']) ?>" alt="Profile picture">
                        <?php else: ?>
                            <div class="avatar-lg d-flex align-items-center justify-content-center">
                                <i class="bi bi-person text-secondary" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-1"><?= escape($me['name']) ?></h4>
                            <div class="text-muted small"><?= escape($me['email']) ?></div>
                            <div class="text-muted small">WhatsApp: <?= escape($me['whatsapp_phone']) ?></div>
                            <span class="badge text-bg-secondary mt-2"><?= escape(ucfirst($me['category'])) ?></span>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                    <?php endif; ?>

                    <h6 class="mt-3">Upload Profile Picture</h6>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <input type="file" name="profile_picture" class="form-control" accept="image/png,image/jpeg,image/webp" required>
                            <div class="form-text">Max size 4MB. Supported: JPG, PNG, WEBP.</div>
                        </div>
                        <div class="d-grid d-sm-flex gap-2">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Save</button>
                            <a class="btn btn-outline-secondary" href="members_portal.php">Back to Portal</a>
                        </div>
                    </form>

                    <?php if ($me['category'] === 'associate'): ?>
                    <hr>
                    <h6 class="mt-3">Associate Information</h6>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_associate" value="1">
                        <div class="mb-3">
                            <label class="form-label">Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" min="1950" max="<?= date('Y') ?>" value="<?= escape($associate['graduation_year'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Profession</label>
                            <input type="text" name="current_profession" class="form-control" maxlength="120" value="<?= escape($associate['current_profession'] ?? '') ?>">
                        </div>
                        <div class="d-grid d-sm-flex gap-2">
                            <button class="btn btn-success" type="submit"><i class="bi bi-save"></i> Update Info</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
