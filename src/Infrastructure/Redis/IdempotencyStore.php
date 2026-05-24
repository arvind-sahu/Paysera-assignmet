<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use App\Application\Port\IdempotencyStoreInterface;

/**
 * Fast path for idempotent transfer replays under high load.
 */
final class IdempotencyStore implements IdempotencyStoreInterface
{
    private const PREFIX = 'idempotency:';

    public function __construct(
        private readonly ResilientRedisExecutor $redis,
        private readonly int $ttlSeconds,
    ) {
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->execute(
            fn ($client) => $client->get(self::PREFIX . $key),
        );

        return is_string($value) ? $value : null;
    }

    public function remember(string $key, string $transferReference): void
    {
        $this->redis->execute(function ($client) use ($key, $transferReference): void {
            $client->setex(
                self::PREFIX . $key,
                $this->ttlSeconds,
                $transferReference,
            );
        });
    }
}
