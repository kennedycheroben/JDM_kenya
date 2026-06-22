<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/contact_messages.php';

if (!function_exists('redirectToContact')) {
    function redirectToContact($query = '')
    {
        $location = '/JDM_kenya/contact.php' . $query;
        header('Location: ' . $location);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $subject && $message) {
        ensureContactMessagesTable($pdo);

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if ($userId <= 0) {
            $userId = null;
        }

        $stmt = $pdo->prepare('
            INSERT INTO contact_messages (user_id, name, email, subject, message)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $name, $email, $subject, $message]);

        // For now, just log to a file or send email. Since no mail server, we'll log.
        $log = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message\n\n";
        $logFile = dirname(__FILE__) . '/../../contact_logs.txt';
        file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);

        // Redirect back with success
        redirectToContact('?success=1');
    } else {
        redirectToContact('?error=1');
    }
} else {
    redirectToContact();
}
?>
