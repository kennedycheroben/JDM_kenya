<?php

declare(strict_types=1);

namespace Jdm\OAuth\Repositories;

use Jdm\OAuth\Entities\ClientEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PDO;

final class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $statement = $this->pdo->prepare('SELECT id, name, redirect_uri, is_confidential FROM oauth_clients WHERE id = ? AND revoked_at IS NULL LIMIT 1');
        $statement->execute([$clientIdentifier]);
        $client = $statement->fetch(PDO::FETCH_ASSOC);

        return $client
            ? new ClientEntity($client['id'], $client['name'], $client['redirect_uri'], (bool) $client['is_confidential'])
            : null;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if ($grantType !== null && $grantType !== 'authorization_code') {
            return false;
        }

        $statement = $this->pdo->prepare('SELECT secret_hash, is_confidential FROM oauth_clients WHERE id = ? AND revoked_at IS NULL LIMIT 1');
        $statement->execute([$clientIdentifier]);
        $client = $statement->fetch(PDO::FETCH_ASSOC);

        if (! $client) {
            return false;
        }

        if (! (bool) $client['is_confidential']) {
            return $clientSecret === null || $clientSecret === '';
        }

        return is_string($clientSecret) && $clientSecret !== '' && password_verify($clientSecret, $client['secret_hash']);
    }
}
