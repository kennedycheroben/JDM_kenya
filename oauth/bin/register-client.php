<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2).'/core/db_connect.php';

[$script, $name, $redirectUri] = array_pad($argv, 3, null);
if (! is_string($name) || trim($name) === '' || ! is_string($redirectUri) || filter_var($redirectUri, FILTER_VALIDATE_URL) === false || str_contains($redirectUri, '#')) {
    fwrite(STDERR, "Usage: php {$script} \"Client name\" https://exact.example/auth/callback\n");
    exit(1);
}

$parts = parse_url($redirectUri);
$localAllowed = filter_var(getenv('JDM_OAUTH_ALLOW_INSECURE_LOCAL') ?: 'false', FILTER_VALIDATE_BOOL)
    && ($parts['scheme'] ?? '') === 'http'
    && in_array(strtolower((string) ($parts['host'] ?? '')), ['127.0.0.1', 'localhost'], true);
if (($parts['scheme'] ?? '') !== 'https' && ! $localAllowed) {
    fwrite(STDERR, "Redirect URI must use HTTPS (except explicitly enabled loopback development).\n");
    exit(1);
}

$clientId = 'jdm_'.bin2hex(random_bytes(16));
$clientSecret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$now = gmdate('Y-m-d H:i:s');
$statement = $pdo->prepare('INSERT INTO oauth_clients (id, name, secret_hash, redirect_uri, allowed_scopes, is_confidential, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
$statement->execute([$clientId, trim($name), password_hash($clientSecret, PASSWORD_DEFAULT), $redirectUri, 'profile email', $now, $now]);

fwrite(STDOUT, "Client ID: {$clientId}\nClient secret (shown once): {$clientSecret}\nRedirect URI: {$redirectUri}\n");
