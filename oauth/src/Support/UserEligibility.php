<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

final class UserEligibility
{
    public static function allowsAuthorization(?array $user): bool
    {
        if (! $user || ($user['account_status'] ?? null) !== 'active') {
            return false;
        }

        return ! in_array($user['category'] ?? null, ['partner', 'missionary'], true) || (bool) ($user['is_approved'] ?? false);
    }
}
