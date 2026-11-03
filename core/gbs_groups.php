<?php
/**
 * JDM Kenya - Group Bible Study (GBS) Authorization & Management Helpers
 *
 * Establishes one consistent, server-side authorization and lifecycle model
 * for campus Group Bible Study (GBS) leadership and group management, while
 * keeping campus GBS strictly separate from General Monday Bible Study.
 */

if (!function_exists('reconcileGbsLeaders')) {
    /**
     * Reconcile existing GBS leadership records across `users`, `leaders`,
     * `gbs_groups`, and `gbs_members` idempotently without losing existing data.
     */
    function reconcileGbsLeaders(PDO $pdo, bool $includeExistingGroupOwners = false): void
    {
        try {
            // 1. Ensure any user with an active 'gbs_leader' appointment in `leaders` has users.is_gbs_leader = 1
            $pdo->exec("
                UPDATE users u
                INNER JOIN leaders l ON l.user_id = u.id AND l.leader_type = 'gbs_leader'
                SET u.is_gbs_leader = 1
                WHERE u.is_gbs_leader = 0
            ");

            // 2. Optionally (during migration/setup) ensure existing group owners with is_gbs_leader = 1
            //    also have a 'gbs_leader' record in `leaders`.
            if ($includeExistingGroupOwners) {
                $pdo->exec("
                    UPDATE users u
                    INNER JOIN gbs_groups g ON g.leader_id = u.id
                    SET u.is_gbs_leader = 1
                    WHERE u.is_gbs_leader = 0
                ");
            }

            // 3. Ensure every user with users.is_gbs_leader = 1 has a corresponding row in `leaders`
            $pdo->exec("
                INSERT INTO leaders (user_id, leader_type, campus_name, created_by)
                SELECT u.id, 'gbs_leader', NULL, u.id
                FROM users u
                WHERE u.is_gbs_leader = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM leaders l
                      WHERE l.user_id = u.id AND l.leader_type = 'gbs_leader'
                  )
            ");

            // 4. Remove duplicate 'gbs_leader' rows for the same user in `leaders` (keep lowest id)
            $pdo->exec("
                DELETE l1 FROM leaders l1
                INNER JOIN leaders l2
                    ON l1.user_id = l2.user_id
                   AND l1.leader_type = 'gbs_leader'
                   AND l2.leader_type = 'gbs_leader'
                   AND l1.id > l2.id
            ");

            // 5. Ensure every GBS group's leader_id is recorded in `gbs_members` as 'leader'
            $pdo->exec("
                INSERT INTO gbs_members (gbs_id, user_id, role, joined_at)
                SELECT g.id, g.leader_id, 'leader', g.created_at
                FROM gbs_groups g
                INNER JOIN users u ON u.id = g.leader_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM gbs_members gm
                    WHERE gm.gbs_id = g.id AND gm.user_id = g.leader_id
                )
            ");
            $pdo->exec("
                UPDATE gbs_members gm
                INNER JOIN gbs_groups g ON g.id = gm.gbs_id AND g.leader_id = gm.user_id
                SET gm.role = 'leader'
                WHERE gm.role <> 'leader'
            ");
        } catch (Throwable $e) {
            error_log('[gbs_groups] reconcileGbsLeaders notice: ' . $e->getMessage());
        }
    }
}

if (!function_exists('isEligibleGbsMember')) {
    /**
     * Check whether a user ID belongs to an active, eligible registered JDM member.
     */
    function isEligibleGbsMember(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT id, role, category, is_approved, account_status
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                SELECT id, role, category, is_approved
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $u['account_status'] = 'active';
            }
        }

        if (!$u) {
            return false;
        }
        if (!in_array($u['role'] ?? '', ['member', 'admin', 'super_admin'], true)) {
            return false;
        }
        $status = $u['account_status'] ?? 'active';
        if ($status !== null && $status !== '' && $status !== 'active') {
            return false;
        }
        if (in_array($u['category'] ?? '', ['partner', 'missionary'], true) && empty($u['is_approved'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('isUserGbsLeader')) {
    /**
     * Authoritative server-side check for whether a user holds an active GBS Leader appointment.
     * Reconciles `leaders` (leader_type = 'gbs_leader') and `users.is_gbs_leader`.
     * Never relies on stale session variables or General Monday Bible Study assignments.
     */
    function isUserGbsLeader(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $isLeader = false;
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.is_gbs_leader, u.account_status,
                       EXISTS(
                           SELECT 1 FROM leaders l
                           WHERE l.user_id = u.id AND l.leader_type = 'gbs_leader'
                       ) AS has_leader_row
                FROM users u
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $status = $row['account_status'] ?? 'active';
            if ($status !== null && $status !== '' && $status !== 'active') {
                return false;
            }

            $flag = !empty($row['is_gbs_leader']);
            $hasLeaderRow = !empty($row['has_leader_row']);
            $isLeader = $flag || $hasLeaderRow;

            // Keep both appointment stores synchronized if one was set independently
            if ($isLeader && !$flag) {
                try {
                    $pdo->prepare('UPDATE users SET is_gbs_leader = 1 WHERE id = ?')->execute([$userId]);
                } catch (Throwable $ignored) {}
            } elseif ($isLeader && !$hasLeaderRow) {
                try {
                    $pdo->prepare("INSERT INTO leaders (user_id, leader_type, campus_name, created_by) VALUES (?, 'gbs_leader', NULL, ?)")
                        ->execute([$userId, $userId]);
                } catch (Throwable $ignored) {}
            }
        } catch (PDOException $e) {
            // Fallback for older schemas where `is_gbs_leader` or `account_status` may not exist
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader'");
                $stmt->execute([$userId]);
                $isLeader = ((int)$stmt->fetchColumn() > 0);
            } catch (PDOException $ignored) {}

            if (!$isLeader) {
                try {
                    $stmt = $pdo->prepare('SELECT is_gbs_leader FROM users WHERE id = ? LIMIT 1');
                    $stmt->execute([$userId]);
                    $isLeader = (bool)$stmt->fetchColumn();
                } catch (PDOException $ignored) {}
            }

            if (!$isLeader) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gbs_members WHERE user_id = ? AND role = 'leader'");
                    $stmt->execute([$userId]);
                    $isLeader = ((int)$stmt->fetchColumn() > 0);
                } catch (PDOException $ignored) {}
            }
        }

        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['is_gbs_leader'] = $isLeader ? 1 : 0;
        }

        return $isLeader;
    }
}

if (!function_exists('appointGbsLeader')) {
    /**
     * Appoint an existing registered user as a Campus GBS Leader.
     * Synchronizes both `users.is_gbs_leader` and `leaders` (`leader_type = 'gbs_leader'`)
     * without promoting the user to admin or super_admin.
     */
    function appointGbsLeader(PDO $pdo, int $userId, int $appointedBy = 0, ?string $campusName = null): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID for GBS Leader appointment.');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('Target user does not exist.');
        }

        $actorId = $appointedBy > 0 ? $appointedBy : $userId;
        $campus = ($campusName !== null && trim($campusName) !== '') ? trim($campusName) : null;

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            try {
                $pdo->prepare('UPDATE users SET is_gbs_leader = 1 WHERE id = ?')->execute([$userId]);
            } catch (PDOException $ignored) {}

            $stmtCheck = $pdo->prepare("SELECT id, campus_name FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader' ORDER BY id ASC");
            $stmtCheck->execute([$userId]);
            $existingRows = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

            if (empty($existingRows)) {
                $stmtIns = $pdo->prepare("INSERT INTO leaders (user_id, leader_type, campus_name, created_by) VALUES (?, 'gbs_leader', ?, ?)");
                $stmtIns->execute([$userId, $campus, $actorId]);
            } else {
                $keepId = (int)$existingRows[0]['id'];
                if ($campus !== null) {
                    $pdo->prepare('UPDATE leaders SET campus_name = ? WHERE id = ?')->execute([$campus, $keepId]);
                }
                if (count($existingRows) > 1) {
                    $pdo->prepare("DELETE FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader' AND id <> ?")
                        ->execute([$userId, $keepId]);
                }
            }

            // Ensure any existing groups owned by this leader have their leader membership role intact
            $pdo->prepare("
                INSERT INTO gbs_members (gbs_id, user_id, role, joined_at)
                SELECT g.id, g.leader_id, 'leader', g.created_at
                FROM gbs_groups g
                WHERE g.leader_id = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM gbs_members gm
                      WHERE gm.gbs_id = g.id AND gm.user_id = g.leader_id
                  )
            ")->execute([$userId]);

            $pdo->prepare("
                UPDATE gbs_members gm
                INNER JOIN gbs_groups g ON g.id = gm.gbs_id
                SET gm.role = 'leader'
                WHERE g.leader_id = ? AND gm.user_id = ?
            ")->execute([$userId, $userId]);

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['is_gbs_leader'] = 1;
        }
    }
}

if (!function_exists('revokeGbsLeader')) {
    /**
     * Revoke a user's GBS Leader appointment across both `leaders` and `users.is_gbs_leader`
     * without deleting the user's account or destroying existing group or membership records.
     */
    function revokeGbsLeader(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID for GBS Leader removal.');
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            try {
                $pdo->prepare('UPDATE users SET is_gbs_leader = 0 WHERE id = ?')->execute([$userId]);
            } catch (PDOException $ignored) {}

            try {
                $pdo->prepare("DELETE FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader'")->execute([$userId]);
            } catch (PDOException $ignored) {}

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['is_gbs_leader'] = 0;
            if (($_SESSION['active_dashboard'] ?? '') === 'gbs') {
                $_SESSION['active_dashboard'] = 'member';
            }
        }
    }
}

if (!function_exists('canUserCreateGbsGroup')) {
    /**
     * Determine whether a user can create a GBS group on the server.
     */
    function canUserCreateGbsGroup(PDO $pdo, int $userId, ?string $userRole = null): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $role = $userRole;
        try {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $dbRole = $stmt->fetchColumn();
            if ($dbRole !== false) {
                $role = (string)$dbRole;
            }
        } catch (Throwable $ignored) {}

        if ($role === 'super_admin') {
            return true;
        }
        return isUserGbsLeader($pdo, $userId);
    }
}

if (!function_exists('getGbsGroupMembership')) {
    /**
     * Retrieve group details and the user's verified access/leadership status for that group.
     *
     * Returns null if the group does not exist or the user has no access to view it.
     */
    function getGbsGroupMembership(PDO $pdo, int $gbsId, int $userId, ?string $userRole = null): ?array
    {
        if ($gbsId <= 0 || $userId <= 0) {
            return null;
        }

        $role = $userRole;
        try {
            $stmtRole = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmtRole->execute([$userId]);
            $dbRole = $stmtRole->fetchColumn();
            if ($dbRole !== false) {
                $role = (string)$dbRole;
            }
        } catch (Throwable $ignored) {}

        $isSuperAdmin = ($role === 'super_admin');

        if ($isSuperAdmin) {
            $stmt = $pdo->prepare("
                SELECT 'leader' AS role, g.id, g.name, g.slogan, g.pfp_path, g.leader_id, g.chat_restricted, g.created_at
                FROM gbs_groups g
                WHERE g.id = ?
                LIMIT 1
            ");
            $stmt->execute([$gbsId]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) {
                return null;
            }
            $group['is_leader'] = true;
            $group['can_manage'] = true;
            $group['can_delete'] = true;
            return $group;
        }

        $stmt = $pdo->prepare("
            SELECT gm.role, g.id, g.name, g.slogan, g.pfp_path, g.leader_id, g.chat_restricted, g.created_at
            FROM gbs_groups g
            LEFT JOIN gbs_members gm ON gm.gbs_id = g.id AND gm.user_id = ?
            WHERE g.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $gbsId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            return null;
        }

        $isGroupOwner = ((int)$group['leader_id'] === $userId);
        $hasMemberRow = !empty($group['role']);

        // If user is neither a group member nor the group's owner, deny access
        if (!$hasMemberRow && !$isGroupOwner) {
            return null;
        }

        $holdsActiveAppointment = isUserGbsLeader($pdo, $userId);

        // If the user is the owner and holds an active GBS Leader appointment, ensure membership row exists
        if ($isGroupOwner && $holdsActiveAppointment && $group['role'] !== 'leader') {
            try {
                $pdo->prepare("
                    INSERT INTO gbs_members (gbs_id, user_id, role, joined_at)
                    VALUES (?, ?, 'leader', NOW())
                    ON DUPLICATE KEY UPDATE role = 'leader'
                ")->execute([$gbsId, $userId]);
                $group['role'] = 'leader';
            } catch (Throwable $ignored) {}
        }

        $isAuthorizedLeader = $holdsActiveAppointment && ($isGroupOwner || $group['role'] === 'leader');
        $group['role'] = $isAuthorizedLeader ? 'leader' : 'member';
        $group['is_leader'] = $isAuthorizedLeader;
        $group['can_manage'] = $isAuthorizedLeader;
        $group['can_delete'] = $isAuthorizedLeader && $isGroupOwner;

        return $group;
    }
}

if (!function_exists('canManageGbsGroup')) {
    /**
     * Server-side check whether the given user can manage a specific GBS group.
     */
    function canManageGbsGroup(PDO $pdo, int $gbsId, int $userId, ?string $userRole = null): bool
    {
        $membership = getGbsGroupMembership($pdo, $gbsId, $userId, $userRole);
        return !empty($membership['can_manage']);
    }
}

if (!function_exists('createGbsGroup')) {
    /**
     * Create a new GBS group and register its leader in `gbs_members` transactionally.
     */
    function createGbsGroup(
        PDO $pdo,
        int $actorUserId,
        string $actorRole,
        string $groupName,
        ?string $groupSlogan = null,
        ?int $requestedLeaderId = null,
        ?string $pfpPath = null
    ): int {
        if (!canUserCreateGbsGroup($pdo, $actorUserId, $actorRole)) {
            throw new RuntimeException('You are not authorized to create a GBS group.');
        }

        $groupName = trim($groupName);
        $groupSlogan = $groupSlogan !== null ? trim($groupSlogan) : null;
        if ($groupSlogan === '') {
            $groupSlogan = null;
        }

        if ($groupName === '') {
            throw new InvalidArgumentException('Group name is required.');
        }

        // Verify actor's role from DB
        $stmtRole = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmtRole->execute([$actorUserId]);
        $dbRole = (string)($stmtRole->fetchColumn() ?: $actorRole);

        if ($dbRole === 'super_admin') {
            $leaderId = ($requestedLeaderId !== null && $requestedLeaderId > 0) ? $requestedLeaderId : $actorUserId;
        } else {
            if ($requestedLeaderId !== null && $requestedLeaderId > 0 && $requestedLeaderId !== $actorUserId) {
                throw new RuntimeException('You can only create a GBS group with yourself as the leader.');
            }
            $leaderId = $actorUserId;
        }

        if (!isEligibleGbsMember($pdo, $leaderId)) {
            throw new InvalidArgumentException('Selected leader is not an eligible registered member.');
        }

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO gbs_groups (name, slogan, leader_id, pfp_path, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$groupName, $groupSlogan, $leaderId, $pfpPath]);
            $gbsId = (int)$pdo->lastInsertId();

            $stmtMember = $pdo->prepare("
                INSERT INTO gbs_members (gbs_id, user_id, role, joined_at)
                VALUES (?, ?, 'leader', NOW())
                ON DUPLICATE KEY UPDATE role = 'leader'
            ");
            $stmtMember->execute([$gbsId, $leaderId]);

            // Ensure the leader holds an active GBS Leader appointment
            appointGbsLeader($pdo, $leaderId, $actorUserId);

            if ($leaderId !== $actorUserId) {
                try {
                    $expiry = time() + (7 * 24 * 60 * 60);
                    $notification = "🎉 Congratulations! You have been appointed as the GBS Leader for \"{$groupName}\".\n\n"
                        . "Switch to your GBS Leader dashboard: gbs_dashboard.php?set_dashboard=gbs&exp=" . $expiry . "\n"
                        . "Open your GBS group: gbs_group.php?id={$gbsId}";

                    $stmtNotif = $pdo->prepare("
                        INSERT INTO messages (sender_id, receiver_id, message_text, status)
                        VALUES (?, ?, ?, 'sent')
                    ");
                    $stmtNotif->execute([$actorUserId, $leaderId, $notification]);
                } catch (Throwable $ignored) {}
            }

            if ($ownTx) {
                $pdo->commit();
            }

            return $gbsId;
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('getEligibleGbsUsersNotInGroup')) {
    /**
     * Return eligible registered JDM members who are not yet in the specified GBS group.
     * Excludes sensitive columns (passwords, tokens, etc.).
     */
    function getEligibleGbsUsersNotInGroup(PDO $pdo, int $gbsId): array
    {
        if ($gbsId <= 0) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.category, u.role
                FROM users u
                WHERE u.role IN ('member', 'admin', 'super_admin')
                  AND (u.account_status IS NULL OR u.account_status = 'active')
                  AND (u.category NOT IN ('partner', 'missionary') OR u.is_approved = 1)
                  AND u.id NOT IN (
                      SELECT gm.user_id FROM gbs_members gm WHERE gm.gbs_id = ?
                  )
                ORDER BY u.name ASC
            ");
            $stmt->execute([$gbsId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.category, u.role
                FROM users u
                WHERE u.id NOT IN (
                    SELECT gm.user_id FROM gbs_members gm WHERE gm.gbs_id = ?
                )
                ORDER BY u.name ASC
            ");
            $stmt->execute([$gbsId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

if (!function_exists('addGbsMember')) {
    /**
     * Add an eligible registered member to a GBS group with full server-side validation.
     */
    function addGbsMember(PDO $pdo, int $gbsId, int $actorUserId, string $actorRole, int $memberUserId): void
    {
        $membership = getGbsGroupMembership($pdo, $gbsId, $actorUserId, $actorRole);
        if (!$membership || empty($membership['can_manage'])) {
            throw new RuntimeException('You are not authorized to manage this GBS group.');
        }

        if ($memberUserId <= 0 || !isEligibleGbsMember($pdo, $memberUserId)) {
            throw new InvalidArgumentException('Please select a valid, active registered member.');
        }

        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM gbs_members WHERE gbs_id = ? AND user_id = ?');
        $checkStmt->execute([$gbsId, $memberUserId]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            throw new RuntimeException('User is already a member of this group.');
        }

        $stmt = $pdo->prepare("INSERT INTO gbs_members (gbs_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
        $stmt->execute([$gbsId, $memberUserId]);

        try {
            $groupName = $membership['name'] ?? 'Group Bible Study';
            $notifText = "Welcome to **{$groupName}**! 👋\n\nI'm glad to have you join our Group Bible Study. We look forward to growing together in faith.\n\nYou can access our group page here:\ngbs_group.php?id={$gbsId}";
            $stmtNotif = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, ?, 'sent')");
            $stmtNotif->execute([$actorUserId, $memberUserId, $notifText]);
        } catch (Throwable $e) {
            error_log('Failed to send GBS welcome message: ' . $e->getMessage());
        }
    }
}

if (!function_exists('removeGbsMember')) {
    /**
     * Remove an ordinary member from a GBS group while protecting the group leader.
     */
    function removeGbsMember(PDO $pdo, int $gbsId, int $actorUserId, string $actorRole, int $memberUserId): void
    {
        $membership = getGbsGroupMembership($pdo, $gbsId, $actorUserId, $actorRole);
        if (!$membership || empty($membership['can_manage'])) {
            throw new RuntimeException('You are not authorized to manage this GBS group.');
        }

        if ($memberUserId <= 0) {
            throw new InvalidArgumentException('Invalid member specified.');
        }

        if ($memberUserId === (int)$membership['leader_id']) {
            throw new RuntimeException('Cannot remove the group leader.');
        }

        $stmtCheck = $pdo->prepare('SELECT role FROM gbs_members WHERE gbs_id = ? AND user_id = ? LIMIT 1');
        $stmtCheck->execute([$gbsId, $memberUserId]);
        $targetRole = $stmtCheck->fetchColumn();

        if ($targetRole === false) {
            throw new RuntimeException('User is not a member of this group.');
        }
        if ($targetRole === 'leader') {
            throw new RuntimeException('Cannot remove the group leader.');
        }

        $stmt = $pdo->prepare("DELETE FROM gbs_members WHERE gbs_id = ? AND user_id = ? AND role <> 'leader'");
        $stmt->execute([$gbsId, $memberUserId]);
    }
}

if (!function_exists('updateGbsGroup')) {
    /**
     * Update GBS group name, slogan, and optional profile image.
     */
    function updateGbsGroup(
        PDO $pdo,
        int $gbsId,
        int $actorUserId,
        string $actorRole,
        string $groupName,
        ?string $groupSlogan = null,
        ?string $pfpPath = null
    ): void {
        $membership = getGbsGroupMembership($pdo, $gbsId, $actorUserId, $actorRole);
        if (!$membership || empty($membership['can_manage'])) {
            throw new RuntimeException('You are not authorized to update this GBS group.');
        }

        $groupName = trim($groupName);
        if ($groupName === '') {
            throw new InvalidArgumentException('Group name is required.');
        }
        $groupSlogan = $groupSlogan !== null ? trim($groupSlogan) : null;
        if ($groupSlogan === '') {
            $groupSlogan = null;
        }

        if ($pfpPath !== null && $pfpPath !== '') {
            $stmt = $pdo->prepare('UPDATE gbs_groups SET name = ?, slogan = ?, pfp_path = ? WHERE id = ?');
            $stmt->execute([$groupName, $groupSlogan, $pfpPath, $gbsId]);
        } else {
            $stmt = $pdo->prepare('UPDATE gbs_groups SET name = ?, slogan = ? WHERE id = ?');
            $stmt->execute([$groupName, $groupSlogan, $gbsId]);
        }
    }
}

if (!function_exists('deleteGbsGroup')) {
    function deleteGbsGroup(PDO $pdo, int $gbsId): void
    {
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('SELECT leader_id FROM gbs_groups WHERE id = ? LIMIT 1');
            $stmt->execute([$gbsId]);
            $leaderId = (int)($stmt->fetchColumn() ?: 0);

            $pdo->prepare('DELETE FROM gbs_messages WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_resources WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_announcements WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_members WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_groups WHERE id = ?')->execute([$gbsId]);

            // Only clear is_gbs_leader if the leader has no remaining groups AND no active appointment in `leaders`
            if ($leaderId > 0) {
                $stmtGroups = $pdo->prepare('SELECT COUNT(*) FROM gbs_groups WHERE leader_id = ?');
                $stmtGroups->execute([$leaderId]);
                $remainingGroups = (int)$stmtGroups->fetchColumn();

                $hasAppointment = false;
                try {
                    $stmtAppt = $pdo->prepare("SELECT COUNT(*) FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader'");
                    $stmtAppt->execute([$leaderId]);
                    $hasAppointment = ((int)$stmtAppt->fetchColumn() > 0);
                } catch (Throwable $ignored) {}

                if ($remainingGroups === 0 && !$hasAppointment) {
                    try {
                        $pdo->prepare('UPDATE users SET is_gbs_leader = 0 WHERE id = ?')->execute([$leaderId]);
                    } catch (Throwable $ignored) {}
                }
            }

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('getAppointedGbsLeadersWithGroups')) {
    /**
     * Return all appointed GBS Leaders and the GBS groups (with member counts) they manage.
     */
    function getAppointedGbsLeadersWithGroups(PDO $pdo): array
    {
        try {
            reconcileGbsLeaders($pdo);

            $stmt = $pdo->query("
                SELECT u.id, u.name, u.email, u.whatsapp_phone, u.role, u.category, u.is_gbs_leader,
                       MAX(l.campus_name) AS campus_name,
                       MAX(l.created_at) AS appointed_at
                FROM users u
                LEFT JOIN leaders l ON l.user_id = u.id AND l.leader_type = 'gbs_leader'
                WHERE (u.is_gbs_leader = 1 OR l.id IS NOT NULL)
                  AND (u.account_status IS NULL OR u.account_status <> 'deleted')
                GROUP BY u.id, u.name, u.email, u.whatsapp_phone, u.role, u.category, u.is_gbs_leader
                ORDER BY appointed_at DESC, u.name ASC
            ");
            $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($leaders)) {
                return [];
            }

            $stmtGroups = $pdo->query("
                SELECT g.id, g.name, g.slogan, g.leader_id, g.created_at,
                       (SELECT COUNT(*) FROM gbs_members gm WHERE gm.gbs_id = g.id) AS member_count
                FROM gbs_groups g
                ORDER BY g.created_at DESC
            ");
            $allGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

            $groupsByLeader = [];
            foreach ($allGroups as $grp) {
                $lid = (int)$grp['leader_id'];
                $groupsByLeader[$lid][] = $grp;
            }

            foreach ($leaders as &$leader) {
                $lid = (int)$leader['id'];
                $leader['groups'] = $groupsByLeader[$lid] ?? [];
            }
            unset($leader);

            return $leaders;
        } catch (Throwable $e) {
            error_log('[gbs_groups] getAppointedGbsLeadersWithGroups error: ' . $e->getMessage());
            return [];
        }
    }
}
