<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2).'/core/db_connect.php';

$clientId = $argv[1] ?? '';
if (! is_string($clientId) || ! preg_match('/^jdm_[a-f0-9]{32}$/', $clientId)) {
    fwrite(STDERR, "Usage: php revoke-client.php jdm_<32 hex characters>\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $now = gmdate('Y-m-d H:i:s');
    $statement = $pdo->prepare('UPDATE oauth_clients SET revoked_at = ?, updated_at = ? WHERE id = ? AND revoked_at IS NULL');
    $statement->execute([$now, $now, $clientId]);
    $statement = $pdo->prepare('UPDATE oauth_access_tokens SET revoked_at = ? WHERE client_id = ? AND revoked_at IS NULL');
    $statement->execute([$now, $clientId]);
    $statement = $pdo->prepare('UPDATE oauth_auth_codes SET revoked_at = ? WHERE client_id = ? AND revoked_at IS NULL');
    $statement->execute([$now, $clientId]);
    $pdo->commit();
    fwrite(STDOUT, "Client and outstanding grants revoked.\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "Client revocation failed.\n");
    exit(1);
}
