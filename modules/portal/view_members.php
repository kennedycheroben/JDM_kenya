<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Check admin access
if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin')) {
    header('Location: login.php');
    exit;
}

$page_title = "Members Management - JDM Kenya";
$success_msg = '';
$error_msg = '';
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Get all members (admins can manage members only; super admins can view all)
$is_super_admin = ($_SESSION['user_role'] === 'super_admin');
$query = 'SELECT id, name, email, whatsapp_phone, category, role, created_at FROM users WHERE 1=1';
$params = [];

if (!$is_super_admin) {
    $query .= " AND role = 'member'";
}

if ($selected_category !== 'all') {
    $query .= ' AND category = ?';
    $params[] = $selected_category;
}

$query .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category statistics (members only)
$stats = [
    'students' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'member' AND category = 'student'")->fetch(PDO::FETCH_ASSOC)['count'],
    'associates' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'member' AND category = 'associate'")->fetch(PDO::FETCH_ASSOC)['count'],
    'partners' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'member' AND category = 'partner'")->fetch(PDO::FETCH_ASSOC)['count'],
    'total' => $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'")->fetch(PDO::FETCH_ASSOC)['count'],
];

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
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-handshake text-warning"></i>
            <h5>Partners</h5>
            <h3 class="text-warning"><?= $stats['partners'] ?></h3>
            <p>Partner members</p>
        </div>
    </div>
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
            <a href="view_members.php?category=partner" class="category-btn <?= $selected_category === 'partner' ? 'active' : '' ?>">
                <i class="bi bi-handshake"></i> Partners
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
                        echo "All Members (" . count($members) . ")";
                    } else {
                        echo ucfirst($selected_category) . " Members (" . count($members) . ")";
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
                                    <th>ID</th>
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
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><strong>#<?= $member['id'] ?></strong></td>
                                        <td><?= escape($member['name']) ?></td>
                                        <td><?= escape($member['email']) ?></td>
                                        <td><?= escape($member['whatsapp_phone']) ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = match($member['category']) {
                                                'student' => 'bg-info',
                                                'associate' => 'bg-success',
                                                'partner' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badge_class ?>">
                                                <?php 
                                                echo match($member['category']) {
                                                    'student' => '<i class="bi bi-book"></i> Student',
                                                    'associate' => '<i class="bi bi-briefcase"></i> Associate',
                                                    'partner' => '<i class="bi bi-handshake"></i> Partner',
                                                    default => htmlspecialchars($member['category'])
                                                };
                                                ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($member['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $r = $member['role'] ?? 'member';
                                            $rb = match($r) {
                                                'super_admin' => 'bg-dark',
                                                'admin' => 'bg-danger',
                                                default => 'bg-primary'
                                            };
                                            ?>
                                            <span class="badge <?= $rb ?>"><?= escape($r) ?></span>
                                        </td>
                                        <td>
                                            <a href="view_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="edit_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="delete_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
