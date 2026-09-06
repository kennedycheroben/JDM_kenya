<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use PDO;

final readonly class AuditLogger
{
    public function __construct(private PDO $pdo, private string $salt) {}

    public function record(string $event, ?string $clientId = null, ?string $userSubject = null, array $metadata = []): void
    {
        $statement = $this->pdo->prepare('INSERT INTO oauth_audit_events (event_type, client_id, user_subject, ip_hash, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $event,
            $clientId,
            $userSubject,
            hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $this->salt),
            json_encode($metadata, JSON_THROW_ON_ERROR),
            gmdate('Y-m-d H:i:s'),
        ]);
    }
}
