<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use League\OAuth2\Server\Exception\OAuthServerException;

final class PkceRequestGuard
{
    public static function assertS256(array $query): void
    {
        if (($query['code_challenge_method'] ?? '') !== 'S256' || ! preg_match('/^[A-Za-z0-9_-]{43,128}$/', (string) ($query['code_challenge'] ?? ''))) {
            throw OAuthServerException::invalidRequest('code_challenge', 'S256 PKCE is required.');
        }
    }
}
