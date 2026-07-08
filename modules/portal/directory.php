<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'member' && $_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'super_admin')) {
    header('Location: login.php');
    exit;
}

$is_super_admin = ($_SESSION['user_role'] === 'super_admin');
$can_view_contact = in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin']);
$user_role = $_SESSION['user_role'] ?? 'member';
if ($user_role === 'super_admin') {
    $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path FROM users WHERE role IN ('member','admin','super_admin') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role === 'admin') {
    $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path FROM users WHERE role IN ('member','admin','super_admin') AND category != 'partner' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $members = $pdo->query("SELECT id, name, whatsapp_phone, pfp_path FROM users WHERE role IN ('member','admin','super_admin') AND category NOT IN ('partner', 'missionary') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-people"></i> Members Directory</h2>
        <p class="text-muted mb-0">Connect with our community members</p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($members as $m): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex gap-3 align-items-center">
                    <?php if (!empty($m['pfp_path'])): ?>
                        <img src="<?= escape($m['pfp_path']) ?>" alt="Profile picture" class="rounded-circle" style="width:56px;height:56px;object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                            <i class="bi bi-person text-secondary" style="font-size:1.5rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= escape($m['name']) ?></div>
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
                    </div>
                    <i class="bi bi-chat-dots text-muted"></i>
                </div>
                <div class="card-footer bg-transparent">
                    <a class="btn btn-sm btn-outline-primary w-100" href="chat.php?with=<?= (int)$m['id'] ?>">
                        <i class="bi bi-chat"></i> Message
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
$page_title = "Directory - JDM Kenya";
include(__DIR__ . '/layout.php');
?>

