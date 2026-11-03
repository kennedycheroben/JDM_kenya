<?php
/**
 * GBS Dashboard - JDM Kenya
 *
 * Displays the GBS groups available to the logged-in user and allows
 * authorized GBS Leaders (and Super Admin) to create and manage GBS groups.
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/gbs_groups.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';

// Server-side verification of GBS Leader status
$is_gbs_leader_db = isUserGbsLeader($pdo, $user_id);
$can_create_group = canUserCreateGbsGroup($pdo, $user_id, $user_role);

if ($is_gbs_leader_db || $user_role === 'super_admin') {
    $_SESSION['active_dashboard'] = 'gbs';
}

$message = '';
$error = '';
$my_leaderships = [];
$my_memberships = [];
$all_groups = [];
$appointed_gbs_leaders = [];
$all_users = [];

if (isset($_GET['message']) && trim((string)$_GET['message']) !== '') {
    $message = trim((string)$_GET['message']);
}
if (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
    $error = trim((string)$_GET['error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) {
        $error = $_SESSION['csrf_error'] ?? 'Session expired. Please reload the page and try again.';
        unset($_SESSION['csrf_error']);
    } else {
        $post_action = $_POST['action'] ?? 'create_gbs_group';

        if ($post_action === 'delete_gbs_group') {
            $delete_gbs_id = (int)($_POST['gbs_id'] ?? 0);
            $membership = getGbsGroupMembership($pdo, $delete_gbs_id, $user_id, $user_role);

            if (!$membership || empty($membership['can_delete'])) {
                $error = 'You can only delete GBS groups you lead.';
            } else {
                try {
                    deleteGbsGroup($pdo, $delete_gbs_id);
                    header('Location: gbs_dashboard.php?message=' . urlencode('GBS group deleted successfully.'));
                    exit;
                } catch (Throwable $e) {
                    $error = 'Failed to delete GBS group: ' . $e->getMessage();
                }
            }
        } elseif ($post_action === 'create_gbs_group') {
            if (!$can_create_group) {
                $error = 'You are not authorized to create a GBS group.';
            } else {
                $group_name = trim($_POST['group_name'] ?? '');
                $group_slogan = trim($_POST['group_slogan'] ?? '');
                $requested_leader_id = isset($_POST['leader_id']) && $_POST['leader_id'] !== ''
                    ? (int)$_POST['leader_id']
                    : null;

                if ($group_name === '') {
                    $error = 'Group name is required.';
                } elseif ($user_role === 'super_admin' && $requested_leader_id !== null && $requested_leader_id <= 0) {
                    $error = 'Please select a group leader.';
                } elseif ($user_role !== 'super_admin' && $requested_leader_id !== null && $requested_leader_id !== $user_id) {
                    $error = 'You can only create a GBS group with yourself as the leader.';
                } else {
                    try {
                        $pfp_path = null;
                        if (!empty($_FILES['profile_picture']['name']) && ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                                throw new RuntimeException('Failed to upload group image.');
                            }
                            if (($_FILES['profile_picture']['size'] ?? 0) > 4 * 1024 * 1024) {
                                throw new RuntimeException('Group image must be 4MB or smaller.');
                            }
                            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                            if (!in_array($ext, $allowed, true)) {
                                throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
                            }

                            $uploadRoot = defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(__DIR__, 2) . '/uploads/');
                            $destination_dir = rtrim($uploadRoot, '/') . '/gbs_pfps/';
                            if (!is_dir($destination_dir)) {
                                mkdir($destination_dir, 0775, true);
                            }

                            $file_name = 'gbs_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                            $destination = $destination_dir . $file_name;
                            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                                throw new RuntimeException('Failed to upload group image.');
                            }
                            $pfp_path = 'uploads/gbs_pfps/' . $file_name;
                        }

                        $gbs_id = createGbsGroup(
                            $pdo,
                            $user_id,
                            $user_role,
                            $group_name,
                            $group_slogan !== '' ? $group_slogan : null,
                            $requested_leader_id,
                            $pfp_path
                        );

                        header('Location: gbs_group.php?id=' . $gbs_id . '&message=' . urlencode('GBS group created successfully!'));
                        exit;
                    } catch (Throwable $e) {
                        $error = 'Failed to create GBS group: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

try {
    if ($is_gbs_leader_db || $user_role === 'super_admin') {
        $stmt = $pdo->prepare("
            SELECT g.*, 'leader' AS member_role,
                   (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
            FROM gbs_groups g
            LEFT JOIN gbs_members gm ON g.id = gm.gbs_id AND gm.user_id = ?
            WHERE g.leader_id = ? OR (gm.user_id = ? AND gm.role = 'leader')
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $my_leaderships = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT g.*, gm.role AS member_role,
                   (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
            FROM gbs_groups g
            JOIN gbs_members gm ON g.id = gm.gbs_id
            WHERE gm.user_id = ? AND gm.role <> 'leader' AND g.leader_id <> ?
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id]);
        $my_memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Non-leader (or revoked leader): all groups they belong to are regular memberships
        $stmt = $pdo->prepare("
            SELECT g.*, 'member' AS member_role,
                   (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
            FROM gbs_groups g
            JOIN gbs_members gm ON g.id = gm.gbs_id
            WHERE gm.user_id = ?
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $my_memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($user_role === 'super_admin') {
        $stmt = $pdo->query("
            SELECT g.*, u.name AS leader_name,
                   (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
            FROM gbs_groups g
            LEFT JOIN users u ON u.id = g.leader_id
            ORDER BY g.created_at DESC
        ");
        $all_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $appointed_gbs_leaders = getAppointedGbsLeadersWithGroups($pdo);
    }
} catch (PDOException $e) {
    $error = $error ?: ('Unable to load GBS groups: ' . $e->getMessage());
}

if ($can_create_group && $user_role === 'super_admin') {
    try {
        $stmt = $pdo->query("
            SELECT id, name, role
            FROM users
            WHERE role IN ('member', 'admin', 'super_admin')
              AND (account_status IS NULL OR account_status = 'active')
              AND (category NOT IN ('partner', 'missionary') OR is_approved = 1)
            ORDER BY name ASC
        ");
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("SELECT id, name, role FROM users ORDER BY name ASC");
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-people-fill"></i> GBS Dashboard</h2>
            <p class="text-muted mb-0">View and manage Campus Group Bible Study (GBS) groups.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($can_create_group): ?>
                <a href="create_gbs.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Dedicated Setup Wizard
                </a>
            <?php endif; ?>
            <a href="member_dashboard.php?set_dashboard=member" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Member Dashboard
            </a>
        </div>
    </div>
</div>

<?php 
$is_link_valid = !isset($_GET['exp']) || (is_numeric($_GET['exp']) && (int)$_GET['exp'] >= time());
if (isset($_GET['new_leader']) && $is_link_valid && $can_create_group): 
?>
    <div class="alert alert-success border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%);">
        <div class="d-flex align-items-center">
            <div class="display-6 me-3">🎉</div>
            <div>
                <h4 class="alert-heading fw-bold mb-1">Congratulations!</h4>
                <p class="mb-0 text-success fw-medium">You have been appointed as a GBS Leader. You can now create your own GBS group and start your ministry.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= escape($message) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle"></i> <?= escape($error) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($can_create_group): ?>
    <div class="card mb-4" id="create-gbs-group">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create GBS Group</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_gbs_group">
                <div class="col-md-4">
                    <label class="form-label">Group Name <span class="text-danger">*</span></label>
                    <input type="text" name="group_name" class="form-control" placeholder="Enter group name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Group Slogan <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="group_slogan" class="form-control" placeholder="Short mission or slogan">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Group Leader</label>
                    <?php if ($user_role !== 'super_admin'): ?>
                        <input type="hidden" name="leader_id" value="<?= (int)$user_id ?>">
                        <input type="text" class="form-control bg-light" value="<?= escape($_SESSION['user_name'] ?? 'Me') ?> (You)" readonly>
                    <?php else: ?>
                        <select name="leader_id" class="form-select" required>
                            <option value="">Select leader</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>" <?= (int)$user['id'] === $user_id ? 'selected' : '' ?>>
                                    <?= escape($user['name']) ?> (<?= escape($user['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Group Image <span class="text-muted small">(optional)</span></label>
                    <input type="file" name="profile_picture" class="form-control" accept="image/png,image/jpeg,image/webp">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-star-fill"></i> Groups I Lead</h5>
            </div>
            <div class="card-body">
                <?php if (empty($my_leaderships)): ?>
                    <p class="text-muted mb-0">You are not leading any GBS groups yet.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($my_leaderships as $group): ?>
                            <div class="col-12">
                                <div class="border rounded p-3 d-flex gap-3 align-items-center">
                                    <?php if (!empty($group['pfp_path'])): ?>
                                        <img src="<?= escape($group['pfp_path']) ?>" alt="Group profile" class="rounded" style="width:72px;height:72px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                                            <i class="bi bi-people text-muted fs-3"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= escape($group['name']) ?></h6>
                                        <div class="text-muted small"><?= escape($group['slogan'] ?? 'No slogan') ?></div>
                                        <span class="badge bg-light text-dark border mt-2"><?= (int)$group['member_count'] ?> members</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="gbs_group.php?id=<?= (int)$group['id'] ?>" class="btn btn-sm btn-primary">
                                            Open
                                        </a>
                                        <form method="post" onsubmit="return confirm('Delete this GBS group? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_gbs_group">
                                            <input type="hidden" name="gbs_id" value="<?= (int)$group['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-person-check"></i> My Groups</h5>
            </div>
            <div class="card-body">
                <?php if (empty($my_memberships)): ?>
                    <p class="text-muted mb-0">You have not joined any GBS groups yet.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($my_memberships as $group): ?>
                            <div class="col-12">
                                <div class="border rounded p-3 d-flex gap-3 align-items-center">
                                    <?php if (!empty($group['pfp_path'])): ?>
                                        <img src="<?= escape($group['pfp_path']) ?>" alt="Group profile" class="rounded" style="width:72px;height:72px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                                            <i class="bi bi-people text-muted fs-3"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= escape($group['name']) ?></h6>
                                        <div class="text-muted small"><?= escape($group['slogan'] ?? 'No slogan') ?></div>
                                        <span class="badge bg-light text-dark border mt-2"><?= (int)$group['member_count'] ?> members</span>
                                    </div>
                                    <a href="gbs_group.php?id=<?= (int)$group['id'] ?>" class="btn btn-sm btn-primary">
                                        Open
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($user_role === 'super_admin'): ?>
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-shield-lock"></i> All GBS Groups (<?= count($all_groups) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($all_groups)): ?>
                <p class="text-muted mb-0">No GBS groups have been created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Group</th>
                                <th>Leader</th>
                                <th>Members</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_groups as $group): ?>
                                <tr>
                                    <td>
                                        <strong><?= escape($group['name']) ?></strong><br>
                                        <small class="text-muted"><?= escape($group['slogan'] ?? 'No slogan') ?></small>
                                    </td>
                                    <td><?= escape($group['leader_name'] ?? 'Unknown') ?></td>
                                    <td><span class="badge bg-secondary"><?= (int)$group['member_count'] ?> members</span></td>
                                    <td><?= date('M d, Y', strtotime($group['created_at'])) ?></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="gbs_group.php?id=<?= (int)$group['id'] ?>" class="btn btn-sm btn-primary">
                                                Open
                                            </a>
                                            <form method="post" onsubmit="return confirm('Delete this GBS group? This cannot be undone.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_gbs_group">
                                                <input type="hidden" name="gbs_id" value="<?= (int)$group['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-badge"></i> Appointed GBS Leaders (<?= count($appointed_gbs_leaders) ?>)</h5>
            <a href="super_admin_dashboard.php" class="btn btn-sm btn-light">Manage Appointments</a>
        </div>
        <div class="card-body">
            <?php if (empty($appointed_gbs_leaders)): ?>
                <p class="text-muted mb-0">No GBS Leaders are currently appointed.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Leader</th>
                                <th>Email</th>
                                <th>Managed Groups</th>
                                <th class="text-end">Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointed_gbs_leaders as $gl): ?>
                                <tr>
                                    <td><strong><?= escape($gl['name']) ?></strong></td>
                                    <td><?= escape($gl['email']) ?></td>
                                    <td>
                                        <?php if (empty($gl['groups'])): ?>
                                            <span class="text-muted small">No group created yet</span>
                                        <?php else: ?>
                                            <?php foreach ($gl['groups'] as $grp): ?>
                                                <a href="gbs_group.php?id=<?= (int)$grp['id'] ?>" class="badge bg-info text-dark text-decoration-none me-1">
                                                    <?= escape($grp['name']) ?> (<?= (int)$grp['member_count'] ?> members)
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="view_member.php?id=<?= (int)$gl['id'] ?>" class="btn btn-sm btn-outline-primary">View Member</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = 'GBS Dashboard';
include(__DIR__ . '/../portal/layout.php');
