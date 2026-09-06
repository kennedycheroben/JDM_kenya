SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bible_study_id` INT UNSIGNED NOT NULL,
  `session_id` INT UNSIGNED NOT NULL,
  `member_user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','cancelled','moved') NOT NULL DEFAULT 'active',
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `moved_by` INT UNSIGNED NULL,
  `administrative_note` VARCHAR(1000) NULL,
  `active_study_id` INT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'active' THEN `bible_study_id` ELSE NULL END
  ) STORED,
  `active_member_id` INT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'active' THEN `member_user_id` ELSE NULL END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bs_active_registration` (`active_study_id`, `active_member_id`),
  KEY `idx_bs_reg_session_status` (`session_id`, `status`),
  KEY `idx_bs_reg_member_date` (`member_user_id`, `bible_study_id`),
  CONSTRAINT `fk_bsr_study` FOREIGN KEY (`bible_study_id`)
    REFERENCES `bible_studies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bsr_session` FOREIGN KEY (`session_id`)
    REFERENCES `bible_study_sessions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bsr_member` FOREIGN KEY (`member_user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_bsr_mover` FOREIGN KEY (`moved_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

