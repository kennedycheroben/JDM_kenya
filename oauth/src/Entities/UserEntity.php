<?php

declare(strict_types=1);

namespace Jdm\OAuth\Entities;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

final class UserEntity implements UserEntityInterface
{
    use EntityTrait;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }
}
