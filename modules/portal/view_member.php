<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// =====================================================================
// ACCESS CONTROL: Allow admins, super_admins, and approved office bearers
// =====================================================================
$viewerRole = $_SESSION['user_role'] ?? '';
$viewerId = (int)($_SESSION['user_id'] ?? 0);

// Check if user is admin or super_admin
$isAdmin = ($viewerRole === 'admin' || $viewerRole === 'super_admin');
$sessionAdmin = $isAdmin;

// Check if user is a GBS leader
$sessionGbsLeader = false;
if ($viewerId > 0) {
    $stmtGbs = $pdo->prepare("SELECT COUNT(*) as c FROM gbs_members WHERE user_id = ? AND role = 'leader'");
    $stmtGbs->execute([$viewerId]);
    $gbsCheck = $stmtGbs->fetch(PDO::FETCH_ASSOC);
    $sessionGbsLeader = ($gbsCheck && $gbsCheck['c'] > 0);
}

// Check if user is an approved office bearer (partner)
$isOfficeBearer = false;
if (!$isAdmin && $viewerId > 0) {
    $stmtViewer = $pdo->prepare('SELECT category, is_approved FROM users WHERE id = ? AND category = "partner" LIMIT 1');
    $stmtViewer->execute([$viewerId]);
    $viewerData = $stmtViewer->fetch(PDO::FETCH_ASSOC);
    $isOfficeBearer = ($viewerData && $viewerData['is_approved']);
}

// Only admins, super_admins, and approved office bearers can view member profiles
if (!$isAdmin && !$isOfficeBearer) {
    header('Location: login.php');
    exit;
}
$id         = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}

// Fetch full user profile including new Office Bearer fields stored on the users table
$stmt = $pdo->prepare('
    SELECT
        u.id, u.name, u.email, u.whatsapp_phone, u.role, u.category,
        u.created_at, u.pfp_path,
        u.graduation_year, u.campus_role, u.employment_status,
        u.company_name, u.industry_profession, u.partnership_focus,
        u.contribution_phone,
        a.graduation_year  AS assoc_grad_year,
        a.current_profession AS assoc_profession
    FROM users u
    LEFT JOIN associates a ON u.id = a.user_id
    WHERE u.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    header('Location: view_members.php');
    exit;
}

// Admins cannot view other admins / JDM Leaders
if ($viewerRole === 'admin' && ($u['role'] === 'admin' || $u['role'] === 'super_admin')) {
    header('Location: view_members.php');
    exit;
}

// Only super_admin can view Office Bearer (partner) profiles
if ($u['category'] === 'partner' && $viewerRole !== 'super_admin') {
    header('Location: view_members.php');
    exit;
}

$isOwnProfile = ($viewerId === $id);

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
                <a href="<?= BASE_PATH ?>/delete_member.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.');"><i class="bi bi-trash"></i> Delete User</a>
            <?php endif; ?>
            <a href="view_members.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>

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
                    <div class="col-12 col-md-6"><strong>Email:</strong> <?= escape($u['email']) ?></div>
                    <div class="col-12 col-md-6"><strong>WhatsApp:</strong> <?= $sessionAdmin || $sessionGbsLeader ? escape($u['whatsapp_phone']) : escape(maskPhone($u['whatsapp_phone'] ?? '')) ?></div>
                    <div class="col-12 col-md-6">
                        <strong>Category:</strong>
                        <?php
                        $catLabel = match($u['category']) {
                            'student'   => '<span class="badge bg-info"><i class="bi bi-book"></i> Student</span>',
                            'associate' => '<span class="badge bg-success"><i class="bi bi-briefcase"></i> Associate</span>',
                            'partner'   => '<span class="badge bg-warning text-dark"><i class="bi bi-briefcase-fill"></i> Office Bearer</span>',
                            'other'     => '<span class="badge bg-secondary">Member</span>',
                            default     => '<span class="badge bg-secondary">Member</span>',
                        };
                        echo $catLabel;
                        ?>
                    </div>
                    <div class="col-12 col-md-6"><strong>Role:</strong> <?= escape($u['role']) ?></div>
                    <div class="col-12 col-md-6"><strong>Joined:</strong> <?= date('M d, Y', strtotime($u['created_at'])) ?></div>

                    <?php if ($u['category'] === 'associate'): ?>
                        <div class="col-12 col-md-6"><strong>Graduation Year:</strong> <?= escape($u['assoc_grad_year'] ?? '-') ?></div>
                        <div class="col-12 col-md-6"><strong>Current Profession:</strong> <?= escape($u['assoc_profession'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($u['category'] === 'partner' && $viewerRole === 'super_admin'): ?>
<!-- Office Bearer Detailed Profile — visible to JDM Leader only -->
<div class="card border-warning">
    <div class="card-header text-white fw-bold" style="background-color:#fd7e14;">
        <i class="bi bi-briefcase-fill"></i> Office Bearer Details <span class="badge bg-dark ms-2"><i class="bi bi-lock-fill"></i> JDM Leader only</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12"><h6 class="text-muted text-uppercase small fw-bold mb-0">Academic &amp; JDM History</h6><hr class="mt-1 mb-2"></div>
            <div class="col-12 col-md-6">
                <strong>UON Graduation Year:</strong>
                <?= escape($u['graduation_year'] ?? '-') ?>
            </div>
            <div class="col-12 col-md-6">
                <strong>Campus Role Held:</strong>
                <?= escape($u['campus_role'] ?? '-') ?>
            </div>

            <div class="col-12 mt-2"><h6 class="text-muted text-uppercase small fw-bold mb-0">Professional &amp; Business Profile</h6><hr class="mt-1 mb-2"></div>
            <div class="col-12 col-md-6">
                <strong>Employment Status:</strong>
                <?= escape($u['employment_status'] ?? '-') ?>
            </div>
            <div class="col-12 col-md-6">
                <strong>Organization / Business:</strong>
                <?= escape($u['company_name'] ?? '-') ?>
            </div>
            <div class="col-12 col-md-6">
                <strong>Industry / Profession:</strong>
                <?= escape($u['industry_profession'] ?? '-') ?>
            </div>

            <div class="col-12 mt-2"><h6 class="text-muted text-uppercase small fw-bold mb-0">Ministry Partnership &amp; Support</h6><hr class="mt-1 mb-2"></div>
            <div class="col-12 col-md-6">
                <strong>Partnership Focus:</strong>
                <?= escape($u['partnership_focus'] ?? '-') ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content    = ob_get_clean();
$page_title = "View Member - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
