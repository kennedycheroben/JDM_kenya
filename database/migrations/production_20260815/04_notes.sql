SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bible_study_id` INT UNSIGNED NOT NULL,
  `member_user_id` INT UNSIGNED NOT NULL,
  `session_id` INT UNSIGNED NULL,
  `note_content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bs_note_owner` (`bible_study_id`, `member_user_id`),
  KEY `idx_bs_notes_member` (`member_user_id`),
  CONSTRAINT `fk_bsn_study` FOREIGN KEY (`bible_study_id`)
    REFERENCES `bible_studies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsn_member` FOREIGN KEY (`member_user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsn_session` FOREIGN KEY (`session_id`)
    REFERENCES `bible_study_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

