<?php
/**
 * JDM Kenya - Notification and Read-Tracking Engine
 *
 * Provides per-user read tracking, dynamic notification badge counts,
 * and role-aware notification queries for both members and administrators.
 */

if (!function_exists('ensureUserContentReadsTable')) {
    function ensureUserContentReadsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_content_reads (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                content_type VARCHAR(32) NOT NULL,
                content_id INT UNSIGNED NOT NULL,
                read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_content (user_id, content_type, content_id),
                KEY idx_user_type (user_id, content_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('mark_content_as_read')) {
    function mark_content_as_read(PDO $pdo, int $userId, string $contentType, int $contentId): bool
    {
        if ($userId <= 0 || $contentId <= 0 || empty($contentType)) {
            return false;
        }
        ensureUserContentReadsTable($pdo);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_content_reads (user_id, content_type, content_id, read_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            return $stmt->execute([$userId, $contentType, $contentId]);
        } catch (Throwable $e) {
            error_log('mark_content_as_read error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mark_all_content_as_read')) {
    function mark_all_content_as_read(PDO $pdo, int $userId, string $contentType, array $contentIds = []): bool
    {
        if ($userId <= 0 || empty($contentType)) {
            return false;
        }
        ensureUserContentReadsTable($pdo);
        try {
            if (empty($contentIds)) {
                // If no specific IDs passed, mark all eligible recent items for this type
                if ($contentType === 'announcement') {
                    $stmt = $pdo->query("SELECT id FROM announcements WHERE COALESCE(date_created, date_posted) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $contentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($contentType === 'activity') {
                    $stmt = $pdo->query("SELECT id FROM activities WHERE date >= CURDATE()");
                    $contentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($contentType === 'resource') {
                    $stmt = $pdo->query("SELECT id FROM resources WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $contentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($contentType === 'prayer_request') {
                    $stmt = $pdo->query("SELECT id FROM prayer_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
                    $contentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($contentType === 'bible_study') {
                    try {
                        $stmt = $pdo->query("SELECT id FROM bible_study_materials WHERE is_published = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                        $contentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    } catch (Throwable $e) {
                        $contentIds = [];
                    }
                }
            }

            if (empty($contentIds)) {
                return true;
            }

            $stmt = $pdo->prepare("
                INSERT INTO user_content_reads (user_id, content_type, content_id, read_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");

            $pdo->beginTransaction();
            foreach ($contentIds as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $stmt->execute([$userId, $contentType, $cid]);
                }
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('mark_all_content_as_read error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_user_notification_counts')) {
    function get_user_notification_counts(PDO $pdo, int $userId, string $userRole): array
    {
        $counts = [
            'unread_chat' => 0,
            'new_resources' => 0,
            'new_announcements' => 0,
            'new_activities' => 0,
            'new_bible_studies' => 0,
            'bible_admin_tasks' => 0,
            'new_prayers' => 0,
            'unread_contacts' => 0,
            'pending_applications' => 0,
            'pending_sports' => 0,
        ];

        if ($userId <= 0) {
            return $counts;
        }

        ensureUserContentReadsTable($pdo);

        // 1. Unread chat messages
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND status = 'sent'");
            $stmt->execute([$userId]);
            $counts['unread_chat'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification chat count error: ' . $e->getMessage());
        }

        // 2. Unread announcements (excluding those posted by this user)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM announcements a
                WHERE (a.posted_by != ? OR a.posted_by IS NULL)
                  AND COALESCE(a.date_created, a.date_posted) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND NOT EXISTS (
                      SELECT 1 FROM user_content_reads ucr
                      WHERE ucr.user_id = ? AND ucr.content_type = 'announcement' AND ucr.content_id = a.id
                  )
            ");
            $stmt->execute([$userId, $userId]);
            $counts['new_announcements'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification announcements count error: ' . $e->getMessage());
        }

        // 3. Unread activities/events (upcoming events not created by this user)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM activities act
                WHERE (act.created_by != ? OR act.created_by IS NULL)
                  AND act.date >= CURDATE()
                  AND NOT EXISTS (
                      SELECT 1 FROM user_content_reads ucr
                      WHERE ucr.user_id = ? AND ucr.content_type = 'activity' AND ucr.content_id = act.id
                  )
            ");
            $stmt->execute([$userId, $userId]);
            $counts['new_activities'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification activities count error: ' . $e->getMessage());
        }

        // 4. Unread resources (within last 30 days, not uploaded by this user)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM resources r
                WHERE (r.uploaded_by != ? OR r.uploaded_by IS NULL)
                  AND r.upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND NOT EXISTS (
                      SELECT 1 FROM user_content_reads ucr
                      WHERE ucr.user_id = ? AND ucr.content_type = 'resource' AND ucr.content_id = r.id
                  )
            ");
            $stmt->execute([$userId, $userId]);
            $counts['new_resources'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Notification resources count error: ' . $e->getMessage());
        }

        // 5. Prayer requests requiring attention / new visible prayer requests
        try {
            if ($userRole === 'admin' || $userRole === 'super_admin') {
                // Admins see new prayer requests requiring pastoral/moderation attention
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM prayer_requests pr
                    WHERE pr.user_id != ?
                      AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                      AND NOT EXISTS (
                          SELECT 1 FROM user_content_reads ucr
                          WHERE ucr.user_id = ? AND ucr.content_type = 'prayer_request' AND ucr.content_id = pr.id
                      )
                ");
                $stmt->execute([$userId, $userId]);
                $counts['new_prayers'] = (int)$stmt->fetchColumn();
            } else {
                // Regular members see public/anonymous requests only (never private)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM prayer_requests pr
                    WHERE pr.user_id != ?
                      AND pr.is_private = 0
                      AND pr.privacy_level IN ('public', 'anonymous')
                      AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                      AND NOT EXISTS (
                          SELECT 1 FROM user_content_reads ucr
                          WHERE ucr.user_id = ? AND ucr.content_type = 'prayer_request' AND ucr.content_id = pr.id
                      )
                ");
                $stmt->execute([$userId, $userId]);
                $counts['new_prayers'] = (int)$stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            error_log('Notification prayer count error: ' . $e->getMessage());
        }

        // 6. Bible study updates & admin tasks
        try {
            // New published materials for members
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bible_study_materials m
                WHERE m.is_published = 1
                  AND m.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  AND NOT EXISTS (
                      SELECT 1 FROM user_content_reads ucr
                      WHERE ucr.user_id = ? AND ucr.content_type = 'bible_study' AND ucr.content_id = m.id
                  )
            ");
            $stmt->execute([$userId]);
            $counts['new_bible_studies'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Table may not exist yet in test/minimal setup
            $counts['new_bible_studies'] = 0;
        }

        if ($userRole === 'super_admin') {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM bible_study_materials WHERE is_published = 0");
                $counts['bible_admin_tasks'] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $counts['bible_admin_tasks'] = 0;
            }
        }

        // 7. Admin-specific categories
        if ($userRole === 'admin' || $userRole === 'super_admin') {
            // Unread contact messages
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread' AND deleted_at IS NULL");
                $counts['unread_contacts'] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('Notification contacts count error: ' . $e->getMessage());
            }

            // Pending partner/missionary applications
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0 AND category IN ('partner', 'missionary')");
                $counts['pending_applications'] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('Notification applications count error: ' . $e->getMessage());
            }

            // Pending sports applications
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM sports_applications WHERE status = 'pending'");
                $counts['pending_sports'] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                // Table might not exist yet
                $counts['pending_sports'] = 0;
            }
        }

        return $counts;
    }
}
