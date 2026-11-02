-- JDM Kenya Migration: User Content Read Tracking
-- Tracks per-user read state for announcements, activities, resources, prayers, and bible studies

CREATE TABLE IF NOT EXISTS user_content_reads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    content_type VARCHAR(32) NOT NULL,
    content_id INT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_content (user_id, content_type, content_id),
    KEY idx_user_type (user_id, content_type),
    CONSTRAINT fk_user_content_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
