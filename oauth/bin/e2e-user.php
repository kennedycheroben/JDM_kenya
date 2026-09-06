<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (! filter_var(getenv('JDM_E2E_FIXTURES_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL)) {
    fwrite(STDERR, "JDM E2E fixtures are disabled.\n");
    exit(1);
}

require_once dirname(__DIR__, 2).'/core/db_connect.php';

if (! in_array(strtolower((string) DB_HOST), ['localhost', '127.0.0.1'], true)
    || ! in_array((string) DB_NAME, ['jdm_kenya', 'jdm_kenya_testing'], true)) {
    fwrite(STDERR, "E2E fixtures are restricted to the allowlisted local JDM databases.\n");
    exit(1);
}

$payload = json_decode((string) stream_get_contents(STDIN), true);
$email = is_array($payload) ? strtolower(trim((string) ($payload['email'] ?? ''))) : '';
$password = is_array($payload) ? (string) ($payload['password'] ?? '') : '';
$cleanup = ($argv[1] ?? '') === '--cleanup';

if (! preg_match('/^sso-e2e-[a-f0-9]{16}@invalid\.test$/', $email) || strlen($password) < 20) {
    fwrite(STDERR, "Invalid E2E fixture payload.\n");
    exit(1);
}

if ($cleanup) {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT oauth_subject FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $subject = $statement->fetchColumn();
        if (is_string($subject) && $subject !== '') {
            $statement = $pdo->prepare('DELETE FROM oauth_access_tokens WHERE user_subject = ?');
            $statement->execute([$subject]);
            $statement = $pdo->prepare('DELETE FROM oauth_auth_codes WHERE user_subject = ?');
            $statement->execute([$subject]);
        }
        $statement = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $statement->execute([$email]);
        $pdo->commit();
        fwrite(STDOUT, "E2E user removed.\n");
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, "E2E user cleanup failed.\n");
        exit(1);
    }

    exit;
}

$statement = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$statement->execute([$email]);
if ($statement->fetchColumn()) {
    fwrite(STDERR, "E2E user already exists.\n");
    exit(1);
}

$bytes = random_bytes(16);
$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
$subject = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
$now = gmdate('Y-m-d H:i:s');
$statement = $pdo->prepare(
    'INSERT INTO users (oauth_subject, name, email, email_verified_at, whatsapp_phone, password, role, category, is_approved, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
);
$statement->execute([
    $subject,
    'Gacio SSO Test Member',
    $email,
    $now,
    '+254700000000',
    password_hash($password, PASSWORD_DEFAULT),
    'member',
    'other',
    'active',
]);

fwrite(STDOUT, "E2E user created.\n");
