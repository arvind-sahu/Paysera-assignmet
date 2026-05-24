<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\Shared\PaginatedResult;

interface TransferRepositoryInterface
{
    public function findByReference(string $reference): ?Transfer;

    public function findByIdempotencyKey(string $key): ?Transfer;

  /**
     * @return PaginatedResult<Transfer>
     */
    public function findByCriteria(TransferListCriteria $criteria): PaginatedResult;

    public function save(Transfer $transfer): void;
}
