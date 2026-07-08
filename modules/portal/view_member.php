<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

$viewerRole = $_SESSION['user_role'] ?? '';
$viewerId = (int)($_SESSION['user_id'] ?? 0);

$isAdmin = ($viewerRole === 'admin' || $viewerRole === 'super_admin');
$sessionAdmin = $isAdmin;

$sessionGbsLeader = false;
if ($viewerId > 0) {
    $stmtGbs = $pdo->prepare("SELECT COUNT(*) as c FROM gbs_members WHERE user_id = ? AND role = 'leader'");
    $stmtGbs->execute([$viewerId]);
    $gbsCheck = $stmtGbs->fetch(PDO::FETCH_ASSOC);
    $sessionGbsLeader = ($gbsCheck && $gbsCheck['c'] > 0);
}

$isOfficeBearer = false;
if (!$isAdmin && $viewerId > 0) {
    $stmtViewer = $pdo->prepare('SELECT category, is_approved FROM users WHERE id = ? AND category = "partner" LIMIT 1');
    $stmtViewer->execute([$viewerId]);
    $viewerData = $stmtViewer->fetch(PDO::FETCH_ASSOC);
    $isOfficeBearer = ($viewerData && $viewerData['is_approved']);
}

if (!$isAdmin && !$isOfficeBearer) {
    header('Location: login.php');
    exit;
}
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT
            u.id, u.name, u.email, u.whatsapp_phone, u.role, u.category,
            u.created_at, u.pfp_path, u.is_approved,
            u.graduation_year, u.campus_role, u.employment_status,
            u.company_name, u.industry_profession, u.partnership_focus,
            u.contribution_phone, u.missionary_type, u.country,
            u.is_staff, u.staff_type,
            a.graduation_year AS assoc_grad_year,
            a.current_profession AS assoc_profession
        FROM users u
        LEFT JOIN associates a ON u.id = a.user_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
        $msg = sprintf("[view_member][Fetch] %s: %s in %s:%d", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        error_log($msg);
        // Also write to a project-level debug log for hosting environments where Apache logs are inaccessible
        $projectRoot = dirname(dirname(dirname(__FILE__)));
        $logFile = $projectRoot . '/tmp/view_member_debug.log';
        @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
        if ($e instanceof PDOException) {
            $info = $e->errorInfo ?? null;
            $pdoMsg = sprintf('[view_member][Fetch][PDO] SQLSTATE=%s Code=%s Message=%s', $info[0] ?? 'unknown', $info[1] ?? 'unknown', $info[2] ?? '');
            error_log($pdoMsg);
            @file_put_contents($logFile, date('c') . ' ' . $pdoMsg . PHP_EOL, FILE_APPEND);
        }
        $err = 'Unable to load member details at this time. Please try again later.';
        $u = false;
}

if (!$u) {
    if (empty($err)) {
        header('Location: view_members.php');
        exit;
    }

    ob_start();
    ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= escape($err) ?>
    </div>
    <?php
    $content = ob_get_clean();
    $page_title = "View Member - JDM Kenya";
    include(__DIR__ . '/layout.php');
    exit;
}

if ($viewerRole === 'admin' && ($u['role'] === 'admin' || $u['role'] === 'super_admin')) {
    header('Location: view_members.php');
    exit;
}

if ($u['category'] === 'partner' && $viewerRole !== 'super_admin' && $viewerId !== (int)$u['id']) {
    header('Location: view_members.php');
    exit;
}

if ($u['category'] === 'missionary' && $viewerRole !== 'super_admin' && $viewerId !== (int)$u['id']) {
    header('Location: view_members.php');
    exit;
}

// Get user's leadership roles
$uLeaders = [];
$allowedCampuses = ['Main Campus', 'Upper Kabete', 'Lower Kabete', 'Chiromo', 'Kikuyu', 'Parklands'];
try {
    $stmtL = $pdo->prepare('SELECT leader_type, campus_name FROM leaders WHERE user_id = ?');
    $stmtL->execute([$id]);
    $uLeaders = $stmtL->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[view_member] leaders query failed: ' . $e->getMessage());
    $uLeaders = [];
}
$leaderTypes = array_column($uLeaders, 'leader_type');
$isCampusLeader   = in_array('campus_leader',   $leaderTypes);
$isGbsLeader      = in_array('gbs_leader',      $leaderTypes);
$isWorshipLeader  = in_array('worship_leader',  $leaderTypes);
$isStaff          = $u['is_staff'] ?? 0;

$isOwnProfile = ($viewerId === $id);

// Handle post actions
$msg = '';
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $viewerRole === 'super_admin') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $selfId = (int)($_SESSION['user_id'] ?? 0);
    $targetId = $id;

    if ($targetId === $selfId || $u['role'] === 'super_admin') {
        $err = 'You cannot perform actions on this user.';
    } else {
        try {
            if ($action === 'appoint_leader') {
                $leaderType = trim($_POST['leader_type'] ?? '');
                $campusName = trim($_POST['leader_campus'] ?? '');
                $allowedTypes = ['campus_leader', 'gbs_leader', 'worship_leader'];
                if (!in_array($leaderType, $allowedTypes, true)) {
                    $err = 'Invalid leadership type.';
                } elseif ($leaderType === 'campus_leader' && $campusName === '') {
                    $err = 'Please select a campus.';
                } else {
                    $pdo->prepare("INSERT INTO leaders (user_id, leader_type, campus_name, created_by) VALUES (?, ?, ?, ?)")
                        ->execute([$targetId, $leaderType, $campusName ?: null, $selfId]);
                    $typeLabel = str_replace('_', ' ', $leaderType);
                    $msg = "User appointed as {$typeLabel}.";
                    $campusInfo = $campusName ? " for {$campusName}" : '';
                    $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                    $stmtNotif->execute([$selfId, $targetId, "🎉 Congratulations! You have been appointed as a " . ucfirst($typeLabel) . "{$campusInfo}.\n\nReason: {$reason}"]);
                }
            } elseif ($action === 'remove_leader') {
                $leaderType = trim($_POST['leader_type'] ?? '');
                $pdo->prepare("DELETE FROM leaders WHERE user_id = ? AND leader_type = ?")->execute([$targetId, $leaderType]);
                $typeLabel = str_replace('_', ' ', $leaderType);
                $msg = "{$typeLabel} role removed.";
                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                $stmtNotif->execute([$selfId, $targetId, "ℹ️ Your appointment as a " . ucfirst($typeLabel) . " has been removed.\n\nReason: {$reason}"]);
            } elseif ($action === 'appoint_staff') {
                $staffType = trim($_POST['staff_type'] ?? '');
                if (!in_array($staffType, ['part_time', 'full_time'], true)) {
                    $err = 'Invalid staff type.';
                } else {
                    $pdo->prepare("UPDATE users SET is_staff = 1, staff_type = ? WHERE id = ?")->execute([$staffType, $targetId]);
                    $label = $staffType === 'part_time' ? 'Part-Time Staff' : 'Full-Time Staff';
                    $msg = "User appointed as {$label}.";
                    $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                    $stmtNotif->execute([$selfId, $targetId, "🎉 Congratulations! You have been appointed as {$label}.\n\nReason: {$reason}"]);
                }
            } elseif ($action === 'remove_staff') {
                $pdo->prepare("UPDATE users SET is_staff = 0, staff_type = NULL WHERE id = ?")->execute([$targetId]);
                $msg = 'Staff role removed.';
                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                $stmtNotif->execute([$selfId, $targetId, "ℹ️ Your staff appointment has been removed.\n\nReason: {$reason}"]);
            } elseif ($action === 'promote_admin') {
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
                $msg = 'User promoted to admin.';
                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                $stmtNotif->execute([$selfId, $targetId, "🛡️ Congratulations! You have been promoted to Admin.\n\nReason: {$reason}"]);
            } elseif ($action === 'demote_member') {
                $pdo->prepare("UPDATE users SET role = 'member' WHERE id = ?")->execute([$targetId]);
                // Also clear is_gbs_leader if the column exists (graceful fallback)
                try {
                    $pdo->prepare("UPDATE users SET is_gbs_leader = 0 WHERE id = ?")->execute([$targetId]);
                } catch (Throwable $ignored) {}
                $pdo->prepare("DELETE FROM leaders WHERE user_id = ?")->execute([$targetId]);
                $msg = 'User demoted to member.';
                $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
                $stmtNotif->execute([$selfId, $targetId, "ℹ️ Your account role has been updated to Member.\n\nReason: {$reason}"]);
            } elseif ($action === 'delete_user') {
                require_once dirname(__FILE__) . '/../../core/users.php';
                deleteUser($pdo, $targetId);
                header('Location: view_members.php?deleted=1');
                exit;
            }
        } catch (Throwable $e) {
            // Log full exception for debugging (server error log)
                $msg = sprintf("[view_member][Action] %s: %s in %s:%d", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
                error_log($msg);
                // Also write to a project-level debug log so you can view errors via File Manager
                $projectRoot = dirname(dirname(dirname(__FILE__)));
                $logFile = $projectRoot . '/tmp/view_member_debug.log';
                @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
                if ($e instanceof PDOException) {
                    $info = $e->errorInfo ?? null;
                    $pdoMsg = sprintf('[view_member][Action][PDO] SQLSTATE=%s Code=%s Message=%s', $info[0] ?? 'unknown', $info[1] ?? 'unknown', $info[2] ?? '');
                    error_log($pdoMsg);
                    @file_put_contents($logFile, date('c') . ' ' . $pdoMsg . PHP_EOL, FILE_APPEND);
                }
                error_log($e->getTraceAsString());
                @file_put_contents($logFile, date('c') . ' TRACE ' . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
                $err = 'Operation failed: ' . $e->getMessage();
        }
    }
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
            <?php if ($viewerRole === 'super_admin' && !$isOwnProfile && $u['role'] !== 'super_admin'): ?>
                <a href="view_members.php" class="btn btn-secondary">Back</a>
            <?php else: ?>
                <a href="view_members.php" class="btn btn-secondary">Back</a>
            <?php endif; ?>
        </div>
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

<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-card-text"></i> User Profile</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-3 text-center">
                <?php if (!empty($u['pfp_path'])): ?>
                    <img src="<?= escape($u['pfp_path']) ?>" class="img-fluid rounded-circle" style="width:120px;height:120px;object-fit:cover;" alt="Profile picture">
                <?php else: ?>
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width:120px;height:120px;">
                        <i class="bi bi-person text-secondary" style="font-size:3rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-9">
                <div class="row g-2">
                    <div class="col-12 col-md-6"><strong>Name:</strong> <?= escape($u['name']) ?></div>
                    <div class="col-12 col-md-6"><strong>Email:</strong> <?= $isAdmin ? escape($u['email']) : escape(maskEmail($u['email'] ?? '')) ?></div>
                    <div class="col-12 col-md-6"><strong>WhatsApp:</strong> <?= $isAdmin ? escape($u['whatsapp_phone']) : escape(maskPhone($u['whatsapp_phone'] ?? '')) ?></div>
                    <div class="col-12 col-md-6">
                        <strong>Category:</strong>
                        <?php
                        $catLabel = '<span class="badge bg-secondary">Member</span>';
                        switch ($u['category'] ?? '') {
                            case 'student':
                                $catLabel = '<span class="badge bg-info"><i class="bi bi-book"></i> Student</span>';
                                break;
                            case 'associate':
                                $catLabel = '<span class="badge bg-success"><i class="bi bi-briefcase"></i> Associate</span>';
                                break;
                            case 'partner':
                                $catLabel = '<span class="badge bg-warning text-dark"><i class="bi bi-briefcase-fill"></i> Office Bearer</span>';
                                break;
                            case 'missionary':
                                $catLabel = '<span class="badge text-white" style="background: linear-gradient(135deg, #6366f1, #a855f7) !important; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 2px 4px rgba(168,85,247,0.25);"><i class="bi bi-globe2"></i> Missionary</span>';
                                break;
                            case 'other':
                                $catLabel = '<span class="badge bg-secondary">Member</span>';
                                break;
                        }
                        echo $catLabel;
                        ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <strong>Role:</strong> <?= escape($u['role']) ?>
                        <?php if ($isCampusLeader): ?> <span class="badge bg-info">Campus Leader</span><?php endif; ?>
                        <?php if ($isGbsLeader): ?> <span class="badge bg-warning">GBS Leader</span><?php endif; ?>
                        <?php if ($isWorshipLeader): ?> <span class="badge bg-success">Worship Leader</span><?php endif; ?>
                        <?php if ($isStaff): ?> <span class="badge bg-secondary"><?= escape(ucfirst(str_replace('_', ' ', $u['staff_type'] ?? ''))) ?> Staff</span><?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6"><strong>Joined:</strong> <?= date('M d, Y', strtotime($u['created_at'])) ?></div>

                    <?php if ($u['category'] === 'associate'): ?>
                        <div class="col-12 col-md-6"><strong>Graduation Year:</strong> <?= escape($u['assoc_grad_year'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Current Profession:</strong> <?= escape($u['assoc_profession'] ?? '-') ?></div>
                    <?php endif; ?>
                    <?php if ($u['category'] === 'missionary'): ?>
                        <div class="col-12 col-md-6"><strong>Missionary Type:</strong> <?= escape(str_replace('_', ' ', ucfirst($u['missionary_type'] ?? '-'))) ?></div>
                        <div class="col-12 col-md-6"><strong>Country:</strong> <?= escape($u['country'] ?? '-') ?></div>
                    <?php endif; ?>
                    <?php if ($u['category'] === 'partner' && $viewerRole === 'super_admin'): ?>
                        <div class="col-12"><hr></div>
                        <div class="col-12 col-md-6"><strong>Graduation Year:</strong> <?= escape($u['graduation_year'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Campus Role:</strong> <?= escape($u['campus_role'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Employment:</strong> <?= escape($u['employment_status'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Company:</strong> <?= escape($u['company_name'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Industry:</strong> <?= escape($u['industry_profession'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Partnership Focus:</strong> <?= escape($u['partnership_focus'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($viewerRole === 'super_admin' && !$isOwnProfile && $u['role'] !== 'super_admin'): ?>
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Admin Actions</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Leadership Actions -->
            <div class="col-12">
                <h6 class="fw-bold">Leadership <small class="text-muted">Appoint or remove leadership roles</small></h6>
            </div>
            <div class="col-md-4">
                <?php if (!$isCampusLeader): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="appoint_leader">
                    <input type="hidden" name="leader_type" value="campus_leader">
                    <input type="hidden" name="leader_campus" value="">
                    <input type="hidden" name="reason" value="">
                    <button type="button" class="btn btn-outline-info w-100" onclick="promptCampusLeader(this.form)"><i class="bi bi-building"></i> Appoint Campus Leader</button>
                </form>
                <?php else: ?>
                <form method="post" onsubmit="return promptReason(this, 'removing Campus Leader')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_leader">
                    <input type="hidden" name="leader_type" value="campus_leader">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-building-slash"></i> Remove Campus Leader</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <?php if (!$isGbsLeader): ?>
                <form method="post" onsubmit="return promptReason(this, 'appointing GBS Leader')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="appoint_leader">
                    <input type="hidden" name="leader_type" value="gbs_leader">
                    <input type="hidden" name="leader_campus" value="">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-warning w-100"><i class="bi bi-people"></i> Appoint GBS Leader</button>
                </form>
                <?php else: ?>
                <form method="post" onsubmit="return promptReason(this, 'removing GBS Leader')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_leader">
                    <input type="hidden" name="leader_type" value="gbs_leader">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-people-slash"></i> Remove GBS Leader</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <?php if (!$isWorshipLeader): ?>
                <form method="post" onsubmit="return promptReason(this, 'appointing Worship Leader')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="appoint_leader">
                    <input type="hidden" name="leader_type" value="worship_leader">
                    <input type="hidden" name="leader_campus" value="">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-success w-100"><i class="bi bi-music-note"></i> Appoint Worship Leader</button>
                </form>
                <?php else: ?>
                <form method="post" onsubmit="return promptReason(this, 'removing Worship Leader')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_leader">
                    <input type="hidden" name="leader_type" value="worship_leader">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-music-note-slash"></i> Remove Worship Leader</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Staff Actions -->
            <div class="col-12 mt-3">
                <h6 class="fw-bold">Staff <small class="text-muted">Appoint or remove staff role</small></h6>
            </div>
            <div class="col-md-4">
                <?php if (!$isStaff): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="appoint_staff">
                    <input type="hidden" name="staff_type" value="">
                    <input type="hidden" name="reason" value="">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="promptStaffType(this.form)"><i class="bi bi-person-badge"></i> Appoint as Staff</button>
                </form>
                <?php else: ?>
                <form method="post" onsubmit="return promptReason(this, 'removing Staff')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_staff">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-person-badge-slash"></i> Remove Staff</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Admin/Destructive Actions -->
            <div class="col-12 mt-3">
                <h6 class="fw-bold text-danger">Danger Zone <small class="text-muted">Destructive actions</small></h6>
            </div>
            <div class="col-md-4">
                <form method="post" onsubmit="return promptReason(this, 'promoting to Admin')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="promote_admin">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100" <?= ($u['role'] === 'admin') ? 'disabled' : '' ?>><i class="bi bi-shield"></i> Promote to Admin</button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="post" onsubmit="return promptReason(this, 'demoting to Member')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="demote_member">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-warning w-100" <?= ($u['role'] === 'member') ? 'disabled' : '' ?>><i class="bi bi-arrow-down"></i> Demote to Member</button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="post" onsubmit="return confirm('Delete this user permanently? This cannot be undone.') && promptReason(this, 'deleting user')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="reason" value="">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
const allowedCampuses = <?= json_encode($allowedCampuses) ?>;
let modalForm = null;

function promptReason(form, actionLabel) {
    const reason = window.prompt(`Please provide the reason for ${actionLabel}:`);
    if (reason === null) return false;
    const trimmed = reason.trim();
    if (!trimmed) {
        alert('A reason is required.');
        return false;
    }
    const input = form.querySelector('input[name="reason"]');
    if (input) input.value = trimmed;
    return true;
}

function promptCampusLeader(form) {
    modalForm = form;
    document.getElementById('modalCampus').value = '';
    document.getElementById('modalCampusReason').value = '';
    // create and keep a reference to the modal instance so we can reliably hide it
    window._campusLeaderModalInstance = new bootstrap.Modal(document.getElementById('campusLeaderModal'));
    window._campusLeaderModalInstance.show();
    return false;
}

function promptStaffType(form) {
    modalForm = form;
    document.getElementById('modalStaffType').value = '';
    document.getElementById('modalStaffReason').value = '';
    // create and keep a reference to this modal instance
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
        if (!modalForm) throw new Error('Form not available');
        const lc = modalForm.querySelector('input[name="leader_campus"]');
        const rr = modalForm.querySelector('input[name="reason"]');
        if (!lc || !rr) throw new Error('Required hidden inputs not found');
        lc.value = campus;
        rr.value = reason;
        if (window._campusLeaderModalInstance) {
            window._campusLeaderModalInstance.hide();
        } else {
            const inst = bootstrap.Modal.getInstance(document.getElementById('campusLeaderModal'));
            if (inst) inst.hide();
        }
        // cleanup any leftover backdrop elements and body/html styles/classes
        setTimeout(function(){
            document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.paddingRight = '';
        }, 80);
        modalForm.submit();
    } catch (e) {
        console.error('Campus leader confirm error:', e);
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
        if (!modalForm) throw new Error('Form not available');
        const st = modalForm.querySelector('input[name="staff_type"]');
        const rr = modalForm.querySelector('input[name="reason"]');
        if (!st || !rr) throw new Error('Required hidden inputs not found');
        st.value = type;
        rr.value = reason;
        if (window._staffTypeModalInstance) {
            window._staffTypeModalInstance.hide();
        } else {
            const inst = bootstrap.Modal.getInstance(document.getElementById('staffTypeModal'));
            if (inst) inst.hide();
        }
        // cleanup any leftover backdrop elements and body/html styles/classes
        setTimeout(function(){
            document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.paddingRight = '';
        }, 80);
        modalForm.submit();
    } catch (e) {
        console.error('Staff confirm error:', e);
        alert('Unable to complete action. Please try again.');
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = "View Member - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
