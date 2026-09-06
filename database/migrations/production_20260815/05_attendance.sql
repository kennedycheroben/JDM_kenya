SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` INT UNSIGNED NOT NULL,
  `attendance_status` ENUM('not_recorded','present','absent','excused')
    NOT NULL DEFAULT 'not_recorded',
  `recorded_by` INT UNSIGNED NULL,
  `recorded_at` DATETIME NULL,
  `remark` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bs_attendance_registration` (`registration_id`),
  KEY `idx_bs_attendance_status` (`attendance_status`),
  CONSTRAINT `fk_bsa_registration` FOREIGN KEY (`registration_id`)
    REFERENCES `bible_study_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsa_actor` FOREIGN KEY (`recorded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

