<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';

function canManageTarget(string $targetRole, int $targetId, int $selfId): bool {
    if ($targetId === $selfId) return false;
    if ($targetRole === 'super_admin') return false;
    return true;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    $selfId = (int)($_SESSION['user_id'] ?? 0);

    if ($targetId <= 0) {
        $err = 'Invalid target user.';
    } else {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $err = 'User not found.';
        } elseif (!canManageTarget($target['role'], (int)$target['id'], $selfId)) {
            $err = 'You cannot perform this action on that user.';
        } else {
            try {
                if ($action === 'promote_admin') {
                    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
                    $msg = 'User promoted to admin.';

                    // Notify the user via chat
                    try {
                        $expiry = time() + (7 * 24 * 60 * 60);
                        $notifText = "🛡️ Congratulations! You have been promoted to Admin.\n\nYou now have access to the Admin Dashboard to manage the portal:\nadmin_dashboard.php?new_admin=1&exp=" . $expiry . "\n\nThank you for your service to JDM Kenya!";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send Admin promotion notification: " . $e->getMessage());
                    }
                } elseif ($action === 'promote_gbs_leader') {
                    $pdo->prepare("UPDATE users SET is_gbs_leader = 1 WHERE id = ?")->execute([$targetId]);
                    $msg = 'User appointed as GBS Leader.';
                    
                    // Notify the user via chat
                    try {
                        $expiry = time() + (7 * 24 * 60 * 60);
                        $notifText = "🎉 Congratulations! You have been appointed as a GBS Leader.\n\nYou can now access your GBS Dashboard to start creating and managing your GBS group(s):\ngbs_dashboard.php?new_leader=1&exp=" . $expiry . "\n\nGod bless you in this new leadership role!";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send GBS Leader appointment notification: " . $e->getMessage());
                    }

                    // If the appointed user is the current session user, redirect to setup
                    if ($targetId === $selfId) {
                        header('Location: gbs_dashboard.php?new_leader=1');
                        exit;
                    }
                } elseif ($action === 'demote_member') {
                    $pdo->prepare("UPDATE users SET role = 'member', is_gbs_leader = 0 WHERE id = ?")->execute([$targetId]);
                    $msg = 'User demoted to member.';
                } elseif ($action === 'remove_gbs_leader') {
                    $pdo->prepare("UPDATE users SET is_gbs_leader = 0 WHERE id = ?")->execute([$targetId]);
                    $msg = 'GBS Leader role removed from user.';
                } elseif ($action === 'delete_user') {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                    $msg = 'User deleted.';
                } else {
                    $err = 'Unknown action.';
                }
            } catch (Throwable $e) {
                $err = 'Operation failed.';
            }
        }
    }
}

$users = $pdo->query('SELECT id, name, email, whatsapp_phone, role, category, is_gbs_leader, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-shield-lock"></i> Super Admin Dashboard</h2>
        <p class="text-muted">Only Super Admin can delete or demote admins.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= escape($msg) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle"></i> <?= escape($err) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> All Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Role</th>
                        <th>Category</th>
                        <th>Joined</th>
                        <th style="width: 260px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $role = $u['role'] ?? 'member';
                        $selfId = (int)($_SESSION['user_id'] ?? 0);
                        $targetId = (int)$u['id'];
                        $locked = ($targetId === $selfId) || ($role === 'super_admin');
                        ?>
                        <tr>
                            <td>#<?= (int)$u['id'] ?></td>
                            <td><?= escape($u['name']) ?></td>
                            <td><?= escape($u['email']) ?></td>
                            <td><?= escape($u['whatsapp_phone']) ?></td>
                            <td>
                                <?php
                                $rb = match($role) {
                                    'super_admin' => 'bg-dark',
                                    'admin' => 'bg-danger',
                                    default => 'bg-primary'
                                };
                                ?>
                                <span class="badge <?= $rb ?>"><?= escape($role) ?></span>
                                <?php if ($u['is_gbs_leader']): ?>
                                    <span class="badge bg-warning ms-1">GBS Leader</span>
                                <?php endif; ?>
                            </td>
                            <td><?= escape($u['category'] ?? '-') ?></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="promote_admin">
                                        <button class="btn btn-sm btn-danger" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>>
                                            Promote to Admin
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="promote_gbs_leader">
                                        <button class="btn btn-sm btn-warning" <?= ($locked || $u['is_gbs_leader']) ? 'disabled' : '' ?>>
                                            <i class="bi bi-star"></i> Appoint as GBS Leader
                                        </button>
                                    </form>
                                    <?php if ($u['is_gbs_leader']): ?>
                                        <form method="post">
                                            <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                            <input type="hidden" name="action" value="remove_gbs_leader">
                                            <button class="btn btn-sm btn-outline-warning" <?= $locked ? 'disabled' : '' ?>>
                                                <i class="bi bi-star"></i> Remove GBS Leader
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="demote_member">
                                        <button class="btn btn-sm btn-outline-primary" <?= ($locked || $role === 'member') ? 'disabled' : '' ?>>
                                            Demote to Member
                                        </button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button class="btn btn-sm btn-outline-danger" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                                <?php if ($role === 'admin'): ?>
                                    <div class="text-muted small mt-1">Admins can only be deleted/demoted via Super Admin rules (supported). Delete is disabled for admins by default.</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "Super Admin Dashboard - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
