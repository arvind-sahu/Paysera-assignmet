<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\ValueObject\Money;

interface AccountBalanceCacheInterface
{
    public function get(string $accountId): ?Money;

    public function set(string $accountId, Money $balance): void;

    public function invalidate(string $accountId): void;
}
