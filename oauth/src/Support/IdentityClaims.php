<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

final class IdentityClaims
{
    public static function forUser(array $user, string $issuer, string $clientId, array $scopes): array
    {
        $claims = [
            'iss' => $issuer,
            'aud' => $clientId,
            'sub' => (string) $user['oauth_subject'],
            'account_active' => ($user['account_status'] ?? null) === 'active',
        ];
        if (in_array('profile', $scopes, true)) {
            $claims['name'] = (string) $user['name'];
        }
        if (in_array('email', $scopes, true)) {
            $claims['email'] = (string) $user['email'];
            $claims['email_verified'] = $user['email_verified_at'] !== null;
        }

        return $claims;
    }
}
