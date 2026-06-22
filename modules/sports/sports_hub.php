<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/sports_schema.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}
$userRole = $_SESSION['user_role'] ?? 'member';

try {
    ensure_sports_schema($pdo);
} catch (Throwable $e) {
    error_log('sports_hub schema setup: ' . $e->getMessage());
    $sportsError = 'Sports information is temporarily unavailable. Please try again later.';
}

// Handle Live Match Feed AJAX Requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'commentary') {
    header('Content-Type: application/json');
    if (!empty($sportsError)) {
        echo json_encode(['status' => 'error', 'message' => $sportsError]);
        exit;
    }
    $lastId = (int)($_GET['last_id'] ?? 0);
    $matchId = (int)($_GET['match_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT id, comment_text, DATE_FORMAT(created_at, '%H:%i') as time_str FROM sports_commentary WHERE match_id = ? AND id > ? ORDER BY id ASC LIMIT 20");
    $stmt->execute([$matchId, $lastId]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Fetch Leaderboard (Top 5 Goal Scorers & MVPs)
$leaderboard = [];
$currentMatch = null;

if (empty($sportsError)) {
    try {
        $leaderboardStmt = $pdo->query("
            SELECT u.name, u.pfp_path, s.goals_scored, s.assists, s.mvp_awards 
            FROM sports_stats s 
            JOIN users u ON s.user_id = u.id 
            ORDER BY s.goals_scored DESC, s.mvp_awards DESC, s.assists DESC 
            LIMIT 5
        ");
        $leaderboard = $leaderboardStmt ? $leaderboardStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $sportsError = 'Sports leaderboard could not be loaded.';
        error_log('sports_hub leaderboard query: ' . $e->getMessage());
    }

    try {
        $matchStmt = $pdo->query("SELECT * FROM sports_matches WHERE status = 'Ongoing' ORDER BY id DESC LIMIT 1");
        $currentMatch = $matchStmt ? $matchStmt->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $e) {
        $sportsError = 'Sports match feed could not be loaded.';
        error_log('sports_hub match query: ' . $e->getMessage());
    }
}

// Initial commentary if a match is ongoing
$initialCommentary = [];
if ($currentMatch) {
    try {
        $stmt = $pdo->prepare("SELECT id, comment_text, DATE_FORMAT(created_at, '%H:%i') as time_str FROM sports_commentary WHERE match_id = ? ORDER BY id DESC LIMIT 10");
        $stmt->execute([$currentMatch['id']]);
        $initialCommentary = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $sportsError = 'Sports commentary could not be loaded.';
        error_log('sports_hub commentary query: ' . $e->getMessage());
    }
}

$page_title = "Sports Ministry Dashboard - JDM Kenya";
ob_start();
?>

<!-- Hero Section -->
<div class="row mb-4 position-relative rounded-4 overflow-hidden shadow-sm" style="height: 250px; background: #000;">
    <img src="https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?q=80&w=2000&auto=format&fit=crop" class="position-absolute w-100 h-100" style="object-fit: cover; opacity: 0.6; z-index: 0;" alt="Football Player with ball">
    <div class="position-absolute w-100 h-100 d-flex flex-column justify-content-center px-4" style="z-index: 1;">
        <h2 class="text-white fw-bold display-5 mb-0">Sports Ministry</h2>
        <p class="text-light lead">Connecting disciples through faith, fitness, and fellowship.</p>
        <?php if (($userRole ?? '') === 'admin' || ($userRole ?? '') === 'super_admin'): ?>
            <div class="mt-3">
                <a href="/JDM_kenya/sports_admin.php" class="btn btn-warning btn-neon"><i class="bi bi-gear-fill"></i> Admin Panel</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <?php if (!empty($sportsError)): ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= escape($sportsError) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Live Match Feed UI -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border: 1px solid rgba(0,0,0,0.05) !important;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-broadcast text-danger"></i> Live Match Feed</h5>
            </div>
            <div class="card-body">
                <?php if ($currentMatch): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded-3" style="background: #f8f9fa;">
                        <h4 class="mb-0 text-dark fw-bold"><?= escape($currentMatch['team_a']) ?></h4>
                        <span class="badge bg-danger px-3 py-2 fs-6">VS</span>
                        <h4 class="mb-0 text-dark fw-bold"><?= escape($currentMatch['team_b']) ?></h4>
                    </div>
                    
                    <div id="commentary-container" class="position-relative" style="height: 350px; overflow-y: auto; scroll-behavior: smooth;">
                        <?php if (empty($initialCommentary)): ?>
                            <div class="text-center text-muted mt-5" id="no-comments-msg">Waiting for kick-off...</div>
                        <?php else: ?>
                            <?php foreach ($initialCommentary as $comment): ?>
                                <div class="commentary-item mb-3 p-3 rounded-3 border-start border-4 border-success bg-white shadow-sm" data-id="<?= $comment['id'] ?>" style="transition: all 0.3s ease;">
                                    <span class="fw-bold text-success me-2"><?= escape($comment['time_str']) ?>'</span>
                                    <span class="text-dark"><?= escape($comment['comment_text']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x display-4 mb-3"></i>
                        <h5>No ongoing matches</h5>
                        <p>Check the schedule for upcoming games.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Player Stats Grid (Leaderboard) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border: 1px solid rgba(0,0,0,0.05) !important;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning"></i> Leaderboard</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-pills mb-3 nav-fill" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-4" style="background-color: #198754;" id="pills-goals-tab" data-bs-toggle="pill" data-bs-target="#pills-goals" type="button" role="tab" aria-selected="true">Top Scorers</button>
                    </li>
                </ul>
                <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-goals" role="tabpanel" aria-labelledby="pills-goals-tab">
                        <?php if (empty($leaderboard)): ?>
                            <p class="text-muted text-center mt-4">No player stats recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border" style="border-radius: 8px; overflow: hidden;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">#</th>
                                            <th>Player</th>
                                            <th class="text-center">⚽</th>
                                            <th class="text-center">🏅</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaderboard as $index => $player): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($player['pfp_path'])): ?>
                                                            <img src="<?= escape($player['pfp_path']) ?>" class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;" alt="Player">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center text-white" style="width: 30px; height: 30px; font-size: 12px;">
                                                                <?= strtoupper(substr($player['name'], 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <span class="fw-semibold text-dark text-truncate" style="max-width: 100px;"><?= escape($player['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center fw-bold text-success"><?= (int)$player['goals_scored'] ?></td>
                                                <td class="text-center text-warning"><?= (int)$player['mvp_awards'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($currentMatch): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('commentary-container');
    const matchId = <?= (int)$currentMatch['id'] ?>;
    let lastId = 0;
    
    // Find the max ID currently loaded
    const items = container.querySelectorAll('.commentary-item');
    if (items.length > 0) {
        lastId = parseInt(items[items.length - 1].getAttribute('data-id'), 10);
    }
    
    // Auto-scroll to bottom
    container.scrollTop = container.scrollHeight;

    // Polling interval for live commentary (updates without page refresh)
    setInterval(() => {
        fetch(`sports.php?ajax=commentary&match_id=${matchId}&last_id=${lastId}`)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success' && res.data.length > 0) {
                    const noCommentsMsg = document.getElementById('no-comments-msg');
                    if (noCommentsMsg) noCommentsMsg.remove();
                    
                    res.data.forEach(comment => {
                        const div = document.createElement('div');
                        div.className = 'commentary-item mb-3 p-3 rounded-3 border-start border-4 border-success bg-white shadow-sm';
                        div.setAttribute('data-id', comment.id);
                        // Start invisible for slide-in animation
                        div.style.opacity = '0';
                        div.style.transform = 'translateY(20px)';
                        div.style.transition = 'all 0.4s ease-out';
                        
                        div.innerHTML = `<span class="fw-bold text-success me-2">${escapeHtml(comment.time_str)}'</span>
                                         <span class="text-dark">${escapeHtml(comment.comment_text)}</span>`;
                        
                        container.appendChild(div);
                        
                        // Trigger reflow and animate in
                        void div.offsetWidth;
                        div.style.opacity = '1';
                        div.style.transform = 'translateY(0)';
                        
                        lastId = Math.max(lastId, parseInt(comment.id, 10));
                    });
                    
                    // Smooth scroll to bottom
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }
            })
            .catch(error => console.error('Error fetching commentary:', error));
    }, 5000); // Check every 5 seconds
    
    function escapeHtml(unsafe) {
        return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
// Integrate with the main layout template
include(__DIR__ . '/../portal/layout.php');
?>
