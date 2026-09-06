<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';

// Handle approve/reject actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) {
        $err = $_SESSION['csrf_error'] ?? 'Session expired. Please reload.';
        unset($_SESSION['csrf_error']);
    } else {
        $action = $_POST['action'] ?? '';
        $targetId = (int)($_POST['user_id'] ?? 0);
        $selfId = (int)($_SESSION['user_id'] ?? 0);

        if ($targetId <= 0) {
            $err = 'Invalid user.';
        } else {
            $stmt = $pdo->prepare('SELECT id, role, category, is_approved FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                $err = 'User not found.';
            } else {
                try {
                    if ($action === 'approve_partner') {
                        if ($target['category'] !== 'partner') {
                            $err = 'Only office bearers can be approved.';
                        } else {
                            $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?")->execute([$targetId]);
                            $msg = 'Office bearer approved successfully.';
                            try {
                                $notifText = "✅ Great news! Your registration as an Office Bearer has been approved by JDM leadership.\n\nYou can now access the full member dashboard, resources, and all portal features.\n\nThank you for your commitment to serve JDM Kenya!";
                                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                                $stmtNotif->execute([$selfId, $targetId, $notifText]);
                            } catch (PDOException $e) {
                                error_log("Failed to send approval notification: " . $e->getMessage());
                            }
                        }
                    } elseif ($action === 'reject_partner') {
                        if ($target['category'] !== 'partner') {
                            $err = 'Only office bearers can be rejected.';
                        } else {
                            try {
                                $notifText = "ℹ️ Thank you for your interest in registering as an Office Bearer with JDM Kenya.\n\nUnfortunately, your application could not be approved at this time.\n\nPlease contact JDM leadership for more information.";
                                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                                $stmtNotif->execute([$selfId, $targetId, $notifText]);
                            } catch (PDOException $e) {
                                error_log("Failed to send rejection notification: " . $e->getMessage());
                            }
                            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                            $msg = 'Office bearer application rejected and deleted.';
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
                                error_log("Failed to send missionary approval notification: " . $e->getMessage());
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
                                error_log("Failed to send missionary rejection notification: " . $e->getMessage());
                            }
                            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                            $msg = 'Missionary application rejected and deleted.';
                        }
                    } else {
                        $err = 'Unknown action.';
                    }
                } catch (Throwable $e) {
                    $err = 'Operation failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch pending applications
$pendingPartners = [];
$pendingMissionaries = [];

try {
    $pendingPartners = $pdo->query('
        SELECT id, name, email, whatsapp_phone, employment_status, company_name,
               industry_profession, partnership_focus, created_at
        FROM users
        WHERE category = "partner" AND is_approved = 0
        ORDER BY created_at ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Pending partners query failed: ' . $e->getMessage());
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
        error_log('Pending partners fallback also failed: ' . $e2->getMessage());
    }
}

try {
    $pendingMissionaries = $pdo->query('
        SELECT id, name, email, whatsapp_phone, missionary_type, country, created_at
        FROM users
        WHERE category = "missionary" AND is_approved = 0
        ORDER BY created_at ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Pending missionaries query failed: ' . $e->getMessage());
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
        error_log('Pending missionaries fallback also failed: ' . $e2->getMessage());
    }
}

$totalPending = count($pendingPartners) + count($pendingMissionaries);

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-file-earmark-text"></i> Applications</h2>
            <p class="text-muted mb-0">Review and manage pending office bearer and missionary applications.</p>
        </div>
        <a href="super_admin_dashboard.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
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

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-briefcase-fill text-warning" style="font-size: 2rem;"></i>
                <h3 class="text-warning mt-2 mb-0"><?= count($pendingPartners) ?></h3>
                <p class="text-muted mb-0">Pending Office Bearer Applications</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card border-info h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-globe text-info" style="font-size: 2rem;"></i>
                <h3 class="text-info mt-2 mb-0"><?= count($pendingMissionaries) ?></h3>
                <p class="text-muted mb-0">Pending Missionary Applications</p>
            </div>
        </div>
    </div>
</div>

<?php if ($totalPending === 0): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
            <h4 class="text-muted mt-3">No Pending Applications</h4>
            <p class="text-muted">All applications have been reviewed. Check back later for new submissions.</p>
        </div>
    </div>
<?php else: ?>

<!-- Pending Office Bearer Applications -->
<?php if (!empty($pendingPartners)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-clock-history"></i>
                Office Bearer Applications (<?= count($pendingPartners) ?>)
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
                            <th>Employment</th>
                            <th>Company</th>
                            <th>Applied</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPartners as $partner): ?>
                            <tr>
                                <td>#<?= (int)$partner['id'] ?></td>
                                <td>
                                    <strong><?= escape($partner['name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        📍 <?= escape($partner['partnership_focus'] ?? 'No focus') ?>
                                    </small>
                                </td>
                                <td><a href="mailto:<?= escape($partner['email']) ?>"><?= escape($partner['email']) ?></a></td>
                                <td><?= escape($partner['whatsapp_phone']) ?></td>
                                <td><?= escape($partner['employment_status'] ?? '-') ?></td>
                                <td><?= escape($partner['company_name'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($partner['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center flex-nowrap">
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$partner['id'] ?>">
                                            <input type="hidden" name="action" value="approve_partner">
                                            <button class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Reject and delete this application?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$partner['id'] ?>">
                                            <input type="hidden" name="action" value="reject_partner">
                                            <button class="btn btn-sm btn-danger">
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

<!-- Pending Missionary Applications -->
<?php if (!empty($pendingMissionaries)): ?>
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-globe"></i>
                Missionary Applications (<?= count($pendingMissionaries) ?>)
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
                                            <button class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Reject and delete this application?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                            <input type="hidden" name="action" value="reject_missionary">
                                            <button class="btn btn-sm btn-danger">
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

<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = "Applications - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
