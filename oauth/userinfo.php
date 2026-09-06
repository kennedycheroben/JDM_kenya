<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/core/db_connect.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

use Jdm\OAuth\Support\Http;
use Jdm\OAuth\Support\IdentityClaims;
use Jdm\OAuth\Support\OAuthServices;
use Laminas\Diactoros\Response\JsonResponse;
use League\OAuth2\Server\Exception\OAuthServerException;

try {
    $services = OAuthServices::create($pdo);
    if (! $services->rateLimiter->allow('userinfo', 60, 60)) {
        Http::safeError(429);
    }

    $request = $services->resourceServer->validateAuthenticatedRequest(Http::request());
    $subject = (string) $request->getAttribute('oauth_user_id');
    $clientId = (string) $request->getAttribute('oauth_client_id');
    $scopes = (array) $request->getAttribute('oauth_scopes');

    $statement = $pdo->prepare('SELECT oauth_subject, name, email, email_verified_at, account_status FROM users WHERE oauth_subject = ? LIMIT 1');
    $statement->execute([$subject]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if (! $user || $user['account_status'] !== 'active') {
        $services->audit->record('userinfo_denied_inactive', $clientId, $subject);
        Http::safeError(403);
    }

    $claims = IdentityClaims::forUser($user, $services->config->issuer, $clientId, $scopes);

    $services->audit->record('userinfo_returned', $clientId, $subject, ['scopes' => array_values($scopes)]);
    Http::emit((new JsonResponse($claims))->withHeader('Cache-Control', 'no-store')->withHeader('Pragma', 'no-cache'));
} catch (OAuthServerException $exception) {
    Http::oauthError($exception);
} catch (Throwable $exception) {
    Http::logUnexpected($exception);
    Http::safeError(503);
}
