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
    require_csrf();
    $message = trim($_POST['message'] ?? '');
    $privacyLevel = trim($_POST['privacy_level'] ?? 'public');
    
    // Validate privacy level
    if (!in_array($privacyLevel, ['public', 'anonymous', 'private'], true)) {
        $privacyLevel = 'public';
    }
    
    // For backward compatibility, set is_private based on privacy_level
    $isPrivate = ($privacyLevel === 'private') ? 1 : 0;

    if ($message === '') {
        $error = 'Please type a prayer request.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO prayer_requests (user_id, message, is_private, privacy_level) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $message, $isPrivate, $privacyLevel]);
            $success = 'Prayer request submitted.';
        } catch (PDOException $e) {
            // Fallback to old schema if privacy_level column doesn't exist yet
            if (strpos($e->getMessage(), 'privacy_level') !== false) {
                $stmt = $pdo->prepare('INSERT INTO prayer_requests (user_id, message, is_private) VALUES (?, ?, ?)');
                $stmt->execute([$userId, $message, $isPrivate]);
                $success = 'Prayer request submitted.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

if ($userRole === 'admin' || $userRole === 'super_admin') {
    // Admins see all requests with user names
    $stmt = $pdo->query('SELECT pr.id, pr.message, pr.privacy_level, pr.is_private, pr.created_at, u.name FROM prayer_requests pr INNER JOIN users u ON u.id = pr.user_id ORDER BY pr.created_at DESC LIMIT 100');
} else {
    // Regular members see public and anonymous requests (but not private ones)
    $stmt = $pdo->query('SELECT pr.id, pr.message, pr.privacy_level, pr.is_private, pr.created_at, u.name FROM prayer_requests pr INNER JOIN users u ON u.id = pr.user_id WHERE pr.privacy_level IN ("public", "anonymous") OR pr.is_private = 0 ORDER BY pr.created_at DESC LIMIT 100');
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-heart-fill text-danger"></i> Prayer Wall</h2>
        <p class="text-muted mb-0">Share your requests and stand in prayer with the community.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-1">Submit a Prayer Request</h5>
                <p class="text-muted small mb-3">Choose your privacy preference below.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= escape($error) ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?= escape($success) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Prayer Request</label>
                        <textarea class="form-control" name="message" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label d-block mb-2">Privacy Level</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="privacy_public" name="privacy_level" value="public" checked>
                            <label class="form-check-label" for="privacy_public">
                                <i class="bi bi-globe"></i> <strong>Public</strong>
                                <br><small class="text-muted">Visible to all members</small>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" id="privacy_anonymous" name="privacy_level" value="anonymous">
                            <label class="form-check-label" for="privacy_anonymous">
                                <i class="bi bi-incognito"></i> <strong>Anonymous</strong>
                                <br><small class="text-muted">Visible to all but your name is hidden</small>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" id="privacy_private" name="privacy_level" value="private">
                            <label class="form-check-label" for="privacy_private">
                                <i class="bi bi-lock"></i> <strong>Private</strong>
                                <br><small class="text-muted">Visible to admins only</small>
                            </label>
                        </div>
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
                    <h5 class="mb-0"><?= ($userRole === 'admin' || $userRole === 'super_admin') ? 'All Requests' : 'Public Requests' ?></h5>
                    <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
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
                                        <?php 
                                            // Determine what name to display
                                            $displayName = $r['name'];
                                            $privacyLevel = $r['privacy_level'] ?? ($r['is_private'] ? 'private' : 'public');
                                            
                                            if ($privacyLevel === 'anonymous' && ($userRole !== 'admin' && $userRole !== 'super_admin')) {
                                                $displayName = 'Anonymous';
                                            }
                                        ?>
                                        <?= escape($displayName) ?>
                                        <?php 
                                            // Display privacy badges
                                            if ($privacyLevel === 'anonymous'): ?>
                                                <span class="badge text-bg-info ms-2"><i class="bi bi-incognito"></i> Anonymous</span>
                                            <?php elseif ($privacyLevel === 'private'): ?>
                                                <span class="badge text-bg-secondary ms-2"><i class="bi bi-lock"></i> Private</span>
                                            <?php endif; ?>
                                        <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                                            <?php if ($privacyLevel === 'anonymous'): ?>
                                                <span class="badge text-bg-secondary ms-1">(<?= escape($r['name']) ?>)</span>
                                            <?php endif; ?>
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

<?php
$content = ob_get_clean();
$page_title = "Prayer Wall - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
