-- JDM Kenya Portal Schema (MySQL 8+, InnoDB, utf8mb4)
-- Run this against DB `jdm_kenya` (or change DB_NAME in db_connect.php).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- USERS (base identity + auth + role/category + profile picture + WhatsApp phone)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  whatsapp_phone VARCHAR(30) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('member','admin') NOT NULL DEFAULT 'member',
  category ENUM('student','associate','partner') NOT NULL,
  pfp_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STUDENTS (only when category=student)
CREATE TABLE IF NOT EXISTS students (
  user_id INT UNSIGNED NOT NULL,
  campus_name ENUM('Main Campus','Upper Kabete','Lower Kabete','Chiromo','Kikuyu','Parklands') NOT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ASSOCIATES (formerly known as alumni; for former students and associate members)
CREATE TABLE IF NOT EXISTS associates (
  user_id INT UNSIGNED NOT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  current_profession VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_associates_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MARKETPLACE PARTNERS (replaces "others")
CREATE TABLE IF NOT EXISTS marketplace_partners (
  user_id INT UNSIGNED NOT NULL,
  business_name VARCHAR(160) DEFAULT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  current_profession VARCHAR(120) DEFAULT NULL,
  service_category VARCHAR(120) DEFAULT NULL,
  location VARCHAR(120) DEFAULT NULL,
  website_url VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_marketplace_partner_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RESOURCES (documents/pdf download center)
CREATE TABLE IF NOT EXISTS resources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(30) NOT NULL DEFAULT 'pdf',
  uploaded_by INT UNSIGNED DEFAULT NULL,
  upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_resources_upload_date (upload_date),
  CONSTRAINT fk_resources_uploaded_by
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ACTIVITIES (events, seminars, ministry programs)
CREATE TABLE IF NOT EXISTS activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  date DATE NOT NULL,
  content TEXT NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activities_date (date),
  KEY idx_activities_created_at (created_at),
  CONSTRAINT fk_activities_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRAYER REQUESTS
CREATE TABLE IF NOT EXISTS prayer_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prayer_requests_created_at (created_at),
  KEY idx_prayer_requests_is_private (is_private),
  CONSTRAINT fk_prayer_requests_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ANNOUNCEMENTS ("Newsroom" section for general updates and news visible to all members)
CREATE TABLE IF NOT EXISTS announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  content TEXT NOT NULL,
  posted_by INT UNSIGNED DEFAULT NULL,
  date_posted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcements_date_posted (date_posted),
  CONSTRAINT fk_announcements_posted_by
    FOREIGN KEY (posted_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMAGE GALLERY
CREATE TABLE IF NOT EXISTS gallery_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) DEFAULT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gallery_uploaded_at (uploaded_at),
  CONSTRAINT fk_gallery_uploaded_by
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

