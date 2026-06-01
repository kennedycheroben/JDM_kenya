<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $subject && $message) {
        // For now, just log to a file or send email. Since no mail server, we'll log.
        $log = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message\n\n";
        file_put_contents('contact_logs.txt', $log, FILE_APPEND);

        // Redirect back with success
        header('Location: contact.php?success=1');
        exit;
    } else {
        header('Location: contact.php?error=1');
        exit;
    }
} else {
    header('Location: contact.php');
    exit;
}
?>
