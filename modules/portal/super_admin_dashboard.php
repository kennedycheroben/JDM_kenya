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
    require_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $reasonRequiredActions = ['promote_admin', 'promote_gbs_leader', 'demote_member', 'remove_gbs_leader'];
    $targetId = (int)($_POST['user_id'] ?? 0);
    $selfId = (int)($_SESSION['user_id'] ?? 0);

    if ($targetId <= 0) {
        $err = 'Invalid target user.';
    } else {
        $stmt = $pdo->prepare('SELECT id, role, category, is_approved FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $err = 'User not found.';
        } elseif (!canManageTarget($target['role'], (int)$target['id'], $selfId)) {
            $err = 'You cannot perform this action on that user.';
        } elseif (in_array($action, $reasonRequiredActions, true) && $reason === '') {
            $err = 'Please provide a reason before completing this action.';
        } else {
            try {
                // =====================================================================
                // OFFICE BEARER APPROVAL WORKFLOW
                // =====================================================================
                // Super Admin can approve or reject pending office bearer (partner) registrations
                
                if ($action === 'approve_partner') {
                    // Check if this is actually a partner category
                    if ($target['category'] !== 'partner') {
                        $err = 'Only partners (office bearers) can be approved.';
                    } else {
                        // Approve the partner: set is_approved = 1
                        $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?")->execute([$targetId]);
                        $msg = 'Office bearer approved and can now access the full dashboard.';
                        
                        // Notify the user via chat
                        try {
                            $notifText = "✅ Great news! Your registration as an Office Bearer has been approved by JDM leadership.\n\nYou can now access the full member dashboard, resources, and all portal features.\n\nThank you for your commitment to serve JDM Kenya!";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send Office Bearer approval notification: " . $e->getMessage());
                        }
                    }
                } elseif ($action === 'reject_partner') {
                    // Check if this is actually a partner category
                    if ($target['category'] !== 'partner') {
                        $err = 'Only partners (office bearers) can be rejected.';
                    } else {
                        // Reject the partner: can delete or just notify
                        // For now, we'll send a rejection message
                        try {
                            $notifText = "ℹ️ Thank you for your interest in registering as an Office Bearer with JDM Kenya.\n\nUnfortunately, your application could not be approved at this time.\n\nPlease contact JDM leadership for more information.";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send Office Bearer rejection notification: " . $e->getMessage());
                        }
                        
                        // Delete the pending registration
                        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                        $msg = 'Office bearer registration rejected and user deleted.';
                    }
                } elseif ($action === 'promote_admin') {
                    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
                    $msg = 'User promoted to admin.';

                    // Notify the user via chat
                    try {
                        $expiry = time() + (7 * 24 * 60 * 60);
                        $notifText = "🛡️ Congratulations! You have been promoted to Admin.\n\nReason from JDM leadership:\n{$reason}\n\nYou now have access to the Admin Dashboard to manage the portal:\nadmin_dashboard.php?new_admin=1&exp=" . $expiry . "\n\nThank you for your service to JDM Kenya!";
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
                        $notifText = "🎉 Congratulations! You have been appointed as a GBS Leader.\n\nReason from JDM leadership:\n{$reason}\n\nYou can now access your GBS Dashboard to start creating and managing your GBS group(s):\ngbs_dashboard.php?new_leader=1&exp=" . $expiry . "\n\nGod bless you in this new leadership role!";
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
                    try {
                        $notifText = "ℹ️ Your account role has been updated to Member by JDM leadership.\n\nReason from JDM leadership:\n{$reason}\n\nIf you have questions, please contact JDM leadership.";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send demotion notification: " . $e->getMessage());
                    }
                } elseif ($action === 'remove_gbs_leader') {
                    $pdo->prepare("UPDATE users SET is_gbs_leader = 0 WHERE id = ?")->execute([$targetId]);
                    $msg = 'GBS Leader role removed from user.';
                    try {
                        $notifText = "ℹ️ Your GBS Leader appointment has been removed by JDM leadership.\n\nReason from JDM leadership:\n{$reason}\n\nYou can continue using the portal as a member.";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send GBS Leader removal notification: " . $e->getMessage());
                    }
                } elseif ($action === 'delete_user') {
                    try {
                        require_once dirname(__FILE__) . '/../../core/users.php';
                        deleteUser($pdo, $targetId);
                        $msg = 'User deleted.';
                    } catch (Throwable $e) {
                        error_log('Failed to delete user: ' . $e->getMessage());
                        $err = 'Failed to delete user. See logs for details.';
                    }
                } else {
                    $err = 'Unknown action.';
                }
            } catch (Throwable $e) {
                $err = 'Operation failed.';
            }
        }
    }
}

$users = $pdo->query('SELECT id, name, email, whatsapp_phone, role, category, is_gbs_leader, is_approved, created_at FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// OFFICE BEARER APPROVAL WORKFLOW
// =====================================================================
// Query for pending office bearer (partner) registrations that need approval
$pendingPartners = $pdo->query('
    SELECT id, name, email, whatsapp_phone, employment_status, company_name, 
           industry_profession, partnership_focus, created_at 
    FROM users 
    WHERE category = "partner" AND is_approved = 0 
    ORDER BY created_at ASC
')->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-shield-lock"></i> JDM leader</h2>
        <p class="text-muted">Only JDM leader can delete or demote admins.</p>
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

<!-- ===================================================================== -->
<!-- PENDING OFFICE BEARER APPROVALS SECTION -->
<!-- ===================================================================== -->
<?php if (!empty($pendingPartners)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-clock-history"></i> 
                Pending Office Bearer Approvals (<?= count($pendingPartners) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>WhatsApp</th>
                            <th>Employment Status</th>
                            <th>Company</th>
                            <th>Applied</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPartners as $partner): ?>
                            <tr class="table-warning-light" style="background-color: #fffdf5;">
                                <td>#<?= (int)$partner['id'] ?></td>
                                <td>
                                    <strong><?= escape($partner['name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        📍 <?= escape($partner['partnership_focus'] ?? 'No focus specified') ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="mailto:<?= escape($partner['email']) ?>" class="text-decoration-none">
                                        <?= escape($partner['email']) ?>
                                    </a>
                                </td>
                                <td><?= escape($partner['whatsapp_phone']) ?></td>
                                <td><?= escape($partner['employment_status'] ?? '-') ?></td>
                                <td><?= escape($partner['company_name'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($partner['created_at'])) ?></td>
                                <td>
                                    <!-- Approval Actions for Pending Office Bearers -->
                                    <div class="d-flex gap-2 justify-content-center flex-nowrap">
                                        <!-- APPROVE: Make office bearer active and notify them -->
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$partner['id'] ?>">
                                            <input type="hidden" name="action" value="approve_partner">
                                            <button class="btn btn-sm btn-success" title="Approve this office bearer">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        
                                        <!-- REJECT: Decline and delete registration -->
                                        <form method="post" class="d-inline" onsubmit="return confirm('Reject and delete this application?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$partner['id'] ?>">
                                            <input type="hidden" name="action" value="reject_partner">
                                            <button class="btn btn-sm btn-danger" title="Reject this office bearer registration">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Custom CSS: crisp, visible grid lines and compact cell padding for the user data table -->
<style>
    .table-bordered th,
    .table-bordered td {
        border: 1px solid #dee2e6 !important;
        padding: 8px 12px;
    }
</style>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> All Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <!-- table-responsive prevents horizontal viewport overflow on smaller screens -->
        <div class="table-responsive">
            <!-- table-bordered: crisp grid lines | table-striped: alternate row shading | w-100: fill container -->
            <table class="table table-bordered table-striped table-hover align-middle w-100 mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Role</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <!-- Actions column: fixed minimum width keeps buttons from wrapping awkwardly -->
                        <th style="min-width: 260px;">Actions</th>
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
                            <td>
                                <?php
                                $categoryDisplay = match($u['category'] ?? '') {
                                    'partner' => 'Office Bearer',
                                    'student' => 'Student',
                                    'associate' => 'Associate',
                                    default => '-'
                                };
                                ?>
                                <?= escape($categoryDisplay) ?>
                            </td>
                            <td>
                                <?php
                                // Show approval status for office bearers
                                if ($u['category'] === 'partner') {
                                    if ($u['is_approved']) {
                                        echo '<span class="badge bg-success">Approved</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    }
                                } else {
                                    echo '<span class="badge bg-secondary">N/A</span>';
                                }
                                ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <!-- Inline flex layout: keeps all action buttons in one compact horizontal row -->
                                <div class="d-flex gap-1 justify-content-center flex-nowrap">

                                    <!-- Promote to Admin: solid danger button (btn-sm keeps row height minimal) -->
                                    <form method="post" onsubmit="return attachActionReason(this, 'promoting this user to Admin');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="promote_admin">
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-sm btn-danger" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>>
                                            Promote to Admin
                                        </button>
                                    </form>

                                    <!-- Appoint as GBS Leader: solid warning with dark text for legibility -->
                                    <form method="post" onsubmit="return attachActionReason(this, 'appointing this user as a GBS Leader');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="promote_gbs_leader">
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-sm btn-warning text-dark" <?= ($locked || $u['is_gbs_leader']) ? 'disabled' : '' ?>>
                                            <i class="bi bi-star"></i> Appoint as GBS Leader
                                        </button>
                                    </form>

                                    <?php if ($u['is_gbs_leader']): ?>
                                        <!-- Remove GBS Leader: outline-secondary for a low-emphasis secondary action -->
                                        <form method="post" onsubmit="return attachActionReason(this, 'removing this GBS Leader appointment');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                            <input type="hidden" name="action" value="remove_gbs_leader">
                                            <input type="hidden" name="reason" value="">
                                            <button class="btn btn-sm btn-outline-secondary" <?= $locked ? 'disabled' : '' ?>>
                                                <i class="bi bi-star"></i> Remove GBS Leader
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Demote to Member: outline-secondary communicates a reversible, non-destructive action -->
                                    <form method="post" onsubmit="return attachActionReason(this, 'demoting this user to Member');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="demote_member">
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-sm btn-outline-secondary" <?= ($locked || $role === 'member') ? 'disabled' : '' ?>>
                                            Demote to Member
                                        </button>
                                    </form>

                                    <!-- Delete: btn-link text-danger p-1 — minimal footprint, clearly destructive colour -->
                                    <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button class="btn btn-sm btn-link text-danger p-1" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>>
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>

                                </div>
                                <?php if ($role === 'admin'): ?>
                                    <!-- Informational note beneath the actions for admin-role rows -->
                                    <div class="text-muted small mt-1">Admins can only be deleted/demoted via JDM leadership rules (supported).</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function attachActionReason(form, actionLabel) {
    const reason = window.prompt(`Please provide the reason for ${actionLabel}:`);
    if (reason === null) {
        return false;
    }

    const trimmed = reason.trim();
    if (!trimmed) {
        alert('A reason is required before completing this action.');
        return false;
    }

    form.querySelector('input[name="reason"]').value = trimmed;
    return true;
}
</script>

<?php
$content = ob_get_clean();
$page_title = "JDM Leader Dashboard - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
