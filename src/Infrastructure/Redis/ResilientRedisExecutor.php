<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Executes Redis operations with graceful degradation when Redis is unavailable.
 * Core writes always go to MySQL; cache/idempotency failures must not break transfers.
 */
final class ResilientRedisExecutor
{
    public function __construct(
        private readonly RedisClientFactory $factory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(Client): T $operation
     */
    public function execute(callable $operation, mixed $fallback = null): mixed
    {
        try {
            return $operation($this->factory->create());
        } catch (\Throwable $exception) {
            $this->logger->warning('Redis unavailable, continuing without cache', [
                'exception' => $exception->getMessage(),
            ]);

            return $fallback;
        }
    }
}
