<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'member';

try {
    $announcements = $pdo->query(
        'SELECT id, title, content, COALESCE(date_created, date_posted) AS date_created
         FROM announcements
         ORDER BY COALESCE(date_created, date_posted) DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Newsroom query error: " . $e->getMessage());
    $announcements = [];
}

ob_start();
?>

<style>
    .newsroom-header {
        background: linear-gradient(135deg, #219a43 0%, #1a7a35 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(33,154,67,0.2);
    }
    .accordion-button:not(.collapsed) {
        background-color: #e9f5ec;
        color: #155724;
    }
    .accordion-item {
        border: none;
        margin-bottom: 0.5rem;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    .accordion-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-1px);
    }
    .accordion-button {
        font-weight: 500;
        border-radius: 8px !important;
        transition: all 0.3s ease;
    }
    .date-badge {
        font-size: 0.75rem;
        background: rgba(33,154,67,0.1);
        color: #219a43;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 600;
    }
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #6c757d;
    }
    .empty-state i { font-size: 4rem; opacity: 0.3; }
</style>

<div class="newsroom-header d-flex align-items-center gap-3">
    <i class="bi bi-megaphone-fill fs-2"></i>
    <div>
        <h1 class="h3 mb-1 fw-bold">Newsroom</h1>
        <p class="mb-0 opacity-75">Latest updates, announcements and news from JDM Kenya</p>
    </div>
</div>

<?php if (empty($announcements)): ?>
    <div class="card shadow-sm">
        <div class="card-body empty-state">
            <i class="bi bi-newspaper"></i>
            <p class="mt-3 mb-0 fs-5">No announcements yet.</p>
            <p class="text-muted small">Check back later for updates from leadership.</p>
        </div>
    </div>
<?php else: ?>
    <div class="accordion" id="newsRoomAccordion">
        <?php foreach ($announcements as $i => $a): ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#newsItem<?= (int)$a['id'] ?>"
                            aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"
                            aria-controls="newsItem<?= (int)$a['id'] ?>">
                        <span class="flex-grow-1"><?= escape($a['title']) ?></span>
                        <span class="date-badge me-3"><?= date('M d, Y', strtotime($a['date_created'])) ?></span>
                    </button>
                </h2>
                <div id="newsItem<?= (int)$a['id'] ?>"
                     class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                     data-bs-parent="#newsRoomAccordion">
                    <div class="accordion-body text-secondary" style="line-height: 1.7;">
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
