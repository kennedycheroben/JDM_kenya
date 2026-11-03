<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/users.php';

if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin')) {
    header('Location: login.php');
    exit;
}

$viewerRole = $_SESSION['user_role'];
$viewerId = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, email, role, category, account_status, is_approved, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    header('Location: view_members.php');
    exit;
}

$targetRole = $u['role'] ?? 'member';

// Prevent deleting self or super administrators
if ($id === $viewerId) {
    header('Location: view_members.php?error=cannot_delete_self');
    exit;
}
if ($targetRole === 'super_admin') {
    header('Location: view_members.php?error=protected');
    exit;
}
if ($viewerRole === 'admin' && $targetRole === 'admin') {
    header('Location: view_members.php?error=protected');
    exit;
}

$currentStatus = $u['account_status'] ?? 'active';
$deps = checkUserDependencies($pdo, $id);
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'deactivate') {
        if (setUserAccountStatus($pdo, $id, 'disabled')) {
            header('Location: view_members.php?deactivated=1');
            exit;
        } else {
            $error = 'Failed to deactivate account.';
        }
    } elseif ($action === 'activate') {
        if (setUserAccountStatus($pdo, $id, 'active')) {
            header('Location: view_members.php?activated=1');
            exit;
        } else {
            $error = 'Failed to activate account.';
        }
    } elseif ($action === 'permanent_delete') {
        if ($viewerRole !== 'super_admin') {
            $error = 'Only Super Administrators have permission to permanently delete member accounts.';
        } else {
            try {
                deleteUser($pdo, $id, true);
                header('Location: view_members.php?deleted=1');
                exit;
            } catch (Throwable $e) {
                error_log('Permanent delete failed for user ' . $id . ': ' . $e->getMessage());
                $error = 'Unable to delete user: ' . $e->getMessage();
            }
        }
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="view_members.php">Members</a></li>
                <li class="breadcrumb-item"><a href="view_member.php?id=<?= (int)$u['id'] ?>"><?= escape($u['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Manage Member Lifecycle</li>
            </ol>
        </nav>
        <h2><i class="bi bi-person-gear"></i> Member Lifecycle & Account Status</h2>
        <p class="text-muted mb-0">Deactivate to preserve ministry history, or permanently remove the account.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= escape($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- User Overview Card -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="bi bi-person-badge"></i> Member Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8 fw-semibold"><?= escape($u['name']) ?></dd>

                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8"><?= escape($u['email']) ?></dd>

                    <dt class="col-sm-4 text-muted">Role</dt>
                    <dd class="col-sm-8"><span class="badge bg-primary"><?= escape($u['role']) ?></span></dd>

                    <dt class="col-sm-4 text-muted">Category</dt>
                    <dd class="col-sm-8"><span class="badge bg-secondary"><?= escape($u['category']) ?></span></dd>

                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8">
                        <?php if ($currentStatus === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php elseif ($currentStatus === 'disabled'): ?>
                            <span class="badge bg-secondary">Deactivated</span>
                        <?php elseif ($currentStatus === 'suspended'): ?>
                            <span class="badge bg-warning text-dark">Suspended</span>
                        <?php else: ?>
                            <span class="badge bg-dark"><?= escape($currentStatus) ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Registered</dt>
                    <dd class="col-sm-8 text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Actions and Dependencies -->
    <div class="col-12 col-lg-7">
        <?php if (!empty($deps['blocking_reasons'])): ?>
            <div class="alert alert-warning mb-4">
                <h6 class="alert-heading fw-bold"><i class="bi bi-shield-exclamation"></i> Associated Records Detected</h6>
                <p class="mb-2 small">This member has active ministry relationships in the database:</p>
                <ul class="mb-2 small">
                    <?php foreach ($deps['blocking_reasons'] as $reason): ?>
                        <li><?= escape($reason) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mb-0 small text-dark fw-semibold">
                    <i class="bi bi-info-circle"></i> Deactivating this account is recommended so that ministry records, attendance history, and audit trails remain intact.
                </p>
            </div>
        <?php endif; ?>

        <!-- Option A: Deactivate / Activate Account -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pause-circle"></i> Account Status: <?= $currentStatus === 'disabled' ? 'Reactivate' : 'Deactivate' ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($currentStatus === 'disabled'): ?>
                    <p class="text-muted small">This account is currently deactivated. Reactivating will restore member login and directory visibility.</p>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-play-circle"></i> Reactivate Account
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-muted small">
                        Deactivating prevents the user from signing in and hides them from the public directory, while safely preserving all historical attendance, Bible study registrations, chat messages, and audit logs.
                    </p>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this member? They will be unable to log in, but all records will be preserved.');">
                            <i class="bi bi-pause-circle"></i> Deactivate Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Option B: Permanent Delete -->
        <?php if ($viewerRole === 'super_admin'): ?>
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-trash"></i> Permanent Administrative Deletion</h5>
                </div>
                <div class="card-body">
                    <p class="text-danger small">
                        <strong>Warning:</strong> Permanent deletion completely purges the member record, leadership assignments, chat history, and uploaded files from the database. This action cannot be undone.
                    </p>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="permanent_delete">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('PERMANENT DELETION: Are you sure you want to permanently delete this member and all associated records? This cannot be undone.');">
                            <i class="bi bi-trash"></i> Permanently Delete Member
                        </button>
                        <a class="btn btn-outline-secondary ms-2" href="view_member.php?id=<?= (int)$u['id'] ?>">Cancel</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "Manage Member Lifecycle - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
