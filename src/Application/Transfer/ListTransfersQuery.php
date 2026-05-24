<?php

declare(strict_types=1);

namespace App\Application\Transfer;

final class ListTransfersQuery
{
    public function __construct(
        public readonly ?string $accountId = null,
        public readonly ?string $status = null,
        public readonly ?int $days = null,
        public readonly int $page = 1,
        public readonly int $limit = 20,
    ) {
    }
}
