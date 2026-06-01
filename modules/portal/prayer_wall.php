<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? 'member';

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $isPrivate = !empty($_POST['is_private']) ? 1 : 0;

    if ($message === '') {
        $error = 'Please type a prayer request.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO prayer_requests (user_id, message, is_private) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $message, $isPrivate]);
            $success = 'Prayer request submitted.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

if ($userRole === 'admin') {
    $stmt = $pdo->query('SELECT pr.id, pr.message, pr.is_private, pr.created_at, u.name FROM prayer_requests pr INNER JOIN users u ON u.id = pr.user_id ORDER BY pr.created_at DESC LIMIT 100');
} else {
    $stmt = $pdo->query('SELECT pr.id, pr.message, pr.is_private, pr.created_at, u.name FROM prayer_requests pr INNER JOIN users u ON u.id = pr.user_id WHERE pr.is_private = 0 ORDER BY pr.created_at DESC LIMIT 100');
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Prayer Wall</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
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
                <li class="nav-item"><a class="nav-link active" href="prayer_wall.php">Prayer Wall</a></li>
                <li class="nav-item"><a class="nav-link" href="news_room.php">Newsroom</a></li>
                <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
            </ul>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Sign out</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1">Submit a Prayer Request</h5>
                    <p class="text-muted small mb-3">Private requests are visible to admins only.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Prayer Request</label>
                            <textarea class="form-control" name="message" rows="5" required></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_private" name="is_private" value="1">
                            <label class="form-check-label" for="is_private">Keep Private (Admin only)</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send"></i> Submit</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0"><?= $userRole === 'admin' ? 'All Requests' : 'Public Requests' ?></h5>
                        <?php if ($userRole === 'admin'): ?>
                            <span class="badge text-bg-warning"><i class="bi bi-eye"></i> Admin view includes private</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$requests): ?>
                        <div class="alert alert-info mb-0">No requests yet.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($requests as $r): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-semibold">
                                            <?= escape($r['name']) ?>
                                            <?php if (!empty($r['is_private'])): ?>
                                                <span class="badge text-bg-secondary ms-2">Private</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                    </div>
                                    <div class="mt-2 text-muted"><?= nl2br(escape($r['message'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
