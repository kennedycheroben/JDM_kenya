<?php

declare(strict_types=1);

namespace Jdm\OAuth\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

final class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    public function __construct(string $identifier, string $name, string $redirectUri, bool $confidential)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUri;
        $this->isConfidential = $confidential;
    }
}
