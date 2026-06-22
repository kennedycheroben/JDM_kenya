<?php
/**
 * GBS Dashboard - JDM Kenya
 *
 * Displays the GBS groups available to the logged-in user.
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/gbs_groups.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$_SESSION['active_dashboard'] = 'gbs';

$message = '';
$error = '';
$my_leaderships = [];
$my_memberships = [];
$all_groups = [];
$all_users = [];

try {
    $stmt = $pdo->prepare("
        SELECT g.*, gm.role AS member_role,
               (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
        FROM gbs_groups g
        JOIN gbs_members gm ON g.id = gm.gbs_id
        WHERE gm.user_id = ? AND gm.role = 'leader'
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $my_leaderships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT g.*, gm.role AS member_role,
               (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
        FROM gbs_groups g
        JOIN gbs_members gm ON g.id = gm.gbs_id
        WHERE gm.user_id = ? AND gm.role <> 'leader'
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $my_memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($user_role === 'super_admin') {
        $stmt = $pdo->query("
            SELECT g.*, u.name AS leader_name,
                   (SELECT COUNT(*) FROM gbs_members WHERE gbs_id = g.id) AS member_count
            FROM gbs_groups g
            LEFT JOIN users u ON u.id = g.leader_id
            ORDER BY g.created_at DESC
        ");
        $all_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Unable to load GBS groups: ' . $e->getMessage();
}

// Check if user is a GBS Leader from DB to ensure immediate access after appointment
$is_gbs_leader_db = false;
try {
    $stmt = $pdo->prepare("SELECT is_gbs_leader FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $is_gbs_leader_db = (bool)$stmt->fetchColumn();
} catch (PDOException $e) { /* fallback to session if db fails */ }

$can_create_group = in_array($user_role, ['admin', 'super_admin'], true) || $is_gbs_leader_db;

if (isset($_GET['message'])) {
    $message = trim($_GET['message']);
}

if ((($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') && ($_POST['action'] ?? '') === 'delete_gbs_group') {
    require_csrf();
    $delete_gbs_id = (int)($_POST['gbs_id'] ?? 0);
    $can_delete = false;

    if ($delete_gbs_id > 0) {
        if ($user_role === 'super_admin') {
            $can_delete = true;
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM gbs_groups WHERE id = ? AND leader_id = ?');
            $stmt->execute([$delete_gbs_id, $user_id]);
            $can_delete = ((int)$stmt->fetchColumn() > 0);
        }
    }

    if (!$can_delete) {
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
}

if ($can_create_group && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') && ($_POST['action'] ?? 'create_gbs_group') !== 'delete_gbs_group') {
    require_csrf();
    $group_name = trim($_POST['group_name'] ?? '');
    $group_slogan = trim($_POST['group_slogan'] ?? '');
    $leader_id = (int)($_POST['leader_id'] ?? 0);

    if ($group_name === '') {
        $error = 'Group name is required';
    } elseif ($leader_id <= 0) {
        $error = 'Please select a group leader';
    } else {
        try {
            $pdo->beginTransaction();

            $pfp_path = null;
            if (!empty($_FILES['profile_picture']['name']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed, true)) {
                    throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
                }

                $destination_dir = UPLOAD_DIR . 'gbs_pfps/';
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

            $stmt = $pdo->prepare("
                INSERT INTO gbs_groups (name, slogan, leader_id, pfp_path, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$group_name, $group_slogan !== '' ? $group_slogan : null, $leader_id, $pfp_path]);
            $gbs_id = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO gbs_members (gbs_id, user_id, role, joined_at)
                VALUES (?, ?, 'leader', NOW())
            ");
            $stmt->execute([$gbs_id, $leader_id]);

            $stmt = $pdo->prepare('UPDATE users SET is_gbs_leader = 1 WHERE id = ?');
            $stmt->execute([$leader_id]);

            if ($leader_id !== $user_id) {
                $expiry = time() + (7 * 24 * 60 * 60);
                $notification = "🎉 Congratulations! You have been appointed as the GBS Leader for \"{$group_name}\".\n\n"
                    . "Switch to your GBS Leader dashboard: gbs_dashboard.php?set_dashboard=gbs&exp=" . $expiry . "\n"
                    . "Open your GBS group: gbs_group.php?id={$gbs_id}";

                $stmt = $pdo->prepare("
                    INSERT INTO messages (sender_id, receiver_id, message_text, status)
                    VALUES (?, ?, ?, 'sent')
                ");
                $stmt->execute([$user_id, $leader_id, $notification]);
            }

            $pdo->commit();
            header('Location: gbs_group.php?id=' . $gbs_id);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to create GBS group: ' . $e->getMessage();
        }
    }
}

if ($can_create_group) {
    try {
        $stmt = $pdo->query("SELECT id, name, role FROM users ORDER BY name ASC");
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = $error ?: 'Unable to load users: ' . $e->getMessage();
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start">
        <div>
            <h2><i class="bi bi-people-fill"></i> GBS Dashboard</h2>
            <p class="text-muted mb-0">View and manage Group Bible Study groups.</p>
        </div>
        <a href="member_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Member Dashboard
        </a>
    </div>
</div>

<?php 
$is_link_valid = !isset($_GET['exp']) || (is_numeric($_GET['exp']) && (int)$_GET['exp'] >= time());
if (isset($_GET['new_leader']) && $is_link_valid): 
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
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= escape($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= escape($error) ?></div>
<?php endif; ?>

<?php if ($can_create_group): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create GBS Group</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_gbs_group">
                <div class="col-md-4">
                    <label class="form-label">Group Name</label>
                    <input type="text" name="group_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Group Slogan</label>
                    <input type="text" name="group_slogan" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Leader</label>
                    <select name="leader_id" class="form-select" required>
                        <?php if (!in_array($user_role, ['admin', 'super_admin'], true)): ?>
                            <option value="<?= $user_id ?>" selected><?= escape($_SESSION['user_name'] ?? 'Me') ?> (You)</option>
                        <?php else: ?>
                            <option value="">Select leader</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>" <?= (int)$user['id'] === $user_id ? 'selected' : '' ?>>
                                    <?= escape($user['name']) ?> (<?= escape($user['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Group Image</label>
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
            <h5 class="mb-0"><i class="bi bi-shield-lock"></i> All GBS Groups</h5>
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
                                    <td><?= (int)$group['member_count'] ?></td>
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
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = 'GBS Dashboard';
include(__DIR__ . '/../portal/layout.php');
