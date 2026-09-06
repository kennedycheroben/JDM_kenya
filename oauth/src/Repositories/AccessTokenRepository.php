<?php

declare(strict_types=1);

namespace Jdm\OAuth\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Jdm\OAuth\Entities\AccessTokenEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use PDO;
use PDOException;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }
        if ($userIdentifier !== null) {
            $token->setUserIdentifier($userIdentifier);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO oauth_access_tokens (identifier_hash, user_subject, client_id, scopes, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([
                self::digest($accessTokenEntity->getIdentifier()),
                $accessTokenEntity->getUserIdentifier(),
                $accessTokenEntity->getClient()->getIdentifier(),
                json_encode(array_map(static fn ($scope): string => $scope->getIdentifier(), $accessTokenEntity->getScopes()), JSON_THROW_ON_ERROR),
                $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
                gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }

            throw $exception;
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $statement = $this->pdo->prepare('UPDATE oauth_access_tokens SET revoked_at = ? WHERE identifier_hash = ? AND revoked_at IS NULL');
        $statement->execute([gmdate('Y-m-d H:i:s'), self::digest($tokenId)]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $statement = $this->pdo->prepare('SELECT revoked_at, expires_at FROM oauth_access_tokens WHERE identifier_hash = ? LIMIT 1');
        $statement->execute([self::digest($tokenId)]);
        $token = $statement->fetch(PDO::FETCH_ASSOC);

        return ! $token
            || $token['revoked_at'] !== null
            || (new DateTimeImmutable($token['expires_at'], new DateTimeZone('UTC')))->getTimestamp() <= time();
    }

    private static function digest(string $identifier): string
    {
        return hash('sha256', $identifier);
    }
}
