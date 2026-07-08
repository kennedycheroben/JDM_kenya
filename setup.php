<?php
require_once __DIR__ . '/core/db_connect.php';

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    die('Access denied. Only super admin can run setup.');
}

$messages = [];
$success = true;

if (isset($_POST['run_migration'])) {
    require_csrf();

    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            message_text TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'Messages table ready.';
    } catch (PDOException $e) {
        $messages[] = 'Messages table error: ' . $e->getMessage();
        $success = false;
    }

    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS leaders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            leader_type VARCHAR(50) NOT NULL,
            campus_name VARCHAR(100) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_leaders_user (user_id),
            KEY idx_leaders_type (leader_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'Leaders table ready.';
    } catch (PDOException $e) {
        $messages[] = 'Leaders table error: ' . $e->getMessage();
        $success = false;
    }

    // ── GBS Tables ──────────────────────────────────────────────────────────
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS gbs_groups (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            slogan VARCHAR(255) NULL DEFAULT NULL,
            leader_id INT UNSIGNED NOT NULL,
            pfp_path VARCHAR(255) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gbs_groups_leader (leader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'gbs_groups table ready.';
    } catch (PDOException $e) {
        $messages[] = 'gbs_groups table error: ' . $e->getMessage();
        $success = false;
    }

    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS gbs_members (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            gbs_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gbs_members_gbs (gbs_id),
            KEY idx_gbs_members_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'gbs_members table ready.';
    } catch (PDOException $e) {
        $messages[] = 'gbs_members table error: ' . $e->getMessage();
        $success = false;
    }

    // ── Associates Table ─────────────────────────────────────────────────────
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS associates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            graduation_year YEAR NULL DEFAULT NULL,
            current_profession VARCHAR(150) NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_associates_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'associates table ready.';
    } catch (PDOException $e) {
        $messages[] = 'associates table error: ' . $e->getMessage();
        $success = false;
    }

    // ── Announcements Table ──────────────────────────────────────────────────
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'announcements table ready.';
    } catch (PDOException $e) {
        $messages[] = 'announcements table error: ' . $e->getMessage();
        $success = false;
    }

    // ── users column additions ────────────────────────────────────────────────
    $colChecks = [
        ['table' => 'users', 'column' => 'reset_token',    'def' => "VARCHAR(64) NULL DEFAULT NULL"],
        ['table' => 'users', 'column' => 'token_expires_at','def' => "DATETIME NULL DEFAULT NULL"],
        ['table' => 'users', 'column' => 'is_approved',    'def' => "TINYINT(1) NOT NULL DEFAULT 1"],
        ['table' => 'users', 'column' => 'missionary_type','def' => "VARCHAR(50) NULL DEFAULT NULL"],
        ['table' => 'users', 'column' => 'country',        'def' => "VARCHAR(100) NULL DEFAULT NULL"],
        ['table' => 'users', 'column' => 'is_staff',       'def' => "TINYINT(1) NOT NULL DEFAULT 0"],
        ['table' => 'users', 'column' => 'staff_type',     'def' => "VARCHAR(50) NULL DEFAULT NULL"],
        ['table' => 'users', 'column' => 'is_gbs_leader',  'def' => "TINYINT(1) NOT NULL DEFAULT 0"],
    ];

    foreach ($colChecks as $c) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$c['table']}` LIKE '{$c['column']}'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `{$c['table']}` ADD COLUMN `{$c['column']}` {$c['def']}");
                $messages[] = "Added column '{$c['column']}' to {$c['table']}.";
            } else {
                $messages[] = "Column '{$c['column']}' already exists in {$c['table']}.";
            }
        } catch (PDOException $e) {
            $messages[] = "Error with {$c['column']}: " . $e->getMessage();
            $success = false;
        }
    }

    try {
        $pdo->query("CREATE INDEX IF NOT EXISTS idx_users_missionary ON users (missionary_type, is_approved)");
        $messages[] = 'Index idx_users_missionary ready.';
    } catch (PDOException $e) {
        $messages[] = 'Note: idx_users_missionary index (may already exist): ' . $e->getMessage();
    }

    try {
        $pdo->query("CREATE INDEX IF NOT EXISTS idx_users_staff ON users (is_staff, staff_type)");
        $messages[] = 'Index idx_users_staff ready.';
    } catch (PDOException $e) {
        $messages[] = 'Note: idx_users_staff index (may already exist): ' . $e->getMessage();
    }

    $messages[] = $success ? 'Migration completed successfully.' : 'Migration completed with errors.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - JDM Kenya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-body p-4">
            <h3 class="mb-3">System Setup</h3>
            <p class="text-muted">Run database migrations to add missing columns for new features.</p>

            <?php if ($messages): ?>
                <?php foreach ($messages as $m): ?>
                    <div class="alert alert-info py-2 small"><?= escape($m) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <button type="submit" name="run_migration" class="btn btn-primary w-100">Run Database Migration</button>
            </form>

            <hr>
            <h5 class="mt-4">SMTP Configuration Check</h5>
            <p class="small text-muted">The password reset emails are sent via SMTP. Check that <code>config.php</code> has correct credentials:</p>
            <ul class="small">
                <li><strong>SMTP_HOST</strong>: mail.jdmkenya.com</li>
                <li><strong>SMTP_PORT</strong>: 465 (SSL) or 587 (TLS)</li>
                <li><strong>SMTP_USERNAME</strong>: Should be a full email (e.g., <code>noreply@jdmkenya.com</code>)</li>
                <li><strong>MAIL_FROM_EMAIL</strong>: Should be a full email (e.g., <code>noreply@jdmkenya.com</code>)</li>
            </ul>
            <p class="small text-muted">You can test email sending at <a href="test_email.php">test_email.php</a></p>
        </div>
    </div>
</div>
</body>
</html>
