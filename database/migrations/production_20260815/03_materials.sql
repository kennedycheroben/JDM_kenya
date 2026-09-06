SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `bible_study_materials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bible_study_id` INT UNSIGNED NOT NULL,
  `material_title` VARCHAR(180) NOT NULL,
  `description` VARCHAR(1000) NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `storage_key` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `file_extension` VARCHAR(10) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bs_material_storage` (`storage_key`),
  KEY `idx_bs_material_study` (`bible_study_id`, `is_published`),
  CONSTRAINT `fk_bsm_study` FOREIGN KEY (`bible_study_id`)
    REFERENCES `bible_studies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bsm_uploader` FOREIGN KEY (`uploaded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

