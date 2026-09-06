<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

final class PendingAuthorization
{
    private const SESSION_KEY = 'oauth_pending_authorization';

    public static function store(array $query): void
    {
        $_SESSION[self::SESSION_KEY] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function consumeUrl(string $basePath): ?string
    {
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if (! is_string($pending) || $pending === '' || str_contains($pending, "\r") || str_contains($pending, "\n")) {
            return null;
        }

        parse_str($pending, $query);
        foreach (['response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method'] as $required) {
            if (! isset($query[$required]) || ! is_string($query[$required]) || $query[$required] === '') {
                return null;
            }
        }

        if ($query['response_type'] !== 'code' || $query['code_challenge_method'] !== 'S256') {
            return null;
        }

        return rtrim($basePath, '/').'/oauth/authorize.php?'.$pending;
    }
}
