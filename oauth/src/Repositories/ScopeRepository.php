<?php

declare(strict_types=1);

namespace Jdm\OAuth\Repositories;

use Jdm\OAuth\Entities\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

final class ScopeRepository implements ScopeRepositoryInterface
{
    private const ALLOWED = ['profile', 'email'];

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return in_array($identifier, self::ALLOWED, true) ? new ScopeEntity($identifier) : null;
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        return array_values(array_filter(
            $scopes,
            static fn (ScopeEntityInterface $scope): bool => in_array($scope->getIdentifier(), self::ALLOWED, true)
        ));
    }
}
