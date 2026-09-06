<?php
require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/core/mailer.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli && (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin')) {
    http_response_code(403);
    echo 'Access denied. Sign in as JDM Leader / super admin to send a test email.';
    exit;
}

$recipient = $isCli ? ($argv[1] ?? '') : trim($_GET['to'] ?? '');

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $usage = $isCli
        ? "Usage: /opt/lampp/bin/php test_email.php you@example.com\n"
        : "Add ?to=you@example.com to the URL.\n";
    echo $usage;
    exit(1);
}

$subject = 'JDM Kenya email test';
$body = '<p>This is a test email from the JDM Kenya portal.</p><p>If you received it, password reset email delivery is configured.</p>';
$plain = "This is a test email from the JDM Kenya portal.\n\nIf you received it, password reset email delivery is configured.";

if (send_app_email($recipient, $subject, $body, $plain)) {
    echo "Test email sent to {$recipient}.\n";
    exit;
}

http_response_code(500);
echo "Test email could not be sent. Check SMTP settings in config.php and the PHP error log.\n";
exit(1);
