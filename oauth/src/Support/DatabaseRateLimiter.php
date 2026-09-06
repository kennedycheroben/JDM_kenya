<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class DatabaseRateLimiter
{
    public function __construct(private PDO $pdo) {}

    public function allow(string $action, int $maximum, int $windowSeconds): bool
    {
        $bucket = hash('sha256', $action.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $initialize = $driver === 'mysql'
            ? 'INSERT IGNORE INTO oauth_rate_limits (bucket_key, window_started_at, hits) VALUES (?, ?, 0)'
            : 'INSERT OR IGNORE INTO oauth_rate_limits (bucket_key, window_started_at, hits) VALUES (?, ?, 0)';
        $statement = $this->pdo->prepare($initialize);
        $statement->execute([$bucket, gmdate('Y-m-d H:i:s')]);
        $this->pdo->beginTransaction();

        try {
            $lockingClause = $driver === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare('SELECT window_started_at, hits FROM oauth_rate_limits WHERE bucket_key = ?'.$lockingClause);
            $statement->execute([$bucket]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $now = time();

            $windowStartedAt = $row
                ? (new DateTimeImmutable($row['window_started_at'], new DateTimeZone('UTC')))->getTimestamp()
                : 0;
            if (! $row || $windowStartedAt <= $now - $windowSeconds) {
                if ($row) {
                    $statement = $this->pdo->prepare('UPDATE oauth_rate_limits SET window_started_at = ?, hits = 1 WHERE bucket_key = ?');
                    $statement->execute([gmdate('Y-m-d H:i:s', $now), $bucket]);
                } else {
                    $statement = $this->pdo->prepare('INSERT INTO oauth_rate_limits (bucket_key, window_started_at, hits) VALUES (?, ?, 1)');
                    $statement->execute([$bucket, gmdate('Y-m-d H:i:s', $now)]);
                }
                $allowed = true;
            } elseif ((int) $row['hits'] >= $maximum) {
                $allowed = false;
            } else {
                $statement = $this->pdo->prepare('UPDATE oauth_rate_limits SET hits = hits + 1 WHERE bucket_key = ?');
                $statement->execute([$bucket]);
                $allowed = true;
            }

            $this->pdo->commit();

            return $allowed;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
