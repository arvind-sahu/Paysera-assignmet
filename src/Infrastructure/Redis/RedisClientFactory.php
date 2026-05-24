<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Predis\Client;

final class RedisClientFactory
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $redisUrl,
    ) {
    }

    public function create(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->redisUrl);
        }

        return $this->client;
    }
}
