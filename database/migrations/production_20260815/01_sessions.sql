SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bible_study_id` INT UNSIGNED NOT NULL,
  `session_name` VARCHAR(80) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `location` VARCHAR(255) NULL,
  `meeting_link` VARCHAR(500) NULL,
  `capacity` INT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` TINYINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bs_session_order` (`bible_study_id`, `display_order`),
  KEY `idx_bs_session_active` (`bible_study_id`, `is_active`),
  CONSTRAINT `fk_bss_study` FOREIGN KEY (`bible_study_id`)
    REFERENCES `bible_studies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

