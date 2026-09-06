SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `metadata_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bs_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_bs_audit_actor_date` (`actor_user_id`, `created_at`),
  CONSTRAINT `fk_bsal_actor` FOREIGN KEY (`actor_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

