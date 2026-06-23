<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/sports_schema.php';

$userRole = $_SESSION['user_role'] ?? null;
if ($userRole !== 'admin' && $userRole !== 'super_admin') {
    header('Location: ' . BASE_PATH . '/sports.php');
    exit;
}

try {
    ensure_sports_schema($pdo);
} catch (Throwable $e) {
    error_log('sports_admin schema setup: ' . $e->getMessage());
    $schemaError = 'Sports tables could not be prepared. Please check the database setup.';
}

$success = '';
$error = $schemaError ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$error) {
    require_csrf();
    if (isset($_POST['create_match'])) {
        $teamA = trim($_POST['team_a']);
        $teamB = trim($_POST['team_b']);
        if ($teamA && $teamB) {
            $stmt = $pdo->prepare("INSERT INTO sports_matches (team_a, team_b, status, start_time) VALUES (?, ?, 'Ongoing', NOW())");
            $stmt->execute([$teamA, $teamB]);
            $success = "Match created and set as Ongoing.";
        } else {
            $error = "Team names cannot be empty.";
        }
    } elseif (isset($_POST['end_match'])) {
        $matchId = (int)$_POST['match_id'];
        $pdo->prepare("UPDATE sports_matches SET status = 'Completed' WHERE id = ?")->execute([$matchId]);
        $success = "Match marked as Completed.";
    } elseif (isset($_POST['add_commentary'])) {
        $matchId = (int)$_POST['match_id'];
        $comment = trim($_POST['comment_text']);
        if ($matchId && $comment) {
            $stmt = $pdo->prepare("INSERT INTO sports_commentary (match_id, comment_text) VALUES (?, ?)");
            $stmt->execute([$matchId, $comment]);
            $success = "Commentary added.";
        } else {
            $error = "Commentary text cannot be empty.";
        }
    } elseif (isset($_POST['update_stats'])) {
        $playerId = (int)$_POST['user_id'];
        $goals = (int)$_POST['goals'];
        $assists = (int)$_POST['assists'];
        $mvp = (int)$_POST['mvp_awards'];
        
        if ($playerId) {
            $stmt = $pdo->prepare("SELECT id FROM sports_stats WHERE user_id = ?");
            $stmt->execute([$playerId]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE sports_stats SET goals_scored = goals_scored + ?, assists = assists + ?, mvp_awards = mvp_awards + ? WHERE user_id = ?")->execute([$goals, $assists, $mvp, $playerId]);
            } else {
                $pdo->prepare("INSERT INTO sports_stats (user_id, goals_scored, assists, mvp_awards) VALUES (?, ?, ?, ?)")->execute([$playerId, $goals, $assists, $mvp]);
            }
            $success = "Player stats updated successfully.";
        } else {
            $error = "Please select a player.";
        }
    }
}

// Fetch data with graceful fallback if tables were just created
$ongoingMatches = [];
$users = [];
try {
    $ongoingMatches = $pdo->query("SELECT * FROM sports_matches WHERE status = 'Ongoing' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Could not load matches. Please refresh the page.';
    error_log('sports_admin matches query: ' . $e->getMessage());
}
try {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Could not load users. Please refresh the page.';
    error_log('sports_admin users query: ' . $e->getMessage());
}

$page_title = "Sports Ministry Admin - JDM Kenya";
ob_start();
?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="bi bi-gear-fill text-warning"></i> Sports Admin Panel</h2>
            <p class="text-muted mb-0">Manage live matches, commentary, and player leaderboards.</p>
        </div>
        <a href="<?= BASE_PATH ?>/sports.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Hub</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= escape($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= escape($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-success text-white rounded-top-4 pt-3 pb-2">
                <h5 class="mb-0"><i class="bi bi-play-circle"></i> Match Management</h5>
            </div>
            <div class="card-body">
                <h6>Create New Match</h6>
                <form method="POST" class="mb-4">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col"><input type="text" name="team_a" class="form-control" placeholder="Team A" required></div>
                        <div class="col-auto"><span class="badge bg-danger">VS</span></div>
                        <div class="col"><input type="text" name="team_b" class="form-control" placeholder="Team B" required></div>
                    </div>
                    <button type="submit" name="create_match" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Start Match</button>
                </form>

                <hr>

                <h6>Ongoing Matches & Commentary</h6>
                <?php if ($ongoingMatches): ?>
                    <?php foreach ($ongoingMatches as $match): ?>
                        <div class="p-3 rounded mb-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="fs-5"><?= escape($match['team_a']) ?> vs <?= escape($match['team_b']) ?></strong>
                                <form method="POST" onsubmit="return confirm('End this match?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                    <button type="submit" name="end_match" class="btn btn-sm btn-outline-danger">End Match</button>
                                </form>
                            </div>
                            <form method="POST" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input type="text" name="comment_text" class="form-control form-control-sm" placeholder="Live commentary (e.g., Goal by John!)" required>
                                <button type="submit" name="add_commentary" class="btn btn-sm btn-primary flex-shrink-0">Add</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small">No ongoing matches found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-success text-white rounded-top-4 pt-3 pb-2">
                <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> Player Stats (Leaderboard)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Update goals, assists, and MOM awards. These will be added to the player's existing tally.</p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Player</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Choose Player --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Add Goals</label>
                            <input type="number" name="goals" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Add Assists</label>
                            <input type="number" name="assists" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Add MOM</label>
                            <input type="number" name="mvp_awards" class="form-control" value="0" min="0">
                        </div>
                    </div>
                    <button type="submit" name="update_stats" class="btn btn-success w-100"><i class="bi bi-upload"></i> Update Player Stats</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include(__DIR__ . '/../portal/layout.php');
?>
