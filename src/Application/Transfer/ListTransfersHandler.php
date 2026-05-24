<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\AccountRepositoryInterface;
use App\Domain\Exception\TransferException;
use App\Domain\Shared\PaginatedResult;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferListCriteria;
use App\Domain\Transfer\TransferRepositoryInterface;
use App\Domain\Transfer\TransferStatus;

final class ListTransfersHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transferRepository,
        private readonly AccountRepositoryInterface $accountRepository,
    ) {
    }

    /**
     * @return PaginatedResult<Transfer>
     */
    public function handle(ListTransfersQuery $query): PaginatedResult
    {
        if ($query->accountId !== null && $this->accountRepository->findById($query->accountId) === null) {
            throw TransferException::accountNotFound($query->accountId);
        }

        $status = $query->status !== null ? TransferStatus::tryFrom($query->status) : null;
        if ($query->status !== null && $status === null) {
            throw TransferException::invalidStatus($query->status);
        }

        $fromDate = null;
        if ($query->days !== null) {
            $fromDate = new \DateTimeImmutable(sprintf('-%d days', $query->days));
        }

        $criteria = new TransferListCriteria(
            accountId: $query->accountId,
            status: $status,
            fromDate: $fromDate,
            page: max(1, $query->page),
            limit: min(max(1, $query->limit), TransferListCriteria::MAX_LIMIT),
        );

        return $this->transferRepository->findByCriteria($criteria);
    }
}
