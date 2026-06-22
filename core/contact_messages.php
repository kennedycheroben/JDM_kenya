<?php

if (!function_exists('ensureContactMessagesTable')) {
    function ensureContactMessagesTable(PDO $pdo): void
    {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
