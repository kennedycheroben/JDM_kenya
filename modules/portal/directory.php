<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'member' && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin')) {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$is_super_admin = ($_SESSION['user_role'] === 'super_admin');
$can_view_contact = in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin']);
$user_role = $_SESSION['user_role'] ?? 'member';
$viewerId = (int)($_SESSION['user_id'] ?? 0);

$sort = ($_GET['sort'] ?? 'newest') === 'name' ? 'name' : 'newest';
$orderClause = ($sort === 'newest') ? 'created_at DESC, id DESC' : 'name ASC, id DESC';

try {
    if ($user_role === 'super_admin') {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') AND (account_status IS NULL OR account_status != 'deleted') ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role === 'admin') {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') AND category != 'partner' AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status != 'deleted') ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') AND (category NOT IN ('partner', 'missionary') OR id = {$viewerId}) AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status = 'active') ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('directory query fallback: ' . $e->getMessage());
    if ($user_role === 'super_admin') {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role === 'admin') {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') AND category != 'partner' ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path, created_at FROM users WHERE role IN ('member','admin','super_admin') AND (category NOT IN ('partner', 'missionary') OR id = {$viewerId}) ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
    }
}

ob_start();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-7">
        <h2><i class="bi bi-people"></i> Members Directory</h2>
        <p class="text-muted mb-0">Connect with our community members</p>
    </div>
    <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
        <div class="btn-group btn-group-sm" role="group" aria-label="Sort directory">
            <a href="?sort=name" class="btn <?= $sort === 'name' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-sort-alpha-down"></i> Name (A–Z)
            </a>
            <a href="?sort=newest" class="btn <?= $sort === 'newest' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-clock-history"></i> Newest First
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if (empty($members)): ?>
        <div class="col-12">
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-people fs-3 d-block mb-2"></i>
                No directory members found.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($members as $m): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <?php if (!empty($m['pfp_path'])): ?>
                            <?php
                            $pfpSrc = $m['pfp_path'];
                            if (!str_starts_with($pfpSrc, 'http://') && !str_starts_with($pfpSrc, 'https://') && !str_starts_with($pfpSrc, BASE_PATH . '/')) {
                                $pfpSrc = BASE_PATH . '/' . ltrim($pfpSrc, '/');
                            }
                            ?>
                            <img src="<?= escape($pfpSrc) ?>" alt="Profile picture" class="rounded-circle" style="width:56px;height:56px;object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                <i class="bi bi-person text-secondary" style="font-size:1.5rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold"><?= escape($m['name']) ?></span>
                                <span class="badge bg-light text-secondary border small">#<?= (int)$m['id'] ?></span>
                            </div>
                            <?php if ($can_view_contact): ?>
                            <div class="small">
                                <a href="<?= escape(waLink($m['whatsapp_phone'] ?? '')) ?>" target="_blank" rel="noopener">
                                    <i class="bi bi-whatsapp"></i> <?= escape($m['whatsapp_phone'] ?? '') ?>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="small text-muted">
                                <i class="bi bi-whatsapp"></i> <?= escape(maskPhone($m['whatsapp_phone'] ?? '')) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($sort === 'newest' && !empty($m['created_at'])): ?>
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-calendar3"></i> Joined <?= date('M d, Y', strtotime($m['created_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-chat-dots text-muted"></i>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a class="btn btn-sm btn-outline-primary w-100" href="<?= BASE_PATH ?>/chat.php?with=<?= (int)$m['id'] ?>">
                            <i class="bi bi-chat"></i> Message
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$page_title = "Directory - JDM Kenya";
include(__DIR__ . '/layout.php');
?>

