<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/core/db_connect.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

use Jdm\OAuth\Entities\UserEntity;
use Jdm\OAuth\Support\Http;
use Jdm\OAuth\Support\OAuthServices;
use Jdm\OAuth\Support\PendingAuthorization;
use Jdm\OAuth\Support\PkceRequestGuard;
use Jdm\OAuth\Support\UserEligibility;
use Laminas\Diactoros\Response;
use League\OAuth2\Server\Exception\OAuthServerException;

try {
    $services = OAuthServices::create($pdo);
    if (! $services->rateLimiter->allow('authorize', 30, 60)) {
        Http::safeError(429);
    }

    $request = Http::request();
    $query = $request->getQueryParams();
    PkceRequestGuard::assertS256($query);

    $authorizationRequest = $services->authorizationServer->validateAuthorizationRequest($request);
    $client = $authorizationRequest->getClient();

    if (empty($_SESSION['user_id'])) {
        PendingAuthorization::store($query);
        header('Location: '.BASE_PATH.'/login.php', true, 302);
        exit;
    }

    try {
        $statement = $pdo->prepare('SELECT id, oauth_subject, account_status, category, is_approved FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $_SESSION['user_id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $statement = $pdo->prepare('SELECT id, category, is_approved FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $_SESSION['user_id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user['oauth_subject'] = null;
            $user['account_status'] = 'active';
        }
    }
    if (! UserEligibility::allowsAuthorization($user ?: null)) {
        $services->audit->record('authorization_denied_ineligible', $client->getIdentifier(), $user['oauth_subject'] ?? null);
        Http::safeError(403);
    }

    if (empty($user['oauth_subject'])) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $user['oauth_subject'] = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        $statement = $pdo->prepare('UPDATE users SET oauth_subject = ? WHERE id = ? AND oauth_subject IS NULL');
        $statement->execute([$user['oauth_subject'], $user['id']]);
        if ($statement->rowCount() !== 1) {
            $statement = $pdo->prepare('SELECT oauth_subject FROM users WHERE id = ? LIMIT 1');
            $statement->execute([$user['id']]);
            $user['oauth_subject'] = (string) $statement->fetchColumn();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (! require_csrf()) {
            Http::safeError(419);
        }

        $approved = isset($_POST['decision']) && hash_equals('approve', (string) $_POST['decision']);
        $authorizationRequest->setUser(new UserEntity($user['oauth_subject']));
        $authorizationRequest->setAuthorizationApproved($approved);
        unset($_SESSION['oauth_pending_authorization']);
        $services->audit->record($approved ? 'authorization_approved' : 'authorization_denied', $client->getIdentifier(), $user['oauth_subject']);
        Http::emit($services->authorizationServer->completeAuthorizationRequest($authorizationRequest, new Response()));
    }

    $scopes = array_map(static fn ($scope): string => $scope->getIdentifier(), $authorizationRequest->getScopes());
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    require __DIR__.'/views/consent.php';
} catch (OAuthServerException $exception) {
    Http::oauthError($exception);
} catch (Throwable $exception) {
    error_log('OAuth authorize error: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    Http::logUnexpected($exception);
    Http::safeError(503);
}
