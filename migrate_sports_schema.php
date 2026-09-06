<?php
require_once __DIR__ . '/core/db_connect.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli && (($_SESSION['user_role'] ?? '') !== 'super_admin')) { http_response_code(403); exit('Forbidden'); }
if (!$isCli && (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !require_csrf())) { http_response_code(405); exit('POST with valid CSRF required'); }

$schema = file_get_contents(__DIR__ . '/database/migrations/20260816_sports_ministry.sql');
if ($schema === false) exit("Migration file not found.\n");
try {
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', preg_replace('/^\s*--.*$/m', '', $schema)))) as $statement) $pdo->exec($statement);
    $columns = [
        'sports_applications' => [
            'application_source' => "ENUM('new_user','existing_user') NOT NULL DEFAULT 'new_user' AFTER user_id"
        ],
        'sports_matches' => [
            'competition_type' => "VARCHAR(100) NULL AFTER match_type", 'venue' => "VARCHAR(180) NULL AFTER competition_type",
            'location_type' => "ENUM('home','away','neutral') NOT NULL DEFAULT 'neutral' AFTER venue",
            'team_a_score' => "SMALLINT UNSIGNED NULL AFTER location_type", 'team_b_score' => "SMALLINT UNSIGNED NULL AFTER team_a_score",
            'is_public' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER team_b_score", 'updated_at' => "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ],
        'sports_stats' => [
            'appearances' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER gbs_id", 'starts' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER appearances",
            'clean_sheets' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER assists", 'yellow_cards' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER clean_sheets",
            'red_cards' => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER yellow_cards", 'updated_at' => "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ]
    ];
    foreach ($columns as $table=>$defs) foreach ($defs as $column=>$definition) {
        $q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'); $q->execute([$table,$column]);
        if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
    $pdo->exec("UPDATE sports_applications a JOIN users u ON u.id=a.user_id SET a.application_source=CASE WHEN u.category='sports_ministry' THEN 'new_user' ELSE 'existing_user' END");
    $index=$pdo->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sports_applications' AND INDEX_NAME='idx_sports_app_source_status'");$index->execute();
    if (!$index->fetchColumn()) $pdo->exec('ALTER TABLE sports_applications ADD KEY idx_sports_app_source_status (application_source,status)');
    $pdo->exec("ALTER TABLE sports_matches MODIFY status ENUM('Scheduled','Ongoing','Completed','Postponed','Cancelled') NOT NULL DEFAULT 'Scheduled'");
    $pdo->exec('ALTER TABLE sports_announcements MODIFY content LONGTEXT NOT NULL');
    echo "Sports Ministry migration completed.\n";
} catch (Throwable $e) { error_log('Sports migration failed: '.$e->getMessage()); http_response_code(500); exit("Migration failed; no rollback was run automatically. Review the server error log.\n"); }
