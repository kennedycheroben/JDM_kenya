<?php
/**
 * Core user utilities
 */

if (!function_exists('deleteUser')) {
    /**
     * Safely delete a user and related data in a transaction.
     * Relies on gbs_groups::deleteGbsGroup when available to clean group data.
     */
    function deleteUser(PDO $pdo, int $userId): void
    {
        $pdo->beginTransaction();

        try {
            // Ensure gbs helper is available
            if (!function_exists('deleteGbsGroup') && file_exists(__DIR__ . '/gbs_groups.php')) {
                require_once __DIR__ . '/gbs_groups.php';
            }

            // Delete any GBS groups led by this user (clean cascade)
            $stmt = $pdo->prepare('SELECT id FROM gbs_groups WHERE leader_id = ?');
            $stmt->execute([$userId]);
            $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($groups as $gId) {
                if (function_exists('deleteGbsGroup')) {
                    deleteGbsGroup($pdo, (int)$gId);
                } else {
                    $pdo->prepare('DELETE FROM gbs_messages WHERE gbs_id = ?')->execute([$gId]);
                    $pdo->prepare('DELETE FROM gbs_resources WHERE gbs_id = ?')->execute([$gId]);
                    $pdo->prepare('DELETE FROM gbs_announcements WHERE gbs_id = ?')->execute([$gId]);
                    $pdo->prepare('DELETE FROM gbs_members WHERE gbs_id = ?')->execute([$gId]);
                    $pdo->prepare('DELETE FROM gbs_groups WHERE id = ?')->execute([$gId]);
                }
            }

            // Remove direct user-owned records that might block deletion
            $pdo->prepare('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?')->execute([$userId, $userId]);
            $pdo->prepare('DELETE FROM video_progress WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM prayer_requests WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM birthday_celebrations WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM birthday_countdowns WHERE user_id = ?')->execute([$userId]);

            // Ensure membership rows are removed
            $pdo->prepare('DELETE FROM gbs_members WHERE user_id = ?')->execute([$userId]);

            // Finally delete the user row (FKs will cascade or set NULL per schema)
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('deleteUser failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
