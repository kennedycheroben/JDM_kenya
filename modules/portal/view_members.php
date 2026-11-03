<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// =====================================================================
// ACCESS CONTROL: Allow admins, super_admins, and approved office bearers
// =====================================================================
$viewerRole = $_SESSION['user_role'] ?? '';
$viewerId = (int)($_SESSION['user_id'] ?? 0);

// Check if user is admin or super_admin
$isAdmin = ($viewerRole === 'admin' || $viewerRole === 'super_admin');

// Check if user is an approved office bearer (partner)
$isOfficeBearer = false;
if (!$isAdmin && $viewerId > 0) {
    $stmtViewer = $pdo->prepare('SELECT category, is_approved FROM users WHERE id = ? AND category = "partner" LIMIT 1');
    $stmtViewer->execute([$viewerId]);
    $viewerData = $stmtViewer->fetch(PDO::FETCH_ASSOC);
    $isOfficeBearer = ($viewerData && $viewerData['is_approved']);
}

// Only admins, super_admins, and approved office bearers can view members
if (!$isAdmin && !$isOfficeBearer) {
    header('Location: login.php');
    exit;
}

$page_title = "Members Management - JDM Kenya";
$success_msg = '';
$error_msg = '';

// Flash messages from actions
if (!empty($_GET['deleted'])) {
    $success_msg = 'Member permanently deleted.';
} elseif (!empty($_GET['deactivated'])) {
    $success_msg = 'Member account deactivated successfully. Login is blocked and history is preserved.';
} elseif (!empty($_GET['activated'])) {
    $success_msg = 'Member account activated successfully.';
} elseif (!empty($_GET['error'])) {
    $errorCode = $_GET['error'];
    if ($errorCode === 'cannot_delete_self') {
        $error_msg = 'You cannot delete your own account.';
    } elseif ($errorCode === 'protected') {
        $error_msg = 'That account is protected and cannot be deleted.';
    } else {
        $error_msg = 'An error occurred while processing the request.';
    }
}
$selected_category = isset($_GET['category']) ? strtolower(trim((string)$_GET['category'])) : 'all';

// Get all members (admins can manage members only; JDM Leaders can view all)
$is_super_admin = ($_SESSION['user_role'] === 'super_admin');

$allowed_categories = ['all', 'student', 'associate', 'partner', 'missionary', 'sports_ministry', 'other'];
if (!in_array($selected_category, $allowed_categories, true)) {
    $selected_category = 'all';
}

if ($selected_category === 'partner' && !$is_super_admin) {
    header('Location: view_members.php');
    exit;
}

if ($selected_category === 'missionary' && !$isAdmin) {
    header('Location: view_members.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Determine visibility filter based on viewer role while including all member roles (member, admin, super_admin)
if ($is_super_admin) {
    $visibilityWhere = "role IN ('member','admin','super_admin') AND (account_status IS NULL OR account_status != 'deleted')";
    $visibilityFallbackWhere = "role IN ('member','admin','super_admin')";
} elseif ($isAdmin) {
    $visibilityWhere = "role IN ('member','admin','super_admin') AND category != 'partner' AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status != 'deleted')";
    $visibilityFallbackWhere = "role IN ('member','admin','super_admin') AND category != 'partner'";
} else {
    $safeViewerId = (int)$viewerId;
    $visibilityWhere = "role IN ('member','admin','super_admin') AND (category NOT IN ('partner', 'missionary') OR id = {$safeViewerId}) AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status = 'active')";
    $visibilityFallbackWhere = "role IN ('member','admin','super_admin') AND (category NOT IN ('partner', 'missionary') OR id = {$safeViewerId})";
}

$countQuery = "SELECT COUNT(*) FROM users WHERE {$visibilityWhere}";
$query = "SELECT id, name, email, whatsapp_phone, category, role, account_status, created_at FROM users WHERE {$visibilityWhere}";
$params = [];

if ($selected_category !== 'all') {
    $countQuery .= ' AND category = ?';
    $query .= ' AND category = ?';
    $params[] = $selected_category;
}

$query .= ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

try {
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $totalMembers = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($totalMembers / $perPage));

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('view_members query fallback: ' . $e->getMessage());
    $fallbackCount = "SELECT COUNT(*) FROM users WHERE {$visibilityFallbackWhere}" . ($selected_category !== 'all' ? ' AND category = ?' : '');
    $fallbackQuery = "SELECT id, name, email, whatsapp_phone, category, role, created_at FROM users WHERE {$visibilityFallbackWhere}" . ($selected_category !== 'all' ? ' AND category = ?' : '') . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmtCount = $pdo->prepare($fallbackCount);
    $stmtCount->execute($params);
    $totalMembers = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($totalMembers / $perPage));

    $stmt = $pdo->prepare($fallbackQuery);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as &$mRow) {
        $mRow['account_status'] = $mRow['account_status'] ?? 'active';
    }
    unset($mRow);
}

// Get category statistics across all valid roles (member, admin, super_admin), respecting viewer visibility rules
$stats = ['students' => 0, 'associates' => 0, 'partners' => 0, 'missionaries' => 0, 'total' => 0];
try {
    $stats['students'] = (int)$pdo->query("SELECT COUNT(*) as c FROM users WHERE {$visibilityWhere} AND category='student'")->fetch(PDO::FETCH_ASSOC)['c'];
    $stats['associates'] = (int)$pdo->query("SELECT COUNT(*) as c FROM users WHERE {$visibilityWhere} AND category='associate'")->fetch(PDO::FETCH_ASSOC)['c'];
    $stats['partners'] = $is_super_admin
        ? (int)$pdo->query("SELECT COUNT(*) as c FROM users WHERE {$visibilityWhere} AND category='partner'")->fetch(PDO::FETCH_ASSOC)['c']
        : 0;
    $stats['missionaries'] = $isAdmin
        ? (int)$pdo->query("SELECT COUNT(*) as c FROM users WHERE {$visibilityWhere} AND category='missionary'")->fetch(PDO::FETCH_ASSOC)['c']
        : 0;
    $stats['total'] = (int)$pdo->query("SELECT COUNT(*) as c FROM users WHERE {$visibilityWhere}")->fetch(PDO::FETCH_ASSOC)['c'];
} catch (Throwable $e) {
    error_log("Stats query error: " . $e->getMessage());
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="bi bi-people"></i> Members Management</h2>
        <p class="text-muted">View and manage all members by category</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-people-fill text-primary"></i>
            <h5>Total Members</h5>
            <h3 class="text-primary"><?= $stats['total'] ?></h3>
            <p>All member categories</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-book text-info"></i>
            <h5>Students</h5>
            <h3 class="text-info"><?= $stats['students'] ?></h3>
            <p>Active students</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-briefcase text-success"></i>
            <h5>Associates</h5>
            <h3 class="text-success"><?= $stats['associates'] ?></h3>
            <p>Associate members</p>
        </div>
    </div>
    <?php if ($is_super_admin): ?>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-briefcase-fill text-warning"></i>
            <h5>Office Bearers</h5>
            <h3 class="text-warning"><?= $stats['partners'] ?></h3>
            <p>Office Bearer members</p>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-globe text-info"></i>
            <h5>Missionaries</h5>
            <h3 class="text-info"><?= $stats['missionaries'] ?></h3>
            <p>Missionary members</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Category Filter -->
<div class="row mb-4">
    <div class="col-md-12">
        <h5>Filter by Category:</h5>
        <div class="category-filter">
            <a href="view_members.php?category=all" class="category-btn <?= $selected_category === 'all' ? 'active' : '' ?>">
                <i class="bi bi-funnel"></i> All Members
            </a>
            <a href="view_members.php?category=student" class="category-btn <?= $selected_category === 'student' ? 'active' : '' ?>">
                <i class="bi bi-book"></i> Students
            </a>
            <a href="view_members.php?category=associate" class="category-btn <?= $selected_category === 'associate' ? 'active' : '' ?>">
                <i class="bi bi-briefcase"></i> Associates
            </a>
            <?php if ($is_super_admin): ?>
            <a href="view_members.php?category=partner" class="category-btn <?= $selected_category === 'partner' ? 'active' : '' ?>">
                <i class="bi bi-briefcase-fill"></i> Office Bearers
            </a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="view_members.php?category=missionary" class="category-btn <?= $selected_category === 'missionary' ? 'active' : '' ?>">
                <i class="bi bi-globe"></i> Missionaries
            </a>
            <?php endif; ?>
            <a href="view_members.php?category=sports_ministry" class="category-btn <?= $selected_category === 'sports_ministry' ? 'active' : '' ?>">
                <i class="bi bi-trophy"></i> Sports Ministry
            </a>
            <a href="view_members.php?category=other" class="category-btn <?= $selected_category === 'other' ? 'active' : '' ?>">
                <i class="bi bi-person"></i> Others
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= escape($success_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?= escape($error_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Members Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-table"></i>
                    <?php 
                    if ($selected_category === 'all') {
                        echo "All Members (" . (int)$totalMembers . ")";
                    } else {
                        $catHeaderLabel = str_replace('_', ' ', ucfirst($selected_category));
                        echo escape($catHeaderLabel) . " Members (" . (int)$totalMembers . ")";
                    }
                    ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($members) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">No.</th>
                                    <th style="width: 105px;">Member ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>WhatsApp Phone</th>
                                    <th>Category</th>
                                    <th>Joined</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $rowIndex => $member): ?>
                                    <tr>
                                        <td><strong><?= (int)($offset + $rowIndex + 1) ?></strong></td>
                                        <td><span class="badge bg-light text-dark border">#<?= (int)$member['id'] ?></span></td>
                                        <td><?= escape($member['name']) ?></td>
                                        <td><?= $isAdmin ? escape($member['email']) : escape(maskEmail($member['email'] ?? '')) ?></td>
                                        <td><?= $isAdmin ? escape($member['whatsapp_phone']) : escape(maskPhone($member['whatsapp_phone'] ?? '')) ?></td>
                                        <td>
                                            <?php if ($member['category'] === 'missionary'): ?>
                                                <span class="badge text-white" style="background: linear-gradient(135deg, #6366f1, #a855f7) !important; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 2px 4px rgba(168,85,247,0.25);">
                                                    <i class="bi bi-globe2"></i> Missionary
                                                </span>
                                            <?php else: ?>
                                                <?php 
                                                $badgeDisplay = ['class' => 'bg-secondary', 'icon' => 'Member'];
                                                switch($member['category']) {
                                                    case 'student':
                                                        $badgeDisplay = ['class' => 'bg-info', 'icon' => '<i class="bi bi-book"></i> Student'];
                                                        break;
                                                    case 'associate':
                                                        $badgeDisplay = ['class' => 'bg-success', 'icon' => '<i class="bi bi-briefcase"></i> Associate'];
                                                        break;
                                                    case 'partner':
                                                        $badgeDisplay = ['class' => 'bg-warning text-dark', 'icon' => '<i class="bi bi-briefcase-fill"></i> Office Bearer'];
                                                        break;
                                                    case 'sports_ministry':
                                                        $badgeDisplay = ['class' => 'bg-primary', 'icon' => '<i class="bi bi-trophy"></i> Sports Ministry'];
                                                        break;
                                                    case 'other':
                                                        $badgeDisplay = ['class' => 'bg-secondary', 'icon' => 'Member'];
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $badgeDisplay['class'] ?>">
                                                    <?= $badgeDisplay['icon'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($member['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $r = $member['role'] ?? 'member';
                                            $rb = 'bg-primary';
                                            switch($r) {
                                                case 'super_admin':
                                                    $rb = 'bg-dark';
                                                    break;
                                                case 'admin':
                                                    $rb = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $rb ?>"><?= escape($r) ?></span>
                                            <?php if (($member['account_status'] ?? 'active') === 'disabled'): ?>
                                                <span class="badge bg-secondary ms-1">Deactivated</span>
                                            <?php elseif (($member['account_status'] ?? 'active') === 'suspended'): ?>
                                                <span class="badge bg-warning text-dark ms-1">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                                <a href="view_member.php?id=<?= (int)$member['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <?php if ($is_super_admin): ?>
                                                    <a href="delete_member.php?id=<?= (int)$member['id'] ?>" class="btn btn-sm btn-outline-danger" title="Manage account lifecycle">
                                                        <i class="bi bi-person-gear"></i> Manage
                                                    </a>
                                                <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?category=<?= urlencode($selected_category) ?>&page=<?= $page - 1 ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?category=<?= urlencode($selected_category) ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="?category=<?= urlencode($selected_category) ?>&page=<?= $page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> No members found in this category.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Get buffered content
$content = ob_get_clean();
$page_title = "Members Management - JDM Kenya";
include(__DIR__ . "/layout.php");
?>
