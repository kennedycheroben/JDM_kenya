<?php
/**
 * JDM Kenya - Membership, Registration, Directory, Chat, and Deletion Integrity Test Suite
 *
 * Verifies:
 * 1. Database connection targets the authentic local database (jdm_kenya)
 * 2. Member registration persistence and status handling
 * 3. Member directory role, approval, and account status visibility
 * 4. Chat recipient discovery bidirectionality between members
 * 5. Lifecycle status transitions (active, disabled, suspended)
 * 6. Foreign-key-safe member deletion with assignment cleanup
 * 7. Security guards against deleting super administrators
 */

require_once __DIR__ . '/../core/db_connect.php';
require_once __DIR__ . '/../core/users.php';
require_once __DIR__ . '/../core/notifications.php';

$totalPassed = 0;
$totalFailed = 0;

function it(string $description, callable $test): void
{
    global $totalPassed, $totalFailed;
    try {
        $test();
        echo "PASS: {$description}\n";
        $totalPassed++;
    } catch (Throwable $e) {
        echo "FAIL: {$description}\n";
        echo "      " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $totalFailed++;
    }
}

function assert_true(bool $condition, string $message = 'Expected true'): void
{
    if (!$condition) {
        throw new Exception($message);
    }
}

function assert_equals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $msg = $message ?: sprintf('Expected %s, got %s', var_export($expected, true), var_export($actual, true));
        throw new Exception($msg);
    }
}

// Ensure database connection is active
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection failed. Cannot run integrity tests.\n");
}

echo "=== JDM Kenya Membership & Database Integrity Test Suite ===\n";

// 1. Database Connection Target
it('connects to canonical jdm_kenya database', function () use ($pdo) {
    $activeDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    assert_equals('jdm_kenya', $activeDb, "Active database must be 'jdm_kenya'");
    assert_true(defined('DB_NAME') && DB_NAME === 'jdm_kenya', "DB_NAME constant must be 'jdm_kenya'");
});

// 2. Member Registration Persistence
it('persists newly registered user in jdm_kenya with active account_status', function () use ($pdo) {
    $testEmail = 'integrity_reg_test_' . time() . '@example.local';
    $testPhone = '254711999888';
    $testName = 'Integrity Register Test';

    $insert = $pdo->prepare('
        INSERT INTO users (
            name, email, whatsapp_phone, password, role, category,
            is_approved, account_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $insert->execute([$testName, $testEmail, $testPhone, 'hash', 'member', 'student', 1, 'active']);
    $userId = (int)$pdo->lastInsertId();
    assert_true($userId > 0, 'User ID must be assigned upon insertion');

    // Retrieve and verify
    $stmt = $pdo->prepare('SELECT id, name, email, account_status, is_approved FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    assert_true(!empty($u), 'Inserted user must be retrievable from jdm_kenya');
    assert_equals($testName, $u['name']);
    assert_equals($testEmail, $u['email']);
    assert_equals('active', $u['account_status']);
    assert_equals(1, (int)$u['is_approved']);

    // Cleanup
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
});

// 3. Member Directory Visibility Rules
it('directory excludes unapproved or deactivated members from ordinary member view', function () use ($pdo) {
    // Insert 1 approved active, 1 unapproved, 1 deactivated
    $t = time();
    $p = $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, category, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $p->execute(["Dir Active {$t}", "dir_act_{$t}@test.local", '254700000001', 'p', 'member', 'student', 1, 'active']);
    $idActive = (int)$pdo->lastInsertId();

    $p->execute(["Dir Pending {$t}", "dir_pend_{$t}@test.local", '254700000002', 'p', 'member', 'partner', 0, 'active']);
    $idPending = (int)$pdo->lastInsertId();

    $p->execute(["Dir Disabled {$t}", "dir_dis_{$t}@test.local", '254700000003', 'p', 'member', 'student', 1, 'disabled']);
    $idDisabled = (int)$pdo->lastInsertId();

    // Query as ordinary member
    $memberQuery = "SELECT id FROM users WHERE role IN ('member','admin','super_admin') AND category NOT IN ('partner', 'missionary') AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status = 'active')";
    $visibleIds = $pdo->query($memberQuery)->fetchAll(PDO::FETCH_COLUMN);

    assert_true(in_array((string)$idActive, array_map('strval', $visibleIds), true), 'Active approved member must be visible in directory');
    assert_true(!in_array((string)$idPending, array_map('strval', $visibleIds), true), 'Pending unapproved member must NOT be visible to regular members');
    assert_true(!in_array((string)$idDisabled, array_map('strval', $visibleIds), true), 'Deactivated member must NOT be visible to regular members');

    // Query as super_admin
    $adminQuery = "SELECT id FROM users WHERE role IN ('member','admin','super_admin') AND (account_status IS NULL OR account_status != 'deleted')";
    $adminVisibleIds = $pdo->query($adminQuery)->fetchAll(PDO::FETCH_COLUMN);

    assert_true(in_array((string)$idActive, array_map('strval', $adminVisibleIds), true), 'Super admin sees active member');
    assert_true(in_array((string)$idPending, array_map('strval', $adminVisibleIds), true), 'Super admin sees pending partner');
    assert_true(in_array((string)$idDisabled, array_map('strval', $adminVisibleIds), true), 'Super admin sees deactivated member');

    // Cleanup
    $pdo->prepare('DELETE FROM users WHERE id IN (?, ?, ?)')->execute([$idActive, $idPending, $idDisabled]);
});

// 4. Chat Recipient Discovery Bidirectionality
it('chat recipient query discovers members bidirectionally and excludes self and inactive accounts', function () use ($pdo) {
    $t = time();
    $p = $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, category, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $p->execute(["Chat Member Alpha {$t}", "alpha_{$t}@test.local", '254700000010', 'p', 'member', 'student', 1, 'active']);
    $idAlpha = (int)$pdo->lastInsertId();

    $p->execute(["Chat Member Beta {$t}", "beta_{$t}@test.local", '254700000011', 'p', 'member', 'other', 1, 'active']);
    $idBeta = (int)$pdo->lastInsertId();

    $p->execute(["Chat Member Disabled {$t}", "dis_{$t}@test.local", '254700000012', 'p', 'member', 'other', 1, 'disabled']);
    $idDisabled = (int)$pdo->lastInsertId();

    $chatStmt = $pdo->prepare("SELECT id FROM users WHERE id <> ? AND category != 'partner' AND (is_approved = 1 OR is_approved IS NULL) AND (account_status IS NULL OR account_status = 'active') ORDER BY name ASC");

    // From Alpha's viewpoint
    $chatStmt->execute([$idAlpha]);
    $alphaRecipients = array_map('intval', $chatStmt->fetchAll(PDO::FETCH_COLUMN));

    assert_true(!in_array($idAlpha, $alphaRecipients, true), 'Alpha must not see themselves in chat recipients');
    assert_true(in_array($idBeta, $alphaRecipients, true), 'Alpha must see Beta in chat recipients');
    assert_true(!in_array($idDisabled, $alphaRecipients, true), 'Alpha must not see disabled member in chat recipients');

    // From Beta's viewpoint
    $chatStmt->execute([$idBeta]);
    $betaRecipients = array_map('intval', $chatStmt->fetchAll(PDO::FETCH_COLUMN));

    assert_true(!in_array($idBeta, $betaRecipients, true), 'Beta must not see themselves in chat recipients');
    assert_true(in_array($idAlpha, $betaRecipients, true), 'Beta must see Alpha in chat recipients (bidirectionality)');
    assert_true(!in_array($idDisabled, $betaRecipients, true), 'Beta must not see disabled member in chat recipients');

    // Cleanup
    $pdo->prepare('DELETE FROM users WHERE id IN (?, ?, ?)')->execute([$idAlpha, $idBeta, $idDisabled]);
});

// 5. Lifecycle Status Transitions
it('manages account status transitions safely', function () use ($pdo) {
    $t = time();
    $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(["Status Test {$t}", "status_{$t}@test.local", '254700000020', 'p', 'member', 1, 'active']);
    $userId = (int)$pdo->lastInsertId();

    assert_equals('active', getUserAccountStatus($pdo, $userId));

    // Disable
    $res = setUserAccountStatus($pdo, $userId, 'disabled');
    assert_true($res, 'Setting status to disabled must return true');
    assert_equals('disabled', getUserAccountStatus($pdo, $userId));

    // Suspend
    $res = setUserAccountStatus($pdo, $userId, 'suspended');
    assert_true($res, 'Setting status to suspended must return true');
    assert_equals('suspended', getUserAccountStatus($pdo, $userId));

    // Reactivate
    $res = setUserAccountStatus($pdo, $userId, 'active');
    assert_true($res, 'Setting status to active must return true');
    assert_equals('active', getUserAccountStatus($pdo, $userId));

    // Invalid status rejected
    $res = setUserAccountStatus($pdo, $userId, 'invalid_status_xyz');
    assert_true(!$res, 'Setting invalid status must return false');
    assert_equals('active', getUserAccountStatus($pdo, $userId));

    // Cleanup
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
});

// 6. User Dependency Inspection and Safe Deletion with RESTRICT FKs
it('identifies restrictive foreign keys and deletes user cleanly without FK violation', function () use ($pdo) {
    $t = time();
    $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(["FK Test User {$t}", "fk_test_{$t}@test.local", '254700000030', 'p', 'member', 1, 'active']);
    $userId = (int)$pdo->lastInsertId();

    // Attach restrictive FK records:
    // 1. Bible Study leader assignment (RESTRICT)
    $bsId = $pdo->query('SELECT id FROM bible_studies LIMIT 1')->fetchColumn();
    if (!$bsId) {
        $pdo->exec("INSERT INTO bible_studies (title, description) VALUES ('Test Study', 'Desc')");
        $bsId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("INSERT INTO bible_study_leader_assignments (bible_study_id, leader_user_id, is_current, appointed_at) VALUES (?, ?, 0, NOW())")
        ->execute([$bsId, $userId]);

    // 2. Sports Admin assignment (RESTRICT)
    $pdo->prepare("INSERT INTO sports_admin_assignments (user_id, status, appointed_at) VALUES (?, 'active', NOW())")
        ->execute([$userId]);

    // 3. Ministry leader appointment
    $pdo->prepare("INSERT INTO leaders (user_id, leader_type, created_by) VALUES (?, 'campus_leader', ?)")
        ->execute([$userId, $userId]);

    // 4. Chat messages
    $otherUser = $pdo->query("SELECT id FROM users WHERE id <> {$userId} LIMIT 1")->fetchColumn();
    if ($otherUser) {
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, status) VALUES (?, ?, 'Hello test', 'sent')")
            ->execute([$userId, $otherUser]);
    }

    // Inspect dependencies
    $deps = checkUserDependencies($pdo, $userId);
    assert_true(!$deps['can_delete_safely'], 'User with active assignments must report can_delete_safely = false');
    assert_true($deps['counts']['bible_study_leader_assignments'] >= 1, 'Must count bible study leader assignment');
    assert_true($deps['counts']['sports_admin_assignments'] >= 1, 'Must count sports admin assignment');
    assert_true($deps['counts']['leaders'] >= 1, 'Must count leader appointment');

    // Deleting with cleanAssignments = false must throw RuntimeException
    $caughtException = false;
    try {
        deleteUser($pdo, $userId, false);
    } catch (RuntimeException $re) {
        $caughtException = true;
    }
    assert_true($caughtException, 'deleteUser with cleanAssignments=false must throw RuntimeException when dependencies exist');

    // Deleting with cleanAssignments = true must cleanly and transactionally remove assignments and delete user
    deleteUser($pdo, $userId, true);

    // Verify user no longer exists
    $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $exists->execute([$userId]);
    assert_equals(0, (int)$exists->fetchColumn(), 'User must be permanently removed');

    // Verify child assignments were cleanly removed
    $cntBsla = $pdo->prepare('SELECT COUNT(*) FROM bible_study_leader_assignments WHERE leader_user_id = ?');
    $cntBsla->execute([$userId]);
    assert_equals(0, (int)$cntBsla->fetchColumn(), 'Leader assignment must be cleaned up');

    $cntSports = $pdo->prepare('SELECT COUNT(*) FROM sports_admin_assignments WHERE user_id = ?');
    $cntSports->execute([$userId]);
    assert_equals(0, (int)$cntSports->fetchColumn(), 'Sports admin assignment must be cleaned up');
});

// 7. Protection of Super Administrators
it('prohibits deletion of super administrator accounts and invalid user IDs', function () use ($pdo) {
    // Invalid ID
    $caught = false;
    try {
        deleteUser($pdo, 0);
    } catch (InvalidArgumentException $e) {
        $caught = true;
    }
    assert_true($caught, 'Deleting ID 0 must throw InvalidArgumentException');

    // Super Admin protection
    $t = time();
    $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(["SuperAdmin Guard {$t}", "superguard_{$t}@test.local", '254700000040', 'p', 'super_admin', 1, 'active']);
    $saId = (int)$pdo->lastInsertId();

    $caughtSa = false;
    try {
        deleteUser($pdo, $saId, true);
    } catch (InvalidArgumentException $e) {
        $caughtSa = true;
    }
    assert_true($caughtSa, 'Deleting super_admin must throw InvalidArgumentException');

    // Cleanup manually
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$saId]);
});

echo "------------------------------------------------------------\n";
echo "SUMMARY: {$totalPassed} passed, {$totalFailed} failed.\n";
echo "------------------------------------------------------------\n";

if ($totalFailed > 0) {
    exit(1);
}
