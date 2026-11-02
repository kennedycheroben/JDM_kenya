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
                status VARCHAR(30) NOT NULL DEFAULT 'unread',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP NULL DEFAULT NULL,
                archived_at TIMESTAMP NULL DEFAULT NULL,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_contact_messages_status_created (status, created_at),
                KEY idx_contact_messages_created_at (created_at),
                KEY idx_contact_messages_user_id (user_id),
                KEY idx_contact_messages_deleted_at (deleted_at),
                CONSTRAINT fk_contact_messages_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Additive migration if table was already created with older schema
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM contact_messages LIKE 'archived_at'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE contact_messages ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER read_at");
            }
        } catch (Throwable $e) {
            error_log('contact_messages add archived_at: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM contact_messages LIKE 'deleted_at'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE contact_messages ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER archived_at, ADD KEY idx_contact_messages_deleted_at (deleted_at)");
            }
        } catch (Throwable $e) {
            error_log('contact_messages add deleted_at: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM contact_messages LIKE 'status'");
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($col && strpos(strtolower($col['Type']), 'enum') !== false) {
                $pdo->exec("ALTER TABLE contact_messages MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'unread'");
            }
        } catch (Throwable $e) {
            error_log('contact_messages modify status: ' . $e->getMessage());
        }
    }
}
