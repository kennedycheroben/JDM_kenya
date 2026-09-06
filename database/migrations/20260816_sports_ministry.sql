-- JDM Kenya Sports Ministry - additive production migration
-- Preflight: back up the database and verify users.id is INT UNSIGNED.
SET NAMES utf8mb4;

ALTER TABLE users
  MODIFY category ENUM('student','associate','partner','missionary','sports_ministry','other') NOT NULL DEFAULT 'other';

CREATE TABLE IF NOT EXISTS sports_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  application_source ENUM('new_user','existing_user') NOT NULL DEFAULT 'new_user',
  date_of_birth DATE NOT NULL,
  general_estate VARCHAR(120) NOT NULL,
  education_level ENUM('primary_school','high_school','college_university','graduate') NOT NULL,
  primary_position ENUM('goalkeeper','right_back','centre_back','left_back','defensive_midfielder','central_midfielder','attacking_midfielder','right_winger','left_winger','striker','not_sure') NOT NULL,
  preferred_jersey_number TINYINT UNSIGNED NOT NULL,
  guardian_name VARCHAR(120) NULL,
  guardian_phone VARCHAR(30) NULL,
  guardian_consent_at DATETIME NULL,
  guardian_policy_version VARCHAR(30) NULL,
  rules_accepted_at DATETIME NOT NULL,
  publication_acknowledged_at DATETIME NOT NULL,
  publication_policy_version VARCHAR(30) NOT NULL,
  status ENUM('pending','approved','rejected','suspended','withdrawn') NOT NULL DEFAULT 'pending',
  submitted_at DATETIME NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  applicant_message VARCHAR(1000) NULL,
  internal_review_note VARCHAR(2000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_application_user (user_id),
  KEY idx_sports_app_status_submitted (status, submitted_at),
  KEY idx_sports_app_source_status (application_source, status),
  CONSTRAINT fk_sports_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL, application_id INT UNSIGNED NOT NULL,
  membership_status ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  primary_position VARCHAR(40) NULL, assigned_jersey_number TINYINT UNSIGNED NULL,
  joined_at DATETIME NOT NULL, suspended_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_jersey_number TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN membership_status='active' THEN assigned_jersey_number ELSE NULL END) STORED,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_member_user (user_id), UNIQUE KEY uq_sports_member_application (application_id),
  UNIQUE KEY uq_sports_active_jersey (active_jersey_number), KEY idx_sports_member_status (membership_status),
  CONSTRAINT fk_sports_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_member_application FOREIGN KEY (application_id) REFERENCES sports_applications(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_admin_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL, appointed_by INT UNSIGNED NULL,
  appointed_at DATETIME NOT NULL, ended_at DATETIME NULL,
  status ENUM('active','suspended','ended') NOT NULL DEFAULT 'active', appointment_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_user_id INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='active' THEN user_id ELSE NULL END) STORED,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_admin_active (active_user_id), KEY idx_sports_admin_user_status (user_id,status),
  CONSTRAINT fk_sports_admin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_admin_actor FOREIGN KEY (appointed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_role_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sports_member_id INT UNSIGNED NOT NULL,
  role_code ENUM('player','captain','assistant_captain','head_coach','assistant_coach','goalkeeping_coach','fitness_coach','team_manager','sports_administrator','welfare_discipleship_coordinator','medical_first_aid_officer','kit_equipment_manager','communications_media_officer') NOT NULL,
  appointed_by INT UNSIGNED NULL, starts_at DATE NOT NULL, ends_at DATE NULL,
  status ENUM('active','ended','suspended') NOT NULL DEFAULT 'active', appointment_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_unique_role VARCHAR(40) GENERATED ALWAYS AS (CASE WHEN status='active' AND role_code IN ('captain','assistant_captain') THEN role_code ELSE NULL END) STORED,
  active_member_role VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN status='active' THEN CONCAT(sports_member_id,':',role_code) ELSE NULL END) STORED,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_single_leadership (active_unique_role), UNIQUE KEY uq_sports_member_active_role (active_member_role),
  KEY idx_sports_roles_member_status (sports_member_id,status),
  CONSTRAINT fk_sports_role_member FOREIGN KEY (sports_member_id) REFERENCES sports_members(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_role_actor FOREIGN KEY (appointed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_public_profiles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sports_member_id INT UNSIGNED NOT NULL,
  public_identifier CHAR(32) NOT NULL, public_biography TEXT NULL, public_achievements TEXT NULL,
  institution_name_public VARCHAR(160) NULL, profile_photo_path VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_profile_member (sports_member_id), UNIQUE KEY uq_sports_public_identifier (public_identifier),
  KEY idx_sports_profile_published (is_published,published_at),
  CONSTRAINT fk_sports_profile_member FOREIGN KEY (sports_member_id) REFERENCES sports_members(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_profile_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_training_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(180) NOT NULL, training_date DATE NOT NULL,
  start_time TIME NOT NULL, end_time TIME NULL, location VARCHAR(180) NOT NULL, instructions VARCHAR(1000) NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled', is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_sports_training_public_date (is_public,status,training_date),
  CONSTRAINT fk_sports_training_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_training_attendance (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, training_session_id INT UNSIGNED NOT NULL, sports_member_id INT UNSIGNED NOT NULL,
  attendance_status ENUM('not_recorded','present','absent','excused','late') NOT NULL DEFAULT 'not_recorded', remark VARCHAR(500) NULL,
  recorded_by INT UNSIGNED NULL, recorded_at DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_sports_attendance (training_session_id,sports_member_id), KEY idx_sports_attendance_member (sports_member_id),
  CONSTRAINT fk_sports_attendance_session FOREIGN KEY (training_session_id) REFERENCES sports_training_sessions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_attendance_member FOREIGN KEY (sports_member_id) REFERENCES sports_members(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sports_attendance_actor FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, content LONGTEXT NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft', is_public TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL, expires_at DATETIME NULL, created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_sports_announcement_public (is_public,status,published_at,expires_at),
  CONSTRAINT fk_sports_announcement_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, actor_user_id INT UNSIGNED NULL, action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL, entity_id INT UNSIGNED NULL, metadata_json JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_sports_audit_entity (entity_type,entity_id,created_at), KEY idx_sports_audit_actor (actor_user_id,created_at),
  CONSTRAINT fk_sports_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend legacy tables without removing existing data. Run each ALTER only after
-- checking information_schema on production; the provided PHP migrator is idempotent.
