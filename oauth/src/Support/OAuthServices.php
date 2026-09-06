<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use DateInterval;
use Jdm\OAuth\Repositories\AccessTokenRepository;
use Jdm\OAuth\Repositories\AuthCodeRepository;
use Jdm\OAuth\Repositories\ClientRepository;
use Jdm\OAuth\Repositories\NullRefreshTokenRepository;
use Jdm\OAuth\Repositories\ScopeRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\ResourceServer;
use PDO;

final readonly class OAuthServices
{
    private function __construct(
        public OAuthConfig $config,
        public AuthorizationServer $authorizationServer,
        public ResourceServer $resourceServer,
        public AuditLogger $audit,
        public DatabaseRateLimiter $rateLimiter,
    ) {}

    public static function create(PDO $pdo): self
    {
        $config = OAuthConfig::fromEnvironment();
        $clients = new ClientRepository($pdo);
        $scopes = new ScopeRepository();
        $codes = new AuthCodeRepository($pdo);
        $tokens = new AccessTokenRepository($pdo);
        $authorizationServer = new AuthorizationServer($clients, $tokens, $scopes, $config->privateKeyPath, $config->encryptionKey);
        $grant = new AuthCodeGrant($codes, new NullRefreshTokenRepository(), new DateInterval('PT'.$config->authorizationCodeTtl.'S'));
        $authorizationServer->enableGrantType($grant, new DateInterval('PT'.$config->accessTokenTtl.'S'));

        return new self(
            $config,
            $authorizationServer,
            new ResourceServer($tokens, $config->publicKeyPath),
            new AuditLogger($pdo, $config->auditSalt),
            new DatabaseRateLimiter($pdo),
        );
    }
}
