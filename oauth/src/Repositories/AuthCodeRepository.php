<?php

declare(strict_types=1);

namespace Jdm\OAuth\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Jdm\OAuth\Entities\AuthCodeEntity;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use PDO;
use PDOException;

final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO oauth_auth_codes (identifier_hash, user_subject, client_id, scopes, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([
                self::digest($authCodeEntity->getIdentifier()),
                $authCodeEntity->getUserIdentifier(),
                $authCodeEntity->getClient()->getIdentifier(),
                json_encode(array_map(static fn ($scope): string => $scope->getIdentifier(), $authCodeEntity->getScopes()), JSON_THROW_ON_ERROR),
                $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
                gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }

            throw $exception;
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        $statement = $this->pdo->prepare('UPDATE oauth_auth_codes SET revoked_at = ? WHERE identifier_hash = ? AND revoked_at IS NULL');
        $statement->execute([gmdate('Y-m-d H:i:s'), self::digest($codeId)]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $statement = $this->pdo->prepare('SELECT revoked_at, expires_at FROM oauth_auth_codes WHERE identifier_hash = ? LIMIT 1');
        $statement->execute([self::digest($codeId)]);
        $code = $statement->fetch(PDO::FETCH_ASSOC);

        return ! $code
            || $code['revoked_at'] !== null
            || (new DateTimeImmutable($code['expires_at'], new DateTimeZone('UTC')))->getTimestamp() <= time();
    }

    private static function digest(string $identifier): string
    {
        return hash('sha256', $identifier);
    }
}
