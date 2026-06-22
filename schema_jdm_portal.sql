-- JDM Kenya Portal Master Schema (MySQL 8+, InnoDB, utf8mb4)
-- This file represents the complete, unified database schema for production initialization.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================================
-- 1. CORE USER & ACCOUNT TABLE
-- =========================================================================

-- USERS: Base identity, authentication, categories (Student, Associate, Partner/Office Bearer),
-- and administrative role status.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  whatsapp_phone VARCHAR(30) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('member','admin','super_admin') NOT NULL DEFAULT 'member',
  is_gbs_leader TINYINT(1) NOT NULL DEFAULT 0,
  category ENUM('student','associate','partner','other') NOT NULL DEFAULT 'other',
  pfp_path VARCHAR(255) DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,
  
  -- Academic & JDM History (UON Alumni Verification)
  graduation_year INT DEFAULT NULL,
  campus_role VARCHAR(100) DEFAULT NULL,
  
  -- Professional & Business Profile
  employment_status ENUM('Employed','Business Owner','Self-Employed','Freelancer','Other') DEFAULT NULL,
  company_name VARCHAR(150) DEFAULT NULL,
  industry_profession VARCHAR(100) DEFAULT NULL,
  
  -- Ministry Partnership & Support
  partnership_focus VARCHAR(100) DEFAULT NULL,
  contribution_phone VARCHAR(20) DEFAULT NULL,
  
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_category (category),
  KEY idx_users_email (email),
  KEY idx_users_is_gbs_leader (is_gbs_leader)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 2. CONDITIONAL MEMBER SUB-TABLES (RELATIONAL INTEGRITY)
-- =========================================================================

-- STUDENTS: Specialized fields loaded when category = 'student'
CREATE TABLE IF NOT EXISTS students (
  user_id INT UNSIGNED NOT NULL,
  campus_name ENUM('Main Campus','Upper Kabete','Lower Kabete','Chiromo','Kikuyu','Parklands') NOT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ASSOCIATES: Specialized fields loaded when category = 'associate'
CREATE TABLE IF NOT EXISTS associates (
  user_id INT UNSIGNED NOT NULL,
  graduation_year SMALLINT UNSIGNED DEFAULT NULL,
  current_profession VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_associates_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MARKETPLACE PARTNERS: Left in place for backward compatibility
CREATE TABLE IF NOT EXISTS marketplace_partners (
  user_id INT UNSIGNED NOT NULL,
  business_name VARCHAR(160) DEFAULT NULL,
  service_category VARCHAR(120) DEFAULT NULL,
  location VARCHAR(120) DEFAULT NULL,
  website_url VARCHAR(255) DEFAULT NULL,
  graduation_year YEAR DEFAULT NULL,
  current_profession VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_marketplace_partner_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 3. GENERAL MINISTRY MODULES
-- =========================================================================

-- RESOURCES: Document / PDF Download Center
CREATE TABLE IF NOT EXISTS resources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  category ENUM('study_material','pdf_resource','video_content') NOT NULL DEFAULT 'pdf_resource',
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(30) NOT NULL DEFAULT 'pdf',
  uploaded_by INT UNSIGNED DEFAULT NULL,
  upload_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_resources_upload_date (upload_date),
  KEY idx_resources_category (category),
  CONSTRAINT fk_resources_uploaded_by
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ACTIVITIES: Ministry events, details, and calendar items
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

-- PRAYER REQUESTS: Intercessory prayer wall postings
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

-- ANNOUNCEMENTS: Newsroom postings visible to all members
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

-- CONTACT MESSAGES: Public contact form submissions visible to admins
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('unread','read') NOT NULL DEFAULT 'unread',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_contact_messages_status_created (status, created_at),
  KEY idx_contact_messages_created_at (created_at),
  KEY idx_contact_messages_user_id (user_id),
  CONSTRAINT fk_contact_messages_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GALLERY IMAGES: Image uploads from programs and events
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

-- =========================================================================
-- 4. GRACE BIBLE STUDY (GBS) GROUP NETWORK MODULES
-- =========================================================================

-- GBS GROUPS: Group properties and GBS Leader assignment
CREATE TABLE IF NOT EXISTS gbs_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  slogan VARCHAR(255) DEFAULT NULL,
  pfp_path VARCHAR(255) DEFAULT NULL,
  leader_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  chat_restricted TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_gbs_groups_leader
    FOREIGN KEY (leader_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GBS MEMBERS: Many-to-many relationship mapping members to GBS groups
CREATE TABLE IF NOT EXISTS gbs_members (
  gbs_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('leader','member') NOT NULL DEFAULT 'member',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (gbs_id, user_id),
  CONSTRAINT fk_gbs_members_gbs
    FOREIGN KEY (gbs_id) REFERENCES gbs_groups (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gbs_members_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GBS MESSAGES: Group conversation history
CREATE TABLE IF NOT EXISTS gbs_messages (
  id INT NOT NULL AUTO_INCREMENT,
  gbs_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gbs_messages_gbs_id (gbs_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GBS RESOURCES: Shared Bible study guides and audio files
CREATE TABLE IF NOT EXISTS gbs_resources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gbs_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(50) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_gbs_resources_gbs
    FOREIGN KEY (gbs_id) REFERENCES gbs_groups (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gbs_resources_uploader
    FOREIGN KEY (uploaded_by) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GBS ANNOUNCEMENTS: Bulletins and updates within a specific GBS group
CREATE TABLE IF NOT EXISTS gbs_announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gbs_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_gbs_announcements_gbs
    FOREIGN KEY (gbs_id) REFERENCES gbs_groups (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gbs_announcements_author
    FOREIGN KEY (author_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 5. REAL-TIME CHAT & MESSAGING MODULE
-- =========================================================================

-- MESSAGES: Direct 1-to-1 chats between users
CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  message_text TEXT NOT NULL,
  status ENUM('sent','delivered','read') DEFAULT 'sent',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at TIMESTAMP NULL DEFAULT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_messages_pair (sender_id, receiver_id, id),
  KEY idx_messages_receiver (receiver_id, id),
  KEY idx_messages_status (status),
  KEY idx_messages_sender_receiver_status (sender_id, receiver_id, status),
  CONSTRAINT fk_messages_receiver
    FOREIGN KEY (receiver_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_messages_sender
    FOREIGN KEY (sender_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 6. MEDIA PROGRESS TRACKING MODULE
-- =========================================================================

-- VIDEO PROGRESS: Track course/sermon material watching completion
CREATE TABLE IF NOT EXISTS video_progress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  video_id VARCHAR(255) NOT NULL,
  progress_percent INT DEFAULT 0,
  completed TINYINT(1) DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_video_progress_user_video (user_id, video_id),
  KEY idx_video_progress_video_id (video_id),
  CONSTRAINT fk_video_progress_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- 7. BIRTHDAY CELEBRATIONS & COUNTDOWNS
-- =========================================================================

-- BIRTHDAY CELEBRATIONS: Historical log of celebrated birthdays
CREATE TABLE IF NOT EXISTS birthday_celebrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  age INT NOT NULL,
  celebration_date DATE NOT NULL,
  message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_birthday_celebrations_user (user_id),
  CONSTRAINT fk_birthday_celebrations_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BIRTHDAY COUNTDOWNS: Real-time calculation cache for next birthday countdowns
CREATE TABLE IF NOT EXISTS birthday_countdowns (
  user_id INT UNSIGNED NOT NULL,
  user_name VARCHAR(120) NOT NULL,
  days_until INT NOT NULL,
  hours_until INT NOT NULL,
  age_will_be INT NOT NULL,
  birthday_date DATE NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_birthday_countdowns_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
