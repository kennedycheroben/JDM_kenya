-- JDM Kenya - Additive Migration: Reconcile Campus GBS Leadership Appointments
-- Date: 2026-11-03
-- Safe, idempotent, non-destructive migration for cPanel / MySQL / MariaDB.

-- 1. Ensure `is_gbs_leader` column exists on `users`
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'is_gbs_leader'
);
SET @sql_add_col := IF(
    @col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_gbs_leader` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt_col FROM @sql_add_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- 2. Ensure `leaders` table exists
CREATE TABLE IF NOT EXISTS `leaders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `leader_type` VARCHAR(50) NOT NULL,
    `campus_name` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leaders_user` (`user_id`),
    KEY `idx_leaders_type` (`leader_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Reconcile users appointed via `leaders` (`leader_type = 'gbs_leader'`) into `users.is_gbs_leader = 1`
UPDATE `users` u
INNER JOIN `leaders` l ON l.user_id = u.id AND l.leader_type = 'gbs_leader'
SET u.is_gbs_leader = 1
WHERE u.is_gbs_leader = 0;

-- 4. Ensure existing GBS group owners have `users.is_gbs_leader = 1`
UPDATE `users` u
INNER JOIN `gbs_groups` g ON g.leader_id = u.id
SET u.is_gbs_leader = 1
WHERE u.is_gbs_leader = 0;

-- 5. Ensure every user with `users.is_gbs_leader = 1` has a corresponding `gbs_leader` record in `leaders`
INSERT INTO `leaders` (`user_id`, `leader_type`, `campus_name`, `created_by`)
SELECT u.id, 'gbs_leader', NULL, u.id
FROM `users` u
WHERE u.is_gbs_leader = 1
  AND NOT EXISTS (
      SELECT 1 FROM `leaders` l
      WHERE l.user_id = u.id AND l.leader_type = 'gbs_leader'
  );

-- 6. Deduplicate any duplicate `gbs_leader` rows for the same user in `leaders`
DELETE l1 FROM `leaders` l1
INNER JOIN `leaders` l2
    ON l1.user_id = l2.user_id
   AND l1.leader_type = 'gbs_leader'
   AND l2.leader_type = 'gbs_leader'
   AND l1.id > l2.id;

-- 7. Ensure every `gbs_groups` owner is registered as `'leader'` in `gbs_members`
INSERT INTO `gbs_members` (`gbs_id`, `user_id`, `role`, `joined_at`)
SELECT g.id, g.leader_id, 'leader', g.created_at
FROM `gbs_groups` g
INNER JOIN `users` u ON u.id = g.leader_id
WHERE NOT EXISTS (
    SELECT 1 FROM `gbs_members` gm
    WHERE gm.gbs_id = g.id AND gm.user_id = g.leader_id
);

UPDATE `gbs_members` gm
INNER JOIN `gbs_groups` g ON g.id = gm.gbs_id AND g.leader_id = gm.user_id
SET gm.role = 'leader'
WHERE gm.role <> 'leader';

