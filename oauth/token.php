<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/core/db_connect.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

use Jdm\OAuth\Support\Http;
use Jdm\OAuth\Support\OAuthServices;
use Laminas\Diactoros\Response;
use League\OAuth2\Server\Exception\OAuthServerException;

$lockName = null;
$releaseLock = static function () use (&$lockName, &$pdo): void {
    if ($lockName === null) {
        return;
    }

    try {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    } catch (Throwable) {
    } finally {
        $lockName = null;
    }
};

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Http::safeError(405);
    }

    $services = OAuthServices::create($pdo);
    if (! $services->rateLimiter->allow('token', 20, 60)) {
        Http::safeError(429);
    }

    $request = Http::request();
    $body = $request->getParsedBody();
    $code = is_array($body) ? (string) ($body['code'] ?? '') : '';
    if ($code === '') {
        throw OAuthServerException::invalidRequest('code');
    }

    $lockName = 'jdm_oauth_code_'.hash('sha256', $code);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        Http::safeError(429);
    }

    $response = $services->authorizationServer->respondToAccessTokenRequest($request, new Response());
    $services->audit->record('token_issued', is_array($body) ? (string) ($body['client_id'] ?? '') : null);
    $releaseLock();
    Http::emit($response->withHeader('Cache-Control', 'no-store')->withHeader('Pragma', 'no-cache'));
} catch (OAuthServerException $exception) {
    $releaseLock();
    Http::oauthError($exception);
} catch (Throwable $exception) {
    $releaseLock();
    Http::logUnexpected($exception);
    Http::safeError(503);
} finally {
    $releaseLock();
}
