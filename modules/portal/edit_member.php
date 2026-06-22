<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$viewerRole = $_SESSION['user_role'];
$viewerId = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: view_members.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, email, whatsapp_phone, role, category FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    header('Location: view_members.php');
    exit;
}

// Permission checks
$isOwnProfile = ($viewerId === $id);
$canEdit = false;

if ($viewerRole === 'super_admin') {
    // JDM Leader / super admin cannot edit any profile
    header('Location: view_members.php');
    exit;
} elseif ($isOwnProfile) {
    // Users can edit their own profile
    $canEdit = true;
} elseif ($viewerRole === 'admin') {
    // Admins can edit members but not other admins, super_admins, or office bearers (partners)
    if ($u['role'] === 'super_admin' || $u['role'] === 'admin' || $u['category'] === 'partner') {
        header('Location: view_members.php');
        exit;
    }
    $canEdit = true;
} else {
    // Regular users cannot edit other profiles
    header('Location: view_members.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp_phone'] ?? '');
    $category = strtolower(trim($_POST['category'] ?? ''));

    $allowedCategories = ['student','associate','partner'];
    if ($name === '' || $email === '' || $whatsapp === '' || !in_array($category, $allowedCategories, true)) {
        $error = 'Please fill all fields correctly.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Prevent email collision
        $stmt2 = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt2->execute([$email, $id]);
        if ($stmt2->fetch()) {
            $error = 'That email is already used by another user.';
        } else {
            $stmt3 = $pdo->prepare('UPDATE users SET name = ?, email = ?, whatsapp_phone = ?, category = ? WHERE id = ?');
            $stmt3->execute([$name, $email, $whatsapp, $category, $id]);
            $success = 'Member updated successfully.';

            $stmt = $pdo->prepare('SELECT id, name, email, whatsapp_phone, role, category FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-pencil"></i> Edit Member</h2>
            <p class="text-muted mb-0">Admins can edit members. Only JDM Leader can manage admins.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="view_member.php?id=<?= (int)$u['id'] ?>" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= escape($error) ?></div>
<?php elseif ($success): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= escape($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Member Details</h5>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="name" value="<?= escape($u['name']) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= escape($u['email']) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">WhatsApp Phone</label>
                <input class="form-control" name="whatsapp_phone" value="<?= escape($u['whatsapp_phone']) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Category</label>
                <select class="form-select" name="category" required>
                    <option value="student" <?= $u['category']==='student'?'selected':'' ?>>Student</option>
                    <option value="associate" <?= $u['category']==='associate'?'selected':'' ?>>Associate</option>
                    <option value="partner" <?= $u['category']==='partner'?'selected':'' ?>>Office Bearer</option>
                </select>
            </div>
            <div class="col-12">
                <div class="d-grid d-sm-flex gap-2">
                    <button class="btn btn-success" type="submit"><i class="bi bi-check-circle"></i> Save</button>
                    <a class="btn btn-outline-secondary" href="view_member.php?id=<?= (int)$u['id'] ?>">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "Edit Member - JDM Kenya";
include(__DIR__ . '/layout.php');
?>
