<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for GBS Leader Appointment, Dashboard Access,
 * Group Creation, Member Management, Revocation, Super Admin Oversight,
 * and Bible Study Separation (Phases 1–7).
 */

require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/gbs_groups.php';
require_once __DIR__ . '/../core/bible_studies.php';

$failures = [];

function assertTrue(bool $condition, string $label, array &$failures): void
{
    if ($condition) {
        echo "[PASS] {$label}\n";
    } else {
        echo "[FAIL] {$label}\n";
        $failures[] = $label;
    }
}

$root = dirname(__DIR__);
$passwordHash = password_hash('TestPass123!', PASSWORD_DEFAULT);
$suffix = bin2hex(random_bytes(4));

$createdUserIds = [];
$createdGroupIds = [];

try {
    // Create temporary test users
    $insertUser = $pdo->prepare("
        INSERT INTO users (name, email, whatsapp_phone, password, role, category, is_approved, account_status, is_gbs_leader, created_at)
        VALUES (?, ?, '+254700000000', ?, ?, 'student', 1, 'active', 0, NOW())
    ");

    // 1. Ordinary Member to be appointed GBS Leader
    $leaderEmail = "gbs_leader_{$suffix}@example.com";
    $insertUser->execute(["Test GBS Leader {$suffix}", $leaderEmail, $passwordHash, 'member']);
    $leaderUserId = (int)$pdo->lastInsertId();
    $createdUserIds[] = $leaderUserId;

    // 2. Ordinary Registered Member to be added to the GBS Group
    $memberEmail = "gbs_member_{$suffix}@example.com";
    $insertUser->execute(["Test GBS Member {$suffix}", $memberEmail, $passwordHash, 'member']);
    $ordinaryMemberId = (int)$pdo->lastInsertId();
    $createdUserIds[] = $ordinaryMemberId;

    // 3. Unrelated Member / Other GBS Leader
    $otherEmail = "gbs_other_{$suffix}@example.com";
    $insertUser->execute(["Test Other User {$suffix}", $otherEmail, $passwordHash, 'member']);
    $otherUserId = (int)$pdo->lastInsertId();
    $createdUserIds[] = $otherUserId;

    // 4. General Monday Bible Study Leader (separate ministry)
    $mondayLeaderEmail = "monday_bs_{$suffix}@example.com";
    $insertUser->execute(["Monday BS Leader {$suffix}", $mondayLeaderEmail, $passwordHash, 'member']);
    $mondayLeaderId = (int)$pdo->lastInsertId();
    $createdUserIds[] = $mondayLeaderId;

    // 5. Super Admin
    $superAdminEmail = "super_admin_{$suffix}@example.com";
    $insertUser->execute(["Test Super Admin {$suffix}", $superAdminEmail, $passwordHash, 'super_admin']);
    $superAdminId = (int)$pdo->lastInsertId();
    $createdUserIds[] = $superAdminId;

    // =========================================================================
    // SCENARIO 1: Super Admin appoints an ordinary member as GBS Leader
    // =========================================================================
    assertTrue(
        !isUserGbsLeader($pdo, $leaderUserId),
        'Scenario 1a: Newly registered member is not initially a GBS Leader',
        $failures
    );

    appointGbsLeader($pdo, $leaderUserId, $superAdminId, 'Kenyatta University (KU)');

    $stmtCheckUser = $pdo->prepare("SELECT role, is_gbs_leader FROM users WHERE id = ?");
    $stmtCheckUser->execute([$leaderUserId]);
    $userRow = $stmtCheckUser->fetch(PDO::FETCH_ASSOC);

    $stmtCheckLeaderTable = $pdo->prepare("SELECT COUNT(*) FROM leaders WHERE user_id = ? AND leader_type = 'gbs_leader'");
    $stmtCheckLeaderTable->execute([$leaderUserId]);
    $leaderRecordCount = (int)$stmtCheckLeaderTable->fetchColumn();

    assertTrue(
        (int)($userRow['is_gbs_leader'] ?? 0) === 1 && $leaderRecordCount === 1 && ($userRow['role'] ?? '') === 'member',
        'Scenario 1b: Super Admin appointment reconciles users.is_gbs_leader = 1 and leaders(gbs_leader) without promoting role to admin/super_admin',
        $failures
    );

    // =========================================================================
    // SCENARIO 2: Appointed leader receives GBS dashboard access
    // =========================================================================
    assertTrue(
        isUserGbsLeader($pdo, $leaderUserId) && canUserCreateGbsGroup($pdo, $leaderUserId, 'member'),
        'Scenario 2a: Appointed GBS Leader is recognized by isUserGbsLeader() and canUserCreateGbsGroup()',
        $failures
    );

    $layoutSource = file_get_contents($root . '/modules/portal/layout.php') ?: '';
    $dashboardSource = file_get_contents($root . '/modules/gbs/gbs_dashboard.php') ?: '';
    $createGbsSource = file_get_contents($root . '/modules/gbs/create_gbs.php') ?: '';

    assertTrue(
        strpos($layoutSource, 'isUserGbsLeader($pdo, $user_id)') !== false
        && strpos($layoutSource, 'GBS Leader View') !== false
        && strpos($layoutSource, 'Member View') !== false
        && strpos($dashboardSource, 'canUserCreateGbsGroup($pdo, $user_id, $user_role)') !== false
        && strpos($createGbsSource, 'canUserCreateGbsGroup($pdo, $userId, $userRole)') !== false,
        'Scenario 2b: Portal layout, switcher, gbs_dashboard.php, and create_gbs.php grant GBS dashboard & group creation access',
        $failures
    );

    // =========================================================================
    // SCENARIO 3 & 4: Appointed leader can create a group & is recorded as leader
    // =========================================================================
    // Verify an ordinary GBS Leader cannot assign another person as leader:
    $blockedAssignOther = false;
    try {
        createGbsGroup($pdo, $leaderUserId, 'member', "Unauthorized Assignment {$suffix}", "Slogan", $otherUserId, null);
    } catch (RuntimeException $e) {
        $blockedAssignOther = true;
    }

    $gbsId = createGbsGroup(
        $pdo,
        $leaderUserId,
        'member',
        "KU Grace Fellowship {$suffix}",
        "Growing in the Word",
        null,
        null
    );

    if ($gbsId > 0) {
        $createdGroupIds[] = $gbsId;
    }

    assertTrue(
        $gbsId > 0 && $blockedAssignOther,
        'Scenario 3: Appointed GBS Leader can create a new Bible Study group (and cannot assign someone else as leader)',
        $failures
    );

    $stmtGroup = $pdo->prepare("SELECT leader_id, name, slogan FROM gbs_groups WHERE id = ?");
    $stmtGroup->execute([$gbsId]);
    $groupRow = $stmtGroup->fetch(PDO::FETCH_ASSOC);

    $stmtLeaderMember = $pdo->prepare("SELECT role FROM gbs_members WHERE gbs_id = ? AND user_id = ?");
    $stmtLeaderMember->execute([$gbsId, $leaderUserId]);
    $leaderMemberRole = $stmtLeaderMember->fetchColumn();

    assertTrue(
        (int)($groupRow['leader_id'] ?? 0) === $leaderUserId && $leaderMemberRole === 'leader',
        'Scenario 4: Newly created group automatically records its creator as leader in gbs_groups and gbs_members',
        $failures
    );

    // =========================================================================
    // SCENARIO 5: The leader can open their group
    // =========================================================================
    $leaderMembership = getGbsGroupMembership($pdo, $gbsId, $leaderUserId, 'member');
    assertTrue(
        is_array($leaderMembership)
        && ($leaderMembership['role'] ?? '') === 'leader'
        && !empty($leaderMembership['can_manage'])
        && !empty($leaderMembership['can_delete']),
        'Scenario 5: Appointed GBS Leader can open and manage their group',
        $failures
    );

    // =========================================================================
    // SCENARIO 6: The Add Member modal submits add_member
    // =========================================================================
    $gbsGroupSource = file_get_contents($root . '/modules/gbs/gbs_group.php') ?: '';
    $addModalPos = strpos($gbsGroupSource, 'id="addMemberModal"');
    $editModalPos = strpos($gbsGroupSource, 'id="editGroupModal"');
    $addModalSlice = ($addModalPos !== false && $editModalPos !== false && $editModalPos > $addModalPos)
        ? substr($gbsGroupSource, $addModalPos, $editModalPos - $addModalPos)
        : '';

    assertTrue(
        $addModalSlice !== ''
        && strpos($addModalSlice, 'name="action" value="add_member"') !== false
        && strpos($addModalSlice, 'value="remove_member"') === false,
        'Scenario 6: Add Member modal in modules/gbs/gbs_group.php submits action="add_member" (and not remove_member)',
        $failures
    );

    // =========================================================================
    // SCENARIO 7: The leader can successfully add an existing registered member
    // =========================================================================
    $eligibleBefore = getEligibleGbsUsersNotInGroup($pdo, $gbsId);
    $eligibleIdsBefore = array_map(static fn(array $u): int => (int)$u['id'], $eligibleBefore);
    assertTrue(
        in_array($ordinaryMemberId, $eligibleIdsBefore, true),
        'Scenario 7a: Registered active member appears in eligible users list before being added',
        $failures
    );

    $firstEligible = $eligibleBefore[0] ?? [];
    assertTrue(
        !array_key_exists('password', $firstEligible)
        && !array_key_exists('reset_token', $firstEligible),
        'Scenario 7b: getEligibleGbsUsersNotInGroup() does not expose passwords or authentication tokens',
        $failures
    );

    $addedOk = false;
    try {
        addGbsMember($pdo, $gbsId, $leaderUserId, 'member', $ordinaryMemberId);
        $addedOk = true;
    } catch (Throwable $e) {
        $addedOk = false;
    }

    assertTrue(
        $addedOk === true,
        'Scenario 7c: Leader can successfully add an existing registered member to their group',
        $failures
    );

    // =========================================================================
    // SCENARIO 8: Duplicate membership is rejected
    // =========================================================================
    $dupRejected = false;
    $dupMessage = '';
    try {
        addGbsMember($pdo, $gbsId, $leaderUserId, 'member', $ordinaryMemberId);
    } catch (RuntimeException $e) {
        $dupRejected = true;
        $dupMessage = $e->getMessage();
    }

    assertTrue(
        $dupRejected && strpos($dupMessage, 'already a member') !== false,
        'Scenario 8: Adding the same member twice is rejected with a duplicate membership error',
        $failures
    );

    // =========================================================================
    // SCENARIO 9: The new member appears in the group membership list
    // =========================================================================
    $stmtMembers = $pdo->prepare("
        SELECT u.id, u.name, gm.role
        FROM gbs_members gm
        JOIN users u ON gm.user_id = u.id
        WHERE gm.gbs_id = ?
    ");
    $stmtMembers->execute([$gbsId]);
    $groupMembers = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
    $memberMap = [];
    foreach ($groupMembers as $gm) {
        $memberMap[(int)$gm['id']] = $gm['role'];
    }

    assertTrue(
        count($groupMembers) === 2
        && ($memberMap[$leaderUserId] ?? '') === 'leader'
        && ($memberMap[$ordinaryMemberId] ?? '') === 'member',
        'Scenario 9: Newly added member appears in the group membership list with role "member"',
        $failures
    );

    // =========================================================================
    // SCENARIO 10: The leader can remove an ordinary member
    // =========================================================================
    $removedOk = false;
    try {
        removeGbsMember($pdo, $gbsId, $leaderUserId, 'member', $ordinaryMemberId);
        $removedOk = true;
    } catch (Throwable $e) {
        $removedOk = false;
    }

    $stmtCheckRemoved = $pdo->prepare("SELECT COUNT(*) FROM gbs_members WHERE gbs_id = ? AND user_id = ?");
    $stmtCheckRemoved->execute([$gbsId, $ordinaryMemberId]);
    assertTrue(
        $removedOk && (int)$stmtCheckRemoved->fetchColumn() === 0,
        'Scenario 10: Leader can remove an ordinary member from their group',
        $failures
    );

    // Re-add member so we can verify preservation on leader revocation later
    addGbsMember($pdo, $gbsId, $leaderUserId, 'member', $ordinaryMemberId);

    // =========================================================================
    // SCENARIO 11: The leader cannot remove themselves as group leader
    // =========================================================================
    $selfRemBlocked = false;
    $selfRemMessage = '';
    try {
        removeGbsMember($pdo, $gbsId, $leaderUserId, 'member', $leaderUserId);
    } catch (RuntimeException $e) {
        $selfRemBlocked = true;
        $selfRemMessage = $e->getMessage();
    }

    $stmtCheckLeaderStillInGroup = $pdo->prepare("SELECT COUNT(*) FROM gbs_members WHERE gbs_id = ? AND user_id = ? AND role = 'leader'");
    $stmtCheckLeaderStillInGroup->execute([$gbsId, $leaderUserId]);
    assertTrue(
        $selfRemBlocked
        && strpos($selfRemMessage, 'Cannot remove the group leader') !== false
        && (int)$stmtCheckLeaderStillInGroup->fetchColumn() === 1,
        'Scenario 11: Leader cannot remove themselves as group leader through ordinary member removal',
        $failures
    );

    // =========================================================================
    // SCENARIO 12: An unrelated member cannot manage another person's group
    // =========================================================================
    appointGbsLeader($pdo, $otherUserId, $superAdminId, 'Kenyatta University (KU)');

    $unrelatedMembership = getGbsGroupMembership($pdo, $gbsId, $otherUserId, 'member');
    $unauthAddBlocked = false;
    $unauthRemoveBlocked = false;
    $unauthUpdateBlocked = false;

    try {
        addGbsMember($pdo, $gbsId, $otherUserId, 'member', $mondayLeaderId);
    } catch (RuntimeException $e) {
        $unauthAddBlocked = true;
    }
    try {
        removeGbsMember($pdo, $gbsId, $otherUserId, 'member', $ordinaryMemberId);
    } catch (RuntimeException $e) {
        $unauthRemoveBlocked = true;
    }
    try {
        updateGbsGroup($pdo, $gbsId, $otherUserId, 'member', 'Hacked Group Name', 'Hacked Slogan');
    } catch (RuntimeException $e) {
        $unauthUpdateBlocked = true;
    }

    assertTrue(
        $unrelatedMembership === null
        && !canManageGbsGroup($pdo, $gbsId, $otherUserId, 'member')
        && $unauthAddBlocked
        && $unauthRemoveBlocked
        && $unauthUpdateBlocked,
        'Scenario 12: Unrelated member or another GBS Leader cannot view or manage another leader\'s group',
        $failures
    );

    // =========================================================================
    // SCENARIO 13: A revoked GBS Leader loses management access while group & member records remain intact
    // =========================================================================
    revokeGbsLeader($pdo, $leaderUserId);

    $stmtAfterRevokeUser = $pdo->prepare("SELECT account_status, is_gbs_leader FROM users WHERE id = ?");
    $stmtAfterRevokeUser->execute([$leaderUserId]);
    $revokedUserRow = $stmtAfterRevokeUser->fetch(PDO::FETCH_ASSOC);

    $stmtAfterRevokeGroup = $pdo->prepare("SELECT COUNT(*) FROM gbs_groups WHERE id = ?");
    $stmtAfterRevokeGroup->execute([$gbsId]);
    $groupStillExists = (int)$stmtAfterRevokeGroup->fetchColumn() === 1;

    $stmtAfterRevokeMembers = $pdo->prepare("SELECT COUNT(*) FROM gbs_members WHERE gbs_id = ?");
    $stmtAfterRevokeMembers->execute([$gbsId]);
    $membersStillExist = (int)$stmtAfterRevokeMembers->fetchColumn() === 2;

    $revokedLeaderMembership = getGbsGroupMembership($pdo, $gbsId, $leaderUserId, 'member');
    $revokedAddBlocked = false;
    try {
        addGbsMember($pdo, $gbsId, $leaderUserId, 'member', $otherUserId);
    } catch (RuntimeException $e) {
        $revokedAddBlocked = true;
    }

    assertTrue(
        !isUserGbsLeader($pdo, $leaderUserId)
        && !canUserCreateGbsGroup($pdo, $leaderUserId, 'member')
        && !canManageGbsGroup($pdo, $gbsId, $leaderUserId, 'member')
        && empty($revokedLeaderMembership['can_manage'])
        && $revokedAddBlocked
        && ($revokedUserRow['account_status'] ?? '') === 'active'
        && $groupStillExists
        && $membersStillExist,
        'Scenario 13: Revoked GBS Leader loses group creation and management access while user account, group, and memberships remain intact',
        $failures
    );

    // Re-appoint leader so Scenario 14 can verify active leader oversight
    appointGbsLeader($pdo, $leaderUserId, $superAdminId, 'Kenyatta University (KU)');

    // =========================================================================
    // SCENARIO 14: Super Admin retains full GBS oversight
    // =========================================================================
    $superMembership = getGbsGroupMembership($pdo, $gbsId, $superAdminId, 'super_admin');
    $superUpdated = false;
    try {
        updateGbsGroup($pdo, $gbsId, $superAdminId, 'super_admin', "KU Grace Fellowship Updated {$suffix}", 'Updated Slogan');
        $superUpdated = true;
    } catch (Throwable $e) {
        $superUpdated = false;
    }

    $appointedLeadersWithGroups = getAppointedGbsLeadersWithGroups($pdo);
    $foundLeaderInOversight = false;
    foreach ($appointedLeadersWithGroups as $row) {
        if ((int)$row['id'] === $leaderUserId) {
            foreach ($row['groups'] ?? [] as $grp) {
                if ((int)$grp['id'] === $gbsId && (int)$grp['member_count'] === 2) {
                    $foundLeaderInOversight = true;
                    break 2;
                }
            }
        }
    }

    assertTrue(
        !empty($superMembership['can_manage'])
        && !empty($superMembership['can_delete'])
        && $superUpdated
        && $foundLeaderInOversight,
        'Scenario 14: Super Admin retains full GBS oversight (can view appointed leaders, groups & member counts, and manage any group)',
        $failures
    );

    // =========================================================================
    // SCENARIO 15: General Monday Bible Study functionality remains separate
    // =========================================================================
    $pdo->prepare("INSERT INTO leaders (user_id, leader_type, created_by, created_at) VALUES (?, 'bible_study_leader', ?, NOW())")
        ->execute([$mondayLeaderId, $superAdminId]);

    assertTrue(
        !isUserGbsLeader($pdo, $mondayLeaderId)
        && !canUserCreateGbsGroup($pdo, $mondayLeaderId, 'member')
        && !canManageGbsGroup($pdo, $gbsId, $mondayLeaderId, 'member'),
        'Scenario 15a: General Monday Bible Study appointment (bible_study_leader) does NOT grant campus GBS leadership access',
        $failures
    );

    $bibleStudyCoreSource = file_get_contents($root . '/core/bible_studies.php') ?: '';
    assertTrue(
        function_exists('bs_is_leader')
        && !bs_is_leader($pdo, 1, $leaderUserId)
        && strpos($bibleStudyCoreSource, 'is_gbs_leader') === false
        && strpos($bibleStudyCoreSource, 'gbs_leader') === false,
        'Scenario 15b: Campus GBS appointment does not grant access to General Monday Bible Study management',
        $failures
    );

} finally {
    // Clean up test groups and test users without affecting real data
    foreach ($createdGroupIds as $gid) {
        try {
            deleteGbsGroup($pdo, (int)$gid);
        } catch (Throwable $e) {
            // ignore cleanup errors
        }
    }
    if (!empty($createdUserIds)) {
        $placeholders = implode(',', array_fill(0, count($createdUserIds), '?'));
        $pdo->prepare("DELETE FROM leaders WHERE user_id IN ({$placeholders})")->execute($createdUserIds);
        $pdo->prepare("DELETE FROM messages WHERE sender_id IN ({$placeholders}) OR receiver_id IN ({$placeholders})")
            ->execute(array_merge($createdUserIds, $createdUserIds));
        $pdo->prepare("DELETE FROM gbs_members WHERE user_id IN ({$placeholders})")->execute($createdUserIds);
        $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($createdUserIds);
    }
}

echo "\n";
if (!empty($failures)) {
    echo "FAILED (" . count($failures) . " scenario(s) failed):\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "ALL 15 PHASE 7 GBS SCENARIOS PASSED!\n";
exit(0);
