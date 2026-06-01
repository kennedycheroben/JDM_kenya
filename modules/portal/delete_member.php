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

$stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    header('Location: view_members.php');
    exit;
}

$targetRole = $u['role'] ?? 'member';

// Admins cannot delete admins/super admins. Super admins cannot delete other admins via this page (use super_admin_dashboard).
if ($viewerRole === 'admin' && ($targetRole === 'admin' || $targetRole === 'super_admin')) {
    header('Location: view_members.php');
    exit;
}
if ($viewerRole === 'super_admin' && $targetRole === 'admin') {
    header('Location: super_admin_dashboard.php');
    exit;
}
if ($targetRole === 'super_admin' || (int)$u['id'] === $viewerId) {
    header('Location: view_members.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        header('Location: view_members.php');
        exit;
    } catch (Throwable $e) {
        $error = 'Unable to delete user.';
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-trash"></i> Delete Member</h2>
        <p class="text-muted mb-0">This action cannot be undone.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">Confirm Delete</h5>
    </div>
    <div class="card-body">
        <p>Delete <strong><?= escape($u['name']) ?></strong> (<?= escape($u['email']) ?>)?</p>
        <form method="post" class="d-grid d-sm-flex gap-2">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i> Yes, Delete</button>
            <a class="btn btn-outline-secondary" href="view_member.php?id=<?= (int)$u['id'] ?>">Cancel</a>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "Delete Member - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
