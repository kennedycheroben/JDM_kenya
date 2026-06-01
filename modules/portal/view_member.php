<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin')) {
    header('Location: login.php');
    exit;
}

$viewerRole = $_SESSION['user_role'];
$viewerId = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}


// Join associates table if category is associate
$stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.whatsapp_phone, u.role, u.category, u.created_at, u.pfp_path, a.graduation_year, a.current_profession FROM users u LEFT JOIN associates a ON u.id = a.user_id WHERE u.id = ? LIMIT 1');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    header('Location: view_members.php');
    exit;
}

// Admins cannot view other admins/super admins management pages (prevent "touching")
if ($viewerRole === 'admin' && ($u['role'] === 'admin' || $u['role'] === 'super_admin')) {
    header('Location: view_members.php');
    exit;
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-person"></i> Member Details</h2>
            <p class="text-muted mb-0">View user profile and registration details.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($viewerRole === 'super_admin' || $viewerRole === 'admin'): ?>
                <!-- Admins can only view details and delete users, not edit personal data -->
                <button class="btn btn-outline-primary" disabled><i class="bi bi-eye"></i> View Details</button>
                <a href="delete_member.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user?')"><i class="bi bi-trash"></i> Delete User</a>
            <?php else: ?>
                <!-- Only users can edit their own profile -->
                <a href="edit_member.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
            <?php endif; ?>
            <a href="view_members.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-card-text"></i> User</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-3 text-center">
                <?php if (!empty($u['pfp_path'])): ?>
                    <img src="<?= escape($u['pfp_path']) ?>" class="img-fluid rounded-circle" style="width: 120px; height:120px; object-fit:cover;" alt="Profile picture">
                <?php else: ?>
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height:120px;">
                        <i class="bi bi-person text-secondary" style="font-size: 3rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-9">
                <div class="row g-2">
                    <div class="col-12 col-md-6"><strong>Name:</strong> <?= escape($u['name']) ?></div>
                    <div class="col-12 col-md-6"><strong>Email:</strong> <?= escape($u['email']) ?></div>
                    <div class="col-12 col-md-6"><strong>WhatsApp:</strong> <?= escape($u['whatsapp_phone']) ?></div>
                    <div class="col-12 col-md-6"><strong>Category:</strong> <?= escape($u['category']) ?></div>
                    <div class="col-12 col-md-6"><strong>Role:</strong> <?= escape($u['role']) ?></div>
                    <div class="col-12 col-md-6"><strong>Joined:</strong> <?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                    <?php if ($u['category'] === 'associate'): ?>
                        <div class="col-12 col-md-6"><strong>Graduation Year:</strong> <?= escape($u['graduation_year'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Current Profession:</strong> <?= escape($u['current_profession'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "View Member - JDM Kenya";
include(__DIR__ . '/layout.php');
?>

