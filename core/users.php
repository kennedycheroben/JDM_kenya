<?php
/**
 * Core user utilities
 *
 * Provides safe lifecycle management:
 * - Dependency inspection (identifying restrictive foreign keys before deletion)
 * - Account status transitions (active, disabled, suspended, deleted)
 * - Safe transactional deletion (cleaning dependent assignments without violating FK constraints)
 */

if (!function_exists('getUserAccountStatus')) {
    /**
     * Retrieve the current account status for a given user ID.
     */
    function getUserAccountStatus(PDO $pdo, int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT account_status FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string)$val : null;
        } catch (Throwable $e) {
            error_log('getUserAccountStatus error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('setUserAccountStatus')) {
    /**
     * Update account status with strict validation ('active', 'disabled', 'suspended', 'deleted').
     */
    function setUserAccountStatus(PDO $pdo, int $userId, string $status): bool
    {
        $allowed = ['active', 'disabled', 'suspended', 'deleted'];
        if ($userId <= 0 || !in_array($status, $allowed, true)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?');
            return $stmt->execute([$status, $userId]);
        } catch (Throwable $e) {
            error_log('setUserAccountStatus error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('checkUserDependencies')) {
    /**
     * Inspect all tables referencing users(id) to determine dependencies.
     * Distinguishes between blocking leadership roles, participation history, and ownership.
     *
     * @return array{
     *   userId: int,
     *   can_delete_safely: bool,
     *   blocking_reasons: string[],
     *   counts: array<string, int>
     * }
     */
    function checkUserDependencies(PDO $pdo, int $userId): array
    {
        $deps = [
            'userId' => $userId,
            'can_delete_safely' => true,
            'blocking_reasons' => [],
            'counts' => [
                'bible_study_leader_assignments' => 0,
                'bible_study_registrations' => 0,
                'sports_admin_assignments' => 0,
                'sports_applications' => 0,
                'sports_members' => 0,
                'leaders' => 0,
                'gbs_groups_led' => 0,
                'gbs_memberships' => 0,
                'messages' => 0,
                'prayer_requests' => 0,
                'activities_created' => 0,
                'announcements_posted' => 0,
            ]
        ];

        if ($userId <= 0) {
            $deps['can_delete_safely'] = false;
            $deps['blocking_reasons'][] = 'Invalid user ID.';
            return $deps;
        }

        // 1. Bible Study leader assignments (RESTRICT)
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM bible_study_leader_assignments WHERE leader_user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['bible_study_leader_assignments'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User is assigned as a leader in %d Bible study session(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 2. Bible study registrations (RESTRICT)
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM bible_study_registrations WHERE member_user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['bible_study_registrations'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User has %d active Bible study registration(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 3. Sports Admin assignments (RESTRICT)
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sports_admin_assignments WHERE user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['sports_admin_assignments'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User has %d Sports Ministry admin assignment(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 4. Sports applications (RESTRICT)
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sports_applications WHERE user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['sports_applications'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User has %d Sports Ministry application(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 5. Sports members (RESTRICT)
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sports_members WHERE user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['sports_members'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User has %d Sports team membership(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 6. Ministry Leaders table
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leaders WHERE user_id = ?');
            $stmt->execute([$userId]);
            $cnt = (int)$stmt->fetchColumn();
            $deps['counts']['leaders'] = $cnt;
            if ($cnt > 0) {
                $deps['blocking_reasons'][] = sprintf('User has %d general ministry leadership appointment(s).', $cnt);
            }
        } catch (Throwable $e) {}

        // 7. GBS groups led
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM gbs_groups WHERE leader_id = ?');
            $stmt->execute([$userId]);
            $deps['counts']['gbs_groups_led'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        // 8. Messages
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?');
            $stmt->execute([$userId, $userId]);
            $deps['counts']['messages'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        // 9. Prayer requests
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM prayer_requests WHERE user_id = ?');
            $stmt->execute([$userId]);
            $deps['counts']['prayer_requests'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        // 10. Activities created
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM activities WHERE created_by = ?');
            $stmt->execute([$userId]);
            $deps['counts']['activities_created'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        // 11. Announcements posted
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM announcements WHERE posted_by = ?');
            $stmt->execute([$userId]);
            $deps['counts']['announcements_posted'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {}

        if (!empty($deps['blocking_reasons'])) {
            $deps['can_delete_safely'] = false;
        }

        return $deps;
    }
}

if (!function_exists('deleteUser')) {
    /**
     * Safely delete a user and related data in a transaction.
     *
     * @param PDO $pdo
     * @param int $userId
     * @param bool $cleanAssignments If true, removes restrictive leader/admin assignments
     *                               in the transaction so foreign keys do not fail.
     *                               If false, throws an exception if restrictive rows exist.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    function deleteUser(PDO $pdo, int $userId, bool $cleanAssignments = true): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID specified for deletion.');
        }

        // Verify user existence and role
        $stmtUser = $pdo->prepare('SELECT id, role, pfp_path FROM users WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) {
            throw new InvalidArgumentException("User with ID {$userId} does not exist.");
        }
        if (($userRow['role'] ?? '') === 'super_admin') {
            throw new InvalidArgumentException('Super administrator accounts cannot be deleted.');
        }

        $avatarPath = $userRow['pfp_path'] ?? null;

        // Check dependencies if cleanAssignments is false
        if (!$cleanAssignments) {
            $deps = checkUserDependencies($pdo, $userId);
            if (!empty($deps['blocking_reasons'])) {
                throw new RuntimeException('Cannot delete user with active dependencies: ' . implode(' ', $deps['blocking_reasons']));
            }
        }

        $pdo->beginTransaction();

        try {
            // Ensure gbs helper is available
            if (!function_exists('deleteGbsGroup') && file_exists(__DIR__ . '/gbs_groups.php')) {
                require_once __DIR__ . '/gbs_groups.php';
            }

            // 1. Delete any GBS groups led by this user
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

            // 2. Remove restrictive foreign key child records
            $pdo->prepare('DELETE FROM bible_study_leader_assignments WHERE leader_user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM bible_study_registrations WHERE member_user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM sports_admin_assignments WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM sports_applications WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM sports_members WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM leaders WHERE user_id = ?')->execute([$userId]);

            // 3. Remove direct user-owned records
            try {
                $pdo->prepare('DELETE FROM user_content_reads WHERE user_id = ?')->execute([$userId]);
            } catch (Throwable $e) {}

            $pdo->prepare('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?')->execute([$userId, $userId]);
            $pdo->prepare('DELETE FROM video_progress WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM prayer_requests WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM birthday_celebrations WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM birthday_countdowns WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM gbs_members WHERE user_id = ?')->execute([$userId]);

            // 4. Finally delete the user row (FKs will cascade or SET NULL per schema)
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

            $pdo->commit();

            // 5. Clean up avatar file safely if it exists inside uploads
            if (!empty($avatarPath) && is_string($avatarPath)) {
                $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(__DIR__) . '/uploads/');
                $realUploadDir = realpath($uploadDir);
                $filePath = $uploadDir . ltrim($avatarPath, '/');
                $realFilePath = realpath($filePath);
                if ($realFilePath && $realUploadDir && strpos($realFilePath, $realUploadDir) === 0 && file_exists($realFilePath)) {
                    @unlink($realFilePath);
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('deleteUser failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
