<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use App\Application\Port\AccountBalanceCacheInterface;
use App\Domain\ValueObject\Money;

/**
 * Read-through cache for account balance lookups (not used during writes).
 */
final class AccountBalanceCache implements AccountBalanceCacheInterface
{
    private const PREFIX = 'balance:';

    public function __construct(
        private readonly ResilientRedisExecutor $redis,
        private readonly int $ttlSeconds,
    ) {
    }

    public function get(string $accountId): ?Money
    {
        $raw = $this->redis->execute(
            fn ($client) => $client->get(self::PREFIX . $accountId),
        );

        if (!is_string($raw)) {
            return null;
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new Money((int) $data['amount_minor'], $data['currency']);
    }

    public function set(string $accountId, Money $balance): void
    {
        $payload = json_encode([
            'amount_minor' => $balance->amountMinor,
            'currency' => $balance->currency,
        ], JSON_THROW_ON_ERROR);

        $this->redis->execute(function ($client) use ($accountId, $payload): void {
            $client->setex(self::PREFIX . $accountId, $this->ttlSeconds, $payload);
        });
    }

    public function invalidate(string $accountId): void
    {
        $this->redis->execute(function ($client) use ($accountId): void {
            $client->del([self::PREFIX . $accountId]);
        });
    }
}
