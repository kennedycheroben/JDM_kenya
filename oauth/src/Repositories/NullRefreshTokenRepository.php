<?php

declare(strict_types=1);

namespace Jdm\OAuth\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

final class NullRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return null;
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        throw new \LogicException('Refresh tokens are disabled.');
    }

    public function revokeRefreshToken(string $tokenId): void {}

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return true;
    }
}
