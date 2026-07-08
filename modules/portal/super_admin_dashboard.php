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
    if (!require_csrf()) {
        $err = $_SESSION['csrf_error'] ?? 'Session expired. Please reload the page and try again.';
        unset($_SESSION['csrf_error']);
    } else {
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
                } elseif ($action === 'approve_missionary') {
                    if ($target['category'] !== 'missionary') {
                        $err = 'Only missionaries can be approved.';
                    } else {
                        $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?")->execute([$targetId]);
                        $msg = 'Missionary approved successfully.';
                        try {
                            $notifText = "✅ Congratulations! Your registration as a Missionary has been approved by JDM leadership.\n\nYou can now access the full member dashboard and all portal features.\n\nWelcome to the JDM Kenya missionary family!";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send Missionary approval notification: " . $e->getMessage());
                        }
                    }
                } elseif ($action === 'reject_missionary') {
                    if ($target['category'] !== 'missionary') {
                        $err = 'Only missionaries can be rejected.';
                    } else {
                        try {
                            $notifText = "ℹ️ Thank you for your interest in registering as a Missionary with JDM Kenya.\n\nUnfortunately, your application could not be approved at this time.\n\nPlease contact JDM leadership for more information.";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send Missionary rejection notification: " . $e->getMessage());
                        }
                        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                        $msg = 'Missionary registration rejected and user deleted.';
                    }
                } elseif ($action === 'appoint_leader') {
                    $leaderType = trim($_POST['leader_type'] ?? '');
                    $campusName = trim($_POST['leader_campus'] ?? '');
                    $allowedTypes = ['campus_leader', 'gbs_leader', 'worship_leader'];
                    if (!in_array($leaderType, $allowedTypes, true)) {
                        $err = 'Invalid leadership type.';
                    } elseif ($leaderType === 'campus_leader' && $campusName === '') {
                        $err = 'Please select a campus for the campus leader.';
                    } else {
                        $pdo->prepare("INSERT INTO leaders (user_id, leader_type, campus_name, created_by) VALUES (?, ?, ?, ?)")
                            ->execute([$targetId, $leaderType, $campusName ?: null, $selfId]);
                        $typeLabel = str_replace('_', ' ', $leaderType);
                        $msg = "User appointed as {$typeLabel}.";
                        try {
                            $campusInfo = $campusName ? " for {$campusName}" : '';
                            $notifText = "🎉 Congratulations! You have been appointed as a " . ucfirst($typeLabel) . "{$campusInfo}.\n\nReason from JDM leadership:\n{$reason}\n\nGod bless you in this new leadership role!";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send leadership notification: " . $e->getMessage());
                        }
                    }
                } elseif ($action === 'remove_leader') {
                    $leaderType = trim($_POST['leader_type'] ?? '');
                    $pdo->prepare("DELETE FROM leaders WHERE user_id = ? AND leader_type = ?")
                        ->execute([$targetId, $leaderType]);
                    $typeLabel = str_replace('_', ' ', $leaderType);
                    $msg = "{$typeLabel} role removed from user.";
                    try {
                        $notifText = "ℹ️ Your appointment as a " . ucfirst($typeLabel) . " has been removed by JDM leadership.\n\nReason from JDM leadership:\n{$reason}";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send leadership removal notification: " . $e->getMessage());
                    }
                } elseif ($action === 'appoint_staff') {
                    $staffType = trim($_POST['staff_type'] ?? '');
                    if (!in_array($staffType, ['part_time', 'full_time'], true)) {
                        $err = 'Invalid staff type.';
                    } else {
                        $pdo->prepare("UPDATE users SET is_staff = 1, staff_type = ? WHERE id = ?")
                            ->execute([$staffType, $targetId]);
                        $label = $staffType === 'part_time' ? 'Part-Time Staff' : 'Full-Time Staff';
                        $msg = "User appointed as {$label}.";
                        try {
                            $notifText = "🎉 Congratulations! You have been appointed as a {$label} at JDM Kenya.\n\nReason from JDM leadership:\n{$reason}\n\nThank you for your service!";
                            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                            $stmtNotif->execute([$selfId, $targetId, $notifText]);
                        } catch (PDOException $e) {
                            error_log("Failed to send staff appointment notification: " . $e->getMessage());
                        }
                    }
                } elseif ($action === 'remove_staff') {
                    $pdo->prepare("UPDATE users SET is_staff = 0, staff_type = NULL WHERE id = ?")
                        ->execute([$targetId]);
                    $msg = 'Staff role removed from user.';
                    try {
                        $notifText = "ℹ️ Your staff appointment has been removed by JDM leadership.\n\nReason from JDM leadership:\n{$reason}";
                        $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                        $stmtNotif->execute([$selfId, $targetId, $notifText]);
                    } catch (PDOException $e) {
                        error_log("Failed to send staff removal notification: " . $e->getMessage());
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
                // Log exception details for debugging
                error_log("[super_admin_dashboard] Action error: " . $e->getMessage() . " -- " . $e->getFile() . ":" . $e->getLine());
                error_log($e->getTraceAsString());
                $err = 'Operation failed: ' . $e->getMessage();
            }
        }
    }
    }
}

$saPage = max(1, (int)($_GET['sa_page'] ?? 1));
$saPerPage = 100;
$saOffset = ($saPage - 1) * $saPerPage;

$totalUsers = 0;
$totalUserPages = 1;
$users = [];

try {
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalUserPages = max(1, (int)ceil($totalUsers / $saPerPage));

    // Try the full column list first; if any column doesn't exist, fall back to basic columns
    try {
        $users = $pdo->query("SELECT id, name, email, whatsapp_phone, role, category, is_gbs_leader, is_approved, is_staff, staff_type, created_at FROM users ORDER BY created_at DESC LIMIT {$saPerPage} OFFSET {$saOffset}")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback: some columns may not exist yet (run migration via /setup.php)
        error_log('Super admin dashboard: full user query failed, falling back: ' . $e->getMessage());
        $users = $pdo->query("SELECT id, name, email, whatsapp_phone, role, category, created_at FROM users ORDER BY created_at DESC LIMIT {$saPerPage} OFFSET {$saOffset}")->fetchAll(PDO::FETCH_ASSOC);
        // Add default values for missing columns
        foreach ($users as &$u) {
            $u['is_gbs_leader'] = $u['is_gbs_leader'] ?? 0;
            $u['is_approved'] = $u['is_approved'] ?? 1;
            $u['is_staff'] = $u['is_staff'] ?? 0;
            $u['staff_type'] = $u['staff_type'] ?? null;
        }
        unset($u);
    }
} catch (PDOException $e) {
    error_log('Super admin dashboard: user count query failed: ' . $e->getMessage());
}

// =====================================================================
// OFFICE BEARER & MISSIONARY APPROVAL WORKFLOW
// =====================================================================
// Query for pending office bearer (partner) registrations that need approval
$pendingPartners = [];
try {
    $pendingPartners = $pdo->query('
        SELECT id, name, email, whatsapp_phone, employment_status, company_name, 
               industry_profession, partnership_focus, created_at 
        FROM users 
        WHERE category = "partner" AND is_approved = 0 
        ORDER BY created_at ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Columns like employment_status, company_name, etc. may not exist
    error_log('Pending partners query failed (columns may be missing): ' . $e->getMessage());
    try {
        $pendingPartners = $pdo->query('
            SELECT id, name, email, whatsapp_phone, created_at 
            FROM users 
            WHERE category = "partner" AND is_approved = 0 
            ORDER BY created_at ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pendingPartners as &$p) {
            $p['employment_status'] = $p['employment_status'] ?? '-';
            $p['company_name'] = $p['company_name'] ?? '-';
            $p['industry_profession'] = $p['industry_profession'] ?? '-';
            $p['partnership_focus'] = $p['partnership_focus'] ?? '-';
        }
        unset($p);
    } catch (PDOException $e2) {
        error_log('Pending partners fallback query also failed: ' . $e2->getMessage());
    }
}

// Query for pending missionary registrations that need approval
$pendingMissionaries = [];
try {
    $pendingMissionaries = $pdo->query('
        SELECT id, name, email, whatsapp_phone, missionary_type, country, created_at 
        FROM users 
        WHERE category = "missionary" AND is_approved = 0 
        ORDER BY created_at ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Pending missionaries query failed (columns may be missing): ' . $e->getMessage());
    try {
        $pendingMissionaries = $pdo->query('
            SELECT id, name, email, whatsapp_phone, created_at 
            FROM users 
            WHERE category = "missionary" AND is_approved = 0 
            ORDER BY created_at ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pendingMissionaries as &$m) {
            $m['missionary_type'] = $m['missionary_type'] ?? '-';
            $m['country'] = $m['country'] ?? '-';
        }
        unset($m);
    } catch (PDOException $e2) {
        error_log('Pending missionaries fallback query also failed: ' . $e2->getMessage());
    }
}

// Get all leaders for display
$leaders = [];
try {
    $leaders = $pdo->query('
        SELECT l.*, u.name as user_name, u.email as user_email 
        FROM leaders l 
        JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $leaders = [];
}

$allowedCampuses = ['Main Campus', 'Upper Kabete', 'Lower Kabete', 'Chiromo', 'Kikuyu', 'Parklands'];

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

<!-- ===================================================================== -->
<!-- PENDING MISSIONARY APPROVALS SECTION -->
<!-- ===================================================================== -->
<?php if (!empty($pendingMissionaries)): ?>
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-globe"></i> 
                Pending Missionary Approvals (<?= count($pendingMissionaries) ?>)
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
                            <th>Missionary Type</th>
                            <th>Country</th>
                            <th>Applied</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingMissionaries as $m): ?>
                            <tr>
                                <td>#<?= (int)$m['id'] ?></td>
                                <td><strong><?= escape($m['name']) ?></strong></td>
                                <td><?= escape($m['email']) ?></td>
                                <td><?= escape($m['whatsapp_phone'] ?: '-') ?></td>
                                <td><?= escape(str_replace('_', ' ', ucfirst($m['missionary_type'] ?? '-'))) ?></td>
                                <td><?= escape($m['country'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center flex-nowrap">
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                            <input type="hidden" name="action" value="approve_missionary">
                                            <button class="btn btn-sm btn-success" title="Approve this missionary">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Reject and delete this application?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                            <input type="hidden" name="action" value="reject_missionary">
                                            <button class="btn btn-sm btn-danger" title="Reject this missionary registration">
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
                        
                        // Get this user's leaders
                        $uLeaders = [];
                        try {
                            $stmtL = $pdo->prepare('SELECT leader_type, campus_name FROM leaders WHERE user_id = ?');
                            $stmtL->execute([$targetId]);
                            $uLeaders = $stmtL->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {
                            $uLeaders = [];
                        }
                        $leaderTypes = $uLeaders ? array_column($uLeaders, 'leader_type') : [];
                        
                        $isCampusLeader = in_array('campus_leader', $leaderTypes);
                        $isWorshipLeader = in_array('worship_leader', $leaderTypes);
                        $isGbsLeader = in_array('gbs_leader', $leaderTypes) || $u['is_gbs_leader'];
                        $isStaff = $u['is_staff'] ?? 0;
                        $staffType = $u['staff_type'] ?? '';
                        ?>
                        <tr>
                            <td>#<?= (int)$u['id'] ?></td>
                            <td><?= escape($u['name']) ?></td>
                            <td><?= escape($u['email']) ?></td>
                            <td><?= escape($u['whatsapp_phone']) ?></td>
                            <td>
                                <?php
                                $rb = 'bg-primary';
                                switch($role) {
                                    case 'super_admin':
                                        $rb = 'bg-dark';
                                        break;
                                    case 'admin':
                                        $rb = 'bg-danger';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $rb ?>"><?= escape($role) ?></span>
                                <?php if ($isCampusLeader): ?><span class="badge bg-info ms-1">Campus Leader</span><?php endif; ?>
                                <?php if ($isGbsLeader): ?><span class="badge bg-warning ms-1">GBS Leader</span><?php endif; ?>
                                <?php if ($isWorshipLeader): ?><span class="badge bg-success ms-1">Worship Leader</span><?php endif; ?>
                                <?php if ($isStaff): ?><span class="badge bg-secondary ms-1"><?= escape(str_replace('_', ' ', ucfirst($staffType))) ?> Staff</span><?php endif; ?>
                            </td>
                            <td>
                                <?php
                                switch($u['category'] ?? '') {
                                    case 'partner':
                                        echo '<span class="badge bg-warning text-dark"><i class="bi bi-briefcase-fill"></i> Office Bearer</span>';
                                        break;
                                    case 'student':
                                        echo '<span class="badge bg-info"><i class="bi bi-book"></i> Student</span>';
                                        break;
                                    case 'associate':
                                        echo '<span class="badge bg-success"><i class="bi bi-briefcase"></i> Associate</span>';
                                        break;
                                    case 'missionary':
                                        echo '<span class="badge text-white" style="background: linear-gradient(135deg, #6366f1, #a855f7) !important; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 2px 4px rgba(168,85,247,0.25);"><i class="bi bi-globe2"></i> Missionary</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary">Member</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($u['category'] === 'partner' || $u['category'] === 'missionary') {
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
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu" style="min-width: 220px;">
                                        <li><a class="dropdown-item" href="view_member.php?id=<?= $targetId ?>"><i class="bi bi-eye"></i> View Profile</a></li>
                                        <li><hr class="dropdown-divider"></li>

                                        <?php if ($role !== 'super_admin' && $targetId !== $selfId): ?>
                                        <li><h6 class="dropdown-header">Leadership</h6></li>
                                        
                                        <!-- Campus Leader -->
                                        <?php if (!$isCampusLeader): ?>
                                        <li>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="appoint_leader">
                                                <input type="hidden" name="leader_type" value="campus_leader">
                                                <input type="hidden" name="leader_campus" value="">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item" type="button" onclick="promptCampusLeader(this)"><i class="bi bi-building"></i> Appoint Campus Leader</button>
                                            </form>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'removing this Campus Leader');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="remove_leader">
                                                <input type="hidden" name="leader_type" value="campus_leader">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item text-danger" onclick="return attachActionReason(this.form, 'removing this Campus Leader')"><i class="bi bi-building-slash"></i> Remove Campus Leader</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>

                                        <!-- GBS Leader -->
                                        <?php if (!$isGbsLeader): ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'appointing this user as GBS Leader');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="appoint_leader">
                                                <input type="hidden" name="leader_type" value="gbs_leader">
                                                <input type="hidden" name="leader_campus" value="">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item"><i class="bi bi-people"></i> Appoint GBS Leader</button>
                                            </form>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'removing this GBS Leader');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="remove_leader">
                                                <input type="hidden" name="leader_type" value="gbs_leader">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item text-danger"><i class="bi bi-people-slash"></i> Remove GBS Leader</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>

                                        <!-- Worship Leader -->
                                        <?php if (!$isWorshipLeader): ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'appointing this user as Worship Leader');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="appoint_leader">
                                                <input type="hidden" name="leader_type" value="worship_leader">
                                                <input type="hidden" name="leader_campus" value="">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item"><i class="bi bi-music-note"></i> Appoint Worship Leader</button>
                                            </form>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'removing this Worship Leader');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="remove_leader">
                                                <input type="hidden" name="leader_type" value="worship_leader">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item text-danger"><i class="bi bi-music-note-slash"></i> Remove Worship Leader</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>

                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Staff</h6></li>
                                        
                                        <?php if (!$isStaff): ?>
                                        <li>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="appoint_staff">
                                                <input type="hidden" name="staff_type" value="">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item" type="button" onclick="promptStaffType(this)"><i class="bi bi-person-badge"></i> Appoint as Staff</button>
                                            </form>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'removing this Staff appointment');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="remove_staff">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item text-danger"><i class="bi bi-person-badge-slash"></i> Remove Staff</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>

                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Admin Actions</h6></li>
                                        
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'promoting this user to Admin');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="promote_admin">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>><i class="bi bi-shield"></i> Promote to Admin</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="post" onsubmit="return attachActionReason(this, 'demoting this user to Member');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="demote_member">
                                                <input type="hidden" name="reason" value="">
                                                <button class="dropdown-item" <?= ($locked || $role === 'member') ? 'disabled' : '' ?>><i class="bi bi-arrow-down"></i> Demote to Member</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $targetId ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button class="dropdown-item text-danger" <?= ($locked || $role === 'admin') ? 'disabled' : '' ?>><i class="bi bi-trash"></i> Delete User</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php if ($role === 'admin'): ?>
                                    <div class="text-muted small mt-1">Admins protected from delete/demote.</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalUserPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center mt-3">
        <?php if ($saPage > 1): ?>
            <li class="page-item"><a class="page-link" href="?sa_page=<?= $saPage - 1 ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalUserPages; $i++): ?>
            <li class="page-item <?= $i === $saPage ? 'active' : '' ?>">
                <a class="page-link" href="?sa_page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($saPage < $totalUserPages): ?>
            <li class="page-item"><a class="page-link" href="?sa_page=<?= $saPage + 1 ?>">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Campus Leader Modal -->
<div class="modal fade" id="campusLeaderModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building"></i> Appoint Campus Leader</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Campus</label>
                    <select id="modalCampus" class="form-select">
                        <option value="">Select campus...</option>
                        <?php foreach ($allowedCampuses as $c): ?>
                        <option value="<?= escape($c) ?>"><?= escape($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea id="modalCampusReason" class="form-control" rows="3" placeholder="Enter the reason for this appointment..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCampusLeader">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Staff Type Modal -->
<div class="modal fade" id="staffTypeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Appoint as Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Staff Type</label>
                    <select id="modalStaffType" class="form-select">
                        <option value="">Select staff type...</option>
                        <option value="part_time">Part-Time</option>
                        <option value="full_time">Full-Time</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea id="modalStaffReason" class="form-control" rows="3" placeholder="Enter the reason for this appointment..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStaffType">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
let modalBtn = null;

function attachActionReason(form, actionLabel) {
    if (!form) return false;
    const reason = window.prompt(`Please provide the reason for ${actionLabel}:`);
    if (reason === null) return false;
    const trimmed = reason.trim();
    if (!trimmed) {
        alert('A reason is required before completing this action.');
        return false;
    }
    const reasonInput = form.querySelector('input[name="reason"]');
    if (reasonInput) reasonInput.value = trimmed;
    return true;
}

function promptCampusLeader(btn) {
    modalBtn = btn;
    document.getElementById('modalCampus').value = '';
    document.getElementById('modalCampusReason').value = '';
    // keep a reference to the modal instance so we can reliably hide it
    window._campusLeaderModalInstance = new bootstrap.Modal(document.getElementById('campusLeaderModal'));
    window._campusLeaderModalInstance.show();
    return false;
}

function promptStaffType(btn) {
    modalBtn = btn;
    document.getElementById('modalStaffType').value = '';
    document.getElementById('modalStaffReason').value = '';
    // keep a reference to the modal instance so we can reliably hide it
    window._staffTypeModalInstance = new bootstrap.Modal(document.getElementById('staffTypeModal'));
    window._staffTypeModalInstance.show();
    return false;
}

document.getElementById('confirmCampusLeader').addEventListener('click', function () {
    const campus = document.getElementById('modalCampus').value;
    const reason = document.getElementById('modalCampusReason').value.trim();
    if (!campus) {
        alert('Please select a campus.');
        return;
    }
    if (!reason) {
        alert('A reason is required.');
        return;
    }
    try {
        if (!modalBtn) throw new Error('No modal trigger button found');
        const form = modalBtn.closest('form');
        if (!form) throw new Error('Form not found');
        const lc = form.querySelector('input[name="leader_campus"]');
        const rr = form.querySelector('input[name="reason"]');
        if (!lc || !rr) throw new Error('Hidden inputs missing');
        lc.value = campus;
        rr.value = reason;
        if (window._campusLeaderModalInstance) {
            window._campusLeaderModalInstance.hide();
        } else {
            const inst = bootstrap.Modal.getInstance(document.getElementById('campusLeaderModal'));
            if (inst) inst.hide();
        }
        // ensure any stray backdrop is removed and body/html styles/classes cleaned
        setTimeout(function(){
            document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.paddingRight = '';
        }, 80);
        form.submit();
    } catch (e) {
        console.error('Confirm campus leader error:', e);
        alert('Unable to complete action. Please try again.');
    }
});

document.getElementById('confirmStaffType').addEventListener('click', function () {
    const type = document.getElementById('modalStaffType').value;
    const reason = document.getElementById('modalStaffReason').value.trim();
    if (!type) {
        alert('Please select a staff type.');
        return;
    }
    if (!reason) {
        alert('A reason is required.');
        return;
    }
    try {
        if (!modalBtn) throw new Error('No modal trigger button found');
        const form = modalBtn.closest('form');
        if (!form) throw new Error('Form not found');
        const st = form.querySelector('input[name="staff_type"]');
        const rr = form.querySelector('input[name="reason"]');
        if (!st || !rr) throw new Error('Hidden inputs missing');
        st.value = type;
        rr.value = reason;
        if (window._staffTypeModalInstance) {
            window._staffTypeModalInstance.hide();
        } else {
            const inst = bootstrap.Modal.getInstance(document.getElementById('staffTypeModal'));
            if (inst) inst.hide();
        }
        // ensure any stray backdrop is removed and body/html styles/classes cleaned
        setTimeout(function(){
            document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.paddingRight = '';
        }, 80);
        form.submit();
    } catch (e) {
        console.error('Confirm staff type error:', e);
        alert('Unable to complete action. Please try again.');
    }
});
</script>

<?php
$content = ob_get_clean();
$page_title = "JDM Leader Dashboard - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
