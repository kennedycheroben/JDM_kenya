<?php
// Compatibility page: "Announcements" / "Newsroom"
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$announcements = $pdo->query('SELECT id, title, content, COALESCE(date_created, date_posted) AS date_created FROM announcements ORDER BY COALESCE(date_created, date_posted) DESC')->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-megaphone"></i> Newsroom</h2>
        <p class="text-muted mb-0">This is the Newsroom section where you can find the latest updates and news.</p>
    </div>
</div>

<?php if (!$announcements): ?>
    <div class="alert alert-info">No announcements yet.</div>
<?php else: ?>
    <div class="accordion" id="burningIssues">
        <?php foreach ($announcements as $i => $a): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                            data-bs-toggle="collapse" data-bs-target="#ann<?= (int)$a['id'] ?>">
                        <?= escape($a['title']) ?>
                        <span class="ms-2 text-muted small">(<?= date('M d, Y', strtotime($a['date_created'])) ?>)</span>
                    </button>
                </h2>
                <div id="ann<?= (int)$a['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#burningIssues">
                    <div class="accordion-body">
                        <?= nl2br(escape($a['content'])) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = "Newsroom - JDM Kenya";
include(__DIR__ . '/layout.php');
?>

