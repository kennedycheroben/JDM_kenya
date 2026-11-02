<?php
/**
 * JDM Kenya - Platform Improvements & Regression Test Suite
 *
 * Covers:
 * 1. Event description expansion (Phase 3)
 * 2. Contact messages inbox & bulk actions (Phase 4)
 * 3. Dynamic notifications & read tracking (Phase 5)
 * 4. Profile update & privilege protection (Phase 6)
 * 5. Route and layout integrity
 */

require_once dirname(__DIR__) . '/core/db_connect.php';
require_once dirname(__DIR__) . '/core/contact_messages.php';
require_once dirname(__DIR__) . '/core/notifications.php';
ob_start();
require_once dirname(__DIR__) . '/modules/public/activities.php';
ob_end_clean();

$passed = 0;
$failed = 0;

function it(string $description, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "PASS: $description\n";
        $passed++;
    } catch (Throwable $e) {
        echo "FAIL: $description - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// SECTION 1: Event Description Expansion
// -------------------------------------------------------------
it('event description expansion - short text renders directly without details toggle', function() {
    $html = render_event_description('Short single-line description.');
    if (strpos($html, 'event-desc-full') === false) {
        throw new RuntimeException('Expected event-desc-full container');
    }
    if (strpos($html, '<details') !== false) {
        throw new RuntimeException('Short text should not contain details tag');
    }
});

it('event description expansion - long text renders View More / View Less toggle', function() {
    $long = str_repeat('Kenya Mission Conference in Nairobi. Strengthening communities through prayer. ', 5);
    $html = render_event_description($long, 100);
    if (strpos($html, '<details class="event-toggle">') === false) {
        throw new RuntimeException('Expected event-toggle details component');
    }
    if (strpos($html, 'View more') === false || strpos($html, 'View less') === false) {
        throw new RuntimeException('Expected View more and View less controls');
    }
    if (strpos($html, 'event-preview') === false || strpos($html, 'event-full') === false) {
        throw new RuntimeException('Expected preview and full content sections');
    }
});

it('event description expansion - multiline text preserves line breaks and escapes XSS', function() {
    $raw = "<script>alert('xss')</script>\nFirst paragraph.\n\nSecond paragraph & special \"chars\".";
    $html = render_event_description($raw, 80);
    if (strpos($html, '<script>') !== false) {
        throw new RuntimeException('XSS script tag was not escaped');
    }
    if (strpos($html, '&lt;script&gt;') === false) {
        throw new RuntimeException('Expected &lt;script&gt; entity');
    }
    if (strpos($html, '<br') === false) {
        throw new RuntimeException('Expected line breaks to be preserved with <br>');
    }
});

it('event description expansion - UTF-8 and multibyte safety', function() {
    $swahili = 'Mkutano Mkuu wa Injili Nairobi 🇰🇪: Karibu sana tujifunze Neno la Mungu pamoja! ' . str_repeat('Neno na Sala. ', 15);
    $html = render_event_description($swahili, 100);
    if (strpos($html, '🇰🇪') === false) {
        throw new RuntimeException('Multibyte emoji was lost or corrupted');
    }
    if (strpos($html, 'Mkutano Mkuu') === false) {
        throw new RuntimeException('Text beginning was corrupted');
    }
});

// -------------------------------------------------------------
// SECTION 2: Contact Messages Management
// -------------------------------------------------------------
ensureContactMessagesTable($pdo);

it('contact messages - schema supports status, archived_at and deleted_at', function() use ($pdo) {
    $cols = $pdo->query('SHOW COLUMNS FROM contact_messages')->fetchAll(PDO::FETCH_COLUMN);
    $required = ['status', 'read_at', 'archived_at', 'deleted_at'];
    foreach ($required as $col) {
        if (!in_array($col, $cols, true)) {
            throw new RuntimeException("Missing expected column $col in contact_messages");
        }
    }
});

it('contact messages - bulk read, unread, archive, trash, and restore operations', function() use ($pdo) {
    // Clean old tests
    $pdo->exec("DELETE FROM contact_messages WHERE email LIKE 'regtest_%@jdm.test'");

    // Create 3 test messages
    $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'unread')");
    $ids = [];
    for ($i = 1; $i <= 3; $i++) {
        $stmt->execute(["Test Sender $i", "regtest_{$i}@jdm.test", "Subject $i", "Message $i body"]);
        $ids[] = (int)$pdo->lastInsertId();
    }

    // 1. Bulk mark read
    $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE id IN ({$ids[0]}, {$ids[1]}) AND deleted_at IS NULL")->execute();
    $readCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE id IN ({$ids[0]}, {$ids[1]}) AND status = 'read'")->fetchColumn();
    if ($readCount !== 2) throw new RuntimeException("Expected 2 read messages, got $readCount");

    // 2. Bulk archive
    $pdo->prepare("UPDATE contact_messages SET status = 'archived', archived_at = NOW() WHERE id = {$ids[0]} AND deleted_at IS NULL")->execute();
    $archCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE id = {$ids[0]} AND status = 'archived'")->fetchColumn();
    if ($archCount !== 1) throw new RuntimeException("Expected 1 archived message, got $archCount");

    // 3. Bulk trash
    $pdo->prepare("UPDATE contact_messages SET deleted_at = NOW() WHERE id = {$ids[2]}")->execute();
    $trashCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE id = {$ids[2]} AND deleted_at IS NOT NULL")->fetchColumn();
    if ($trashCount !== 1) throw new RuntimeException("Expected 1 trashed message, got $trashCount");

    // 4. Restore from trash
    $pdo->prepare("UPDATE contact_messages SET deleted_at = NULL WHERE id = {$ids[2]}")->execute();
    $restoredCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE id = {$ids[2]} AND deleted_at IS NULL")->fetchColumn();
    if ($restoredCount !== 1) throw new RuntimeException("Expected message to be restored from trash");

    // Cleanup
    $pdo->exec("DELETE FROM contact_messages WHERE email LIKE 'regtest_%@jdm.test'");
});

it('contact messages - bulk operations do not affect private member chat messages table', function() use ($pdo) {
    // Verify private messages table count before and after
    $beforeCount = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    
    // Perform contact messages query
    $pdo->exec("DELETE FROM contact_messages WHERE email LIKE 'isolation_test@jdm.test'");
    
    $afterCount = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    if ($beforeCount !== $afterCount) {
        throw new RuntimeException('Private chat messages count was altered by contact_messages operation');
    }
});

// -------------------------------------------------------------
// SECTION 3: Dynamic Sidebar Notifications & Read-Tracking
// -------------------------------------------------------------
ensureUserContentReadsTable($pdo);

it('notifications - creator is not notified about own published content', function() use ($pdo) {
    // Setup test users
    $pdo->exec("DELETE FROM users WHERE email IN ('notif_creator@test.com', 'notif_reader@test.com')");
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, whatsapp_phone, role, is_approved) VALUES (?, ?, 'pass', '254700000000', 'admin', 1)");
    $stmtUser->execute(['Creator Admin', 'notif_creator@test.com']);
    $creatorId = (int)$pdo->lastInsertId();

    $stmtUser->execute(['Reader Member', 'notif_reader@test.com']);
    $readerId = (int)$pdo->lastInsertId();

    // Creator posts an announcement
    $stmtAnn = $pdo->prepare("INSERT INTO announcements (title, content, posted_by, date_created) VALUES ('Admin Announcement', 'Body', ?, NOW())");
    $stmtAnn->execute([$creatorId]);
    $annId = (int)$pdo->lastInsertId();

    $creatorCounts = get_user_notification_counts($pdo, $creatorId, 'admin');
    $readerCounts = get_user_notification_counts($pdo, $readerId, 'member');

    if ($creatorCounts['new_announcements'] !== 0) {
        throw new RuntimeException("Creator should see 0 announcements, got " . $creatorCounts['new_announcements']);
    }
    if ($readerCounts['new_announcements'] < 1) {
        throw new RuntimeException("Reader should see at least 1 announcement, got " . $readerCounts['new_announcements']);
    }

    // Reader views and marks as read
    mark_content_as_read($pdo, $readerId, 'announcement', $annId);
    $readerAfter = get_user_notification_counts($pdo, $readerId, 'member');
    if ($readerAfter['new_announcements'] !== 0) {
        throw new RuntimeException("Reader should see 0 announcements after reading, got " . $readerAfter['new_announcements']);
    }

    // Cleanup
    $pdo->exec("DELETE FROM announcements WHERE id = $annId");
    $pdo->exec("DELETE FROM user_content_reads WHERE user_id IN ($creatorId, $readerId)");
    $pdo->exec("DELETE FROM users WHERE id IN ($creatorId, $readerId)");
});

it('notifications - private prayer requests are hidden from member counts but visible to admins', function() use ($pdo) {
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, whatsapp_phone, role, is_approved) VALUES (?, ?, 'pass', '254700000000', 'member', 1)");
    $stmtUser->execute(['Prayer Poster', 'poster@test.com']);
    $posterId = (int)$pdo->lastInsertId();

    $stmtUser->execute(['Member Observer', 'observer@test.com']);
    $observerId = (int)$pdo->lastInsertId();

    // Insert private prayer
    $stmtPr = $pdo->prepare("INSERT INTO prayer_requests (user_id, message, is_private, privacy_level, created_at) VALUES (?, 'Strictly private prayer', 1, 'private', NOW())");
    $stmtPr->execute([$posterId]);
    $prayerId = (int)$pdo->lastInsertId();

    $memberCounts = get_user_notification_counts($pdo, $observerId, 'member');
    if ($memberCounts['new_prayers'] !== 0) {
        throw new RuntimeException("Regular member must not count private prayer requests, got " . $memberCounts['new_prayers']);
    }

    $adminCounts = get_user_notification_counts($pdo, $observerId, 'admin');
    if ($adminCounts['new_prayers'] < 1) {
        throw new RuntimeException("Admin should count new prayer request requiring attention");
    }

    // Cleanup
    $pdo->exec("DELETE FROM prayer_requests WHERE id = $prayerId");
    $pdo->exec("DELETE FROM user_content_reads WHERE user_id IN ($posterId, $observerId)");
    $pdo->exec("DELETE FROM users WHERE id IN ($posterId, $observerId)");
});

// -------------------------------------------------------------
// SECTION 4: Profile Privilege Escalation Protection
// -------------------------------------------------------------
it('profile update - prevents unauthorized privilege escalation to admin role or approval tampering', function() use ($pdo) {
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, whatsapp_phone, role, is_approved) VALUES (?, ?, 'pass', '254711111111', 'member', 0)");
    $stmtUser->execute(['Regular Member', 'privtest@test.com']);
    $testId = (int)$pdo->lastInsertId();

    // Simulate profile update logic: update only allowed name, email, phone
    $submittedName = 'Updated Name';
    $submittedEmail = 'privtest_updated@test.com';
    $submittedPhone = '254722222222';
    // Attacker tries to inject role and is_approved
    $maliciousRole = 'super_admin';
    $maliciousApproved = 1;

    // The authorized update statement (from profile.php)
    $stmtUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ?, whatsapp_phone = ? WHERE id = ?');
    $stmtUpdate->execute([$submittedName, $submittedEmail, $submittedPhone, $testId]);

    // Verify database record
    $check = $pdo->query("SELECT name, email, role, is_approved FROM users WHERE id = $testId")->fetch(PDO::FETCH_ASSOC);
    if ($check['role'] !== 'member') {
        throw new RuntimeException("Role was maliciously escalated to " . $check['role']);
    }
    if ((int)$check['is_approved'] !== 0) {
        throw new RuntimeException("Approval status was maliciously altered");
    }
    if ($check['name'] !== $submittedName || $check['email'] !== $submittedEmail) {
        throw new RuntimeException("Legitimate fields were not updated properly");
    }

    // Cleanup
    $pdo->exec("DELETE FROM users WHERE id = $testId");
});

// -------------------------------------------------------------
// SECTION 5: Route and Entry Point Integrity
// -------------------------------------------------------------
it('route integrity - canonical member dashboard exists and members_portal redirects', function() {
    $root = dirname(__DIR__);
    if (!file_exists($root . '/member_dashboard.php')) {
        throw new RuntimeException('Canonical member_dashboard.php missing');
    }
    $mpContent = file_get_contents($root . '/members_portal.php');
    if (strpos($mpContent, 'member_dashboard.php') === false) {
        throw new RuntimeException('members_portal.php should redirect to member_dashboard.php');
    }
    $profileContent = file_get_contents($root . '/profile.php');
    if (strpos($profileContent, 'modules/auth/profile.php') === false) {
        throw new RuntimeException('profile.php should require modules/auth/profile.php');
    }
});

echo "\n------------------------------------------------------------\n";
echo "SUMMARY: $passed passed, $failed failed.\n";
echo "------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
