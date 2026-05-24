<?php

declare(strict_types=1);

namespace App\Application\Port;

interface IdempotencyStoreInterface
{
    public function get(string $key): ?string;

    public function remember(string $key, string $transferReference): void;
}
