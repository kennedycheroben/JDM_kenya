-- JDM Kenya incremental schema updates (run once)
-- Adds: super_admin role, resources category, messages table, announcements date_created.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) users.role: add super_admin
-- MySQL requires redefining ENUM list in-place.
ALTER TABLE users
  MODIFY role ENUM('member','admin','super_admin') NOT NULL DEFAULT 'member';

-- 2) resources.category: multi-tier portal categories
ALTER TABLE resources
  ADD COLUMN category ENUM('study_material','pdf_resource','video_content') NOT NULL DEFAULT 'pdf_resource' AFTER title;

-- Optional: keep file_type but align defaults
ALTER TABLE resources
  MODIFY file_type VARCHAR(30) NOT NULL DEFAULT 'pdf';

-- 3) messages table: persistent chat
CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  message_text TEXT NOT NULL,
  status ENUM('sent','read') NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_pair (sender_id, receiver_id, id),
  KEY idx_messages_receiver (receiver_id, id),
  KEY idx_messages_receiver_status (receiver_id, status, id),
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If messages table already existed (older install), add status + index safely (dynamic SQL)
SET @has_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE table_schema = DATABASE()
    AND table_name = 'messages'
    AND column_name = 'status'
);
SET @sql_status := IF(
  @has_status = 0,
  "ALTER TABLE messages ADD COLUMN status ENUM('sent','read') NOT NULL DEFAULT 'sent' AFTER message_text",
  "SELECT 1"
);
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE table_schema = DATABASE()
    AND table_name = 'messages'
    AND index_name = 'idx_messages_receiver_status'
);
SET @sql_idx := IF(
  @has_idx = 0,
  "ALTER TABLE messages ADD KEY idx_messages_receiver_status (receiver_id, status, id)",
  "SELECT 1"
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 4) announcements: ensure date_created exists (keep backward compatibility with date_posted)
ALTER TABLE announcements
  ADD COLUMN date_created TIMESTAMP NULL DEFAULT NULL AFTER content;

UPDATE announcements
  SET date_created = COALESCE(date_created, date_posted);

ALTER TABLE announcements
  MODIFY date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

