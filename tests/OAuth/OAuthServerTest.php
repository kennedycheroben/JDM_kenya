<?php

declare(strict_types=1);

namespace Jdm\Tests\OAuth;

use DateInterval;
use Jdm\OAuth\Entities\UserEntity;
use Jdm\OAuth\Repositories\AccessTokenRepository;
use Jdm\OAuth\Repositories\AuthCodeRepository;
use Jdm\OAuth\Repositories\ClientRepository;
use Jdm\OAuth\Repositories\NullRefreshTokenRepository;
use Jdm\OAuth\Repositories\ScopeRepository;
use Jdm\OAuth\Support\AuditLogger;
use Jdm\OAuth\Support\DatabaseRateLimiter;
use Jdm\OAuth\Support\IdentityClaims;
use Jdm\OAuth\Support\PendingAuthorization;
use Jdm\OAuth\Support\PkceRequestGuard;
use Jdm\OAuth\Support\UserEligibility;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\ResourceServer;
use PDO;
use PHPUnit\Framework\TestCase;

final class OAuthServerTest extends TestCase
{
    private PDO $pdo;

    private string $privateKeyPath;

    private string $publicKeyPath;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $statement = $this->pdo->prepare('INSERT INTO oauth_clients (id, name, secret_hash, redirect_uri, is_confidential, revoked_at) VALUES (?, ?, ?, ?, 1, NULL)');
        $statement->execute(['gacio-client', 'Gacio Sports', password_hash('client-secret', PASSWORD_DEFAULT), 'https://gacio.test/auth/jdm/callback']);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privatePem));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'jdm-oauth-private-');
        $this->publicKeyPath = tempnam(sys_get_temp_dir(), 'jdm-oauth-public-');
        file_put_contents($this->privateKeyPath, $privatePem);
        file_put_contents($this->publicKeyPath, $details['key']);
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyPath);
        @unlink($this->publicKeyPath);
    }

    public function test_authorization_code_pkce_and_protected_token_signature_succeed(): void
    {
        [$server, $resource] = $this->servers();
        [$code, $verifier] = $this->authorize($server);
        $token = $this->exchange($server, $code, $verifier);

        $validated = $resource->validateAuthenticatedRequest(
            (new ServerRequest([], [], 'https://jdm.test/oauth/userinfo.php', 'GET'))->withHeader('Authorization', 'Bearer '.$token)
        );
        self::assertSame('gacio-client', $validated->getAttribute('oauth_client_id'));
        self::assertSame('11111111-2222-4333-8444-555555555555', $validated->getAttribute('oauth_user_id'));
        self::assertSame(['profile', 'email'], $validated->getAttribute('oauth_scopes'));

        $tampered = substr($token, 0, -1).($token[-1] === 'a' ? 'b' : 'a');
        $this->expectException(OAuthServerException::class);
        $resource->validateAuthenticatedRequest(
            (new ServerRequest([], [], 'https://jdm.test/oauth/userinfo.php', 'GET'))->withHeader('Authorization', 'Bearer '.$tampered)
        );
    }

    public function test_redirect_uri_client_pkce_mismatch_and_code_replay_are_rejected(): void
    {
        [$server] = $this->servers();

        try {
            $this->authorize($server, 'https://evil.test/callback');
            self::fail('Manipulated redirect URI was accepted.');
        } catch (OAuthServerException) {
            self::assertTrue(true);
        }

        $invalidClient = $this->authorizationRequest('https://gacio.test/auth/jdm/callback', str_repeat('v', 64));
        $invalidClient = $invalidClient->withQueryParams($invalidClient->getQueryParams() + ['client_id' => 'missing-client']);
        $query = $invalidClient->getQueryParams();
        $query['client_id'] = 'missing-client';
        $invalidClient = $invalidClient->withQueryParams($query);
        $this->expectOAuthFailure(fn () => $server->validateAuthorizationRequest($invalidClient));

        [$code, $verifier] = $this->authorize($server);
        $this->expectOAuthFailure(fn () => $this->exchange($server, $code, str_repeat('x', 64)));
        $token = $this->exchange($server, $code, $verifier);
        self::assertNotSame('', $token);
        $this->expectOAuthFailure(fn () => $this->exchange($server, $code, $verifier));
    }

    public function test_expired_authorization_code_is_rejected(): void
    {
        [$server] = $this->servers(1);
        [$code, $verifier] = $this->authorize($server);
        sleep(2);

        $this->expectException(OAuthServerException::class);
        $this->exchange($server, $code, $verifier);
    }

    public function test_s256_is_mandatory_and_only_minimum_claims_are_returned(): void
    {
        foreach ([[], ['code_challenge_method' => 'plain', 'code_challenge' => str_repeat('a', 64)] ] as $query) {
            $this->expectOAuthFailure(fn () => PkceRequestGuard::assertS256($query));
        }

        PkceRequestGuard::assertS256(['code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)]);
        $user = [
            'oauth_subject' => '11111111-2222-4333-8444-555555555555',
            'name' => 'Member',
            'email' => 'member@example.test',
            'email_verified_at' => '2026-08-27 00:00:00',
            'account_status' => 'active',
            'password' => 'must-not-leak',
            'whatsapp_phone' => '+254700000000',
            'role' => 'super_admin',
        ];
        $claims = IdentityClaims::forUser($user, 'https://jdm.test', 'gacio-client', ['profile', 'email']);
        self::assertSame(['iss', 'aud', 'sub', 'account_active', 'name', 'email', 'email_verified'], array_keys($claims));
        self::assertArrayNotHasKey('password', $claims);
        self::assertArrayNotHasKey('whatsapp_phone', $claims);
        self::assertArrayNotHasKey('role', $claims);
    }

    public function test_disabled_unapproved_and_missing_users_are_ineligible(): void
    {
        self::assertFalse(UserEligibility::allowsAuthorization(null));
        self::assertFalse(UserEligibility::allowsAuthorization(['account_status' => 'disabled', 'category' => 'student', 'is_approved' => 1]));
        self::assertFalse(UserEligibility::allowsAuthorization(['account_status' => 'active', 'category' => 'partner', 'is_approved' => 0]));
        self::assertTrue(UserEligibility::allowsAuthorization(['account_status' => 'active', 'category' => 'student', 'is_approved' => 0]));
    }

    public function test_database_rate_limiter_rejects_endpoint_flooding(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $limiter = new DatabaseRateLimiter($this->pdo);

        self::assertTrue($limiter->allow('token', 2, 60));
        self::assertTrue($limiter->allow('token', 2, 60));
        self::assertFalse($limiter->allow('token', 2, 60));
    }

    public function test_pending_authorization_survives_login_and_is_consumed_once(): void
    {
        $_SESSION = [];
        $query = $this->authorizationRequest('https://gacio.test/auth/jdm/callback', str_repeat('v', 64))->getQueryParams();

        PendingAuthorization::store($query);
        $url = PendingAuthorization::consumeUrl('/JDM_kenya');

        self::assertStringStartsWith('/JDM_kenya/oauth/authorize.php?', (string) $url);
        self::assertStringContainsString('client_id=gacio-client', (string) $url);
        self::assertNull(PendingAuthorization::consumeUrl('/JDM_kenya'));
    }

    public function test_consent_approval_and_denial_preserve_state(): void
    {
        [$server] = $this->servers();
        $request = $this->authorizationRequest('https://gacio.test/auth/jdm/callback', str_repeat('v', 64));

        $approved = $server->validateAuthorizationRequest($request);
        $approved->setUser(new UserEntity('11111111-2222-4333-8444-555555555555'));
        $approved->setAuthorizationApproved(true);
        $approvedResponse = $server->completeAuthorizationRequest($approved, new Response());
        parse_str((string) parse_url($approvedResponse->getHeaderLine('Location'), PHP_URL_QUERY), $approvedQuery);
        self::assertArrayHasKey('code', $approvedQuery);
        self::assertSame(str_repeat('s', 64), $approvedQuery['state']);

        $denied = $server->validateAuthorizationRequest($request);
        $denied->setUser(new UserEntity('11111111-2222-4333-8444-555555555555'));
        $denied->setAuthorizationApproved(false);
        try {
            $server->completeAuthorizationRequest($denied, new Response());
            self::fail('Denied consent did not produce an OAuth access_denied response.');
        } catch (OAuthServerException $exception) {
            $deniedResponse = $exception->generateHttpResponse(new Response());
        }
        parse_str((string) parse_url($deniedResponse->getHeaderLine('Location'), PHP_URL_QUERY), $deniedQuery);
        self::assertSame('access_denied', $deniedQuery['error']);
        self::assertSame(str_repeat('s', 64), $deniedQuery['state']);
    }

    public function test_missing_pkce_unknown_client_and_invalid_scope_are_rejected(): void
    {
        [$server] = $this->servers();
        $base = $this->authorizationRequest('https://gacio.test/auth/jdm/callback', str_repeat('v', 64));

        $missingPkce = $base->withQueryParams(array_diff_key($base->getQueryParams(), array_flip(['code_challenge', 'code_challenge_method'])));
        $this->expectOAuthFailure(fn () => PkceRequestGuard::assertS256($missingPkce->getQueryParams()));

        $unknownClient = $base->getQueryParams();
        $unknownClient['client_id'] = 'unknown-client';
        $this->expectOAuthFailure(fn () => $server->validateAuthorizationRequest($base->withQueryParams($unknownClient)));

        $invalidScope = $base->getQueryParams();
        $invalidScope['scope'] = 'profile email private-admin';
        $this->expectOAuthFailure(fn () => $server->validateAuthorizationRequest($base->withQueryParams($invalidScope)));
    }

    public function test_unverified_email_is_reported_without_sensitive_fields(): void
    {
        $claims = IdentityClaims::forUser([
            'oauth_subject' => '11111111-2222-4333-8444-555555555555',
            'name' => 'Member',
            'email' => 'member@example.test',
            'email_verified_at' => null,
            'account_status' => 'active',
            'password' => 'must-not-leak',
            'whatsapp_phone' => '+254700000000',
            'guardian_contact' => 'must-not-leak',
        ], 'https://jdm.test', 'gacio-client', ['profile', 'email']);

        self::assertFalse($claims['email_verified']);
        self::assertSame(['iss', 'aud', 'sub', 'account_active', 'name', 'email', 'email_verified'], array_keys($claims));
    }

    public function test_rate_limits_are_separate_and_audit_records_exclude_credentials(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $limiter = new DatabaseRateLimiter($this->pdo);
        self::assertTrue($limiter->allow('authorize', 1, 60));
        self::assertFalse($limiter->allow('authorize', 1, 60));
        self::assertTrue($limiter->allow('token', 1, 60));

        (new AuditLogger($this->pdo, str_repeat('a', 32)))->record(
            'authorization_approved',
            'gacio-client',
            '11111111-2222-4333-8444-555555555555',
            ['scopes' => ['profile', 'email']],
        );
        $event = $this->pdo->query('SELECT metadata, ip_hash FROM oauth_audit_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('{"scopes":["profile","email"]}', $event['metadata']);
        self::assertSame(64, strlen($event['ip_hash']));
        self::assertStringNotContainsString('secret', $event['metadata']);
        self::assertStringNotContainsString('token', $event['metadata']);
    }

    private function servers(int $authorizationCodeTtl = 90): array
    {
        $clients = new ClientRepository($this->pdo);
        $tokens = new AccessTokenRepository($this->pdo);
        $server = new AuthorizationServer($clients, $tokens, new ScopeRepository(), $this->privateKeyPath, str_repeat('e', 32));
        $server->enableGrantType(
            new AuthCodeGrant(new AuthCodeRepository($this->pdo), new NullRefreshTokenRepository(), new DateInterval('PT'.$authorizationCodeTtl.'S')),
            new DateInterval('PT300S')
        );

        return [$server, new ResourceServer($tokens, $this->publicKeyPath)];
    }

    private function authorize(AuthorizationServer $server, string $redirectUri = 'https://gacio.test/auth/jdm/callback'): array
    {
        $verifier = str_repeat('v', 64);
        $request = $this->authorizationRequest($redirectUri, $verifier);
        PkceRequestGuard::assertS256($request->getQueryParams());
        $authorization = $server->validateAuthorizationRequest($request);
        $authorization->setUser(new UserEntity('11111111-2222-4333-8444-555555555555'));
        $authorization->setAuthorizationApproved(true);
        $response = $server->completeAuthorizationRequest($authorization, new Response());
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        return [$query['code'], $verifier];
    }

    private function authorizationRequest(string $redirectUri, string $verifier): ServerRequest
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return (new ServerRequest([], [], 'https://jdm.test/oauth/authorize.php', 'GET'))->withQueryParams([
            'response_type' => 'code',
            'client_id' => 'gacio-client',
            'redirect_uri' => $redirectUri,
            'scope' => 'profile email',
            'state' => str_repeat('s', 64),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    private function exchange(AuthorizationServer $server, string $code, string $verifier): string
    {
        $request = (new ServerRequest([], [], 'https://jdm.test/oauth/token.php', 'POST'))->withParsedBody([
            'grant_type' => 'authorization_code',
            'client_id' => 'gacio-client',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://gacio.test/auth/jdm/callback',
            'code' => $code,
            'code_verifier' => $verifier,
        ]);
        $response = $server->respondToAccessTokenRequest($request, new Response());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['access_token'];
    }

    private function expectOAuthFailure(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected OAuth request to fail.');
        } catch (OAuthServerException) {
            self::assertTrue(true);
        }
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE oauth_clients (id TEXT PRIMARY KEY, name TEXT NOT NULL, secret_hash TEXT, redirect_uri TEXT NOT NULL, is_confidential INTEGER NOT NULL, revoked_at TEXT NULL)');
        $this->pdo->exec('CREATE TABLE oauth_auth_codes (identifier_hash TEXT PRIMARY KEY, user_subject TEXT NOT NULL, client_id TEXT NOT NULL, scopes TEXT NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL, created_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE oauth_access_tokens (identifier_hash TEXT PRIMARY KEY, user_subject TEXT NOT NULL, client_id TEXT NOT NULL, scopes TEXT NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL, created_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE oauth_rate_limits (bucket_key TEXT PRIMARY KEY, window_started_at TEXT NOT NULL, hits INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE oauth_audit_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event_type TEXT NOT NULL, client_id TEXT NULL, user_subject TEXT NULL, ip_hash TEXT NOT NULL, metadata TEXT NOT NULL, created_at TEXT NOT NULL)');
    }
}
