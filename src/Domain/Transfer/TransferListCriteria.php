<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

final class TransferListCriteria
{
    public const MAX_LIMIT = 100;
    public const DEFAULT_LIMIT = 20;

    public function __construct(
        public readonly ?string $accountId = null,
        public readonly ?TransferStatus $status = null,
        public readonly ?\DateTimeImmutable $fromDate = null,
        public readonly ?\DateTimeImmutable $toDate = null,
        public readonly int $page = 1,
        public readonly int $limit = self::DEFAULT_LIMIT,
        public readonly string $sortOrder = 'desc',
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
