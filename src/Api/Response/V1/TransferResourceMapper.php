<?php

declare(strict_types=1);

namespace App\Api\Response\V1;

use App\Application\Transfer\TransferFundsResult;
use App\Domain\Shared\PaginatedResult;
use App\Domain\Transfer\Transfer;

final class TransferResourceMapper
{
    public function fromTransfer(Transfer $transfer, bool $replayed = false): array
    {
        return [
            'reference' => $transfer->reference,
            'status' => $transfer->status->value,
            'fromAccountId' => $transfer->fromAccountId,
            'toAccountId' => $transfer->toAccountId,
            'amount' => $transfer->amount->toMajorString(),
            'currency' => $transfer->amount->currency,
            'failureReason' => $transfer->failureReason,
            'createdAt' => $transfer->createdAt->format(\DateTimeInterface::ATOM),
            'completedAt' => $transfer->completedAt?->format(\DateTimeInterface::ATOM),
            'replayed' => $replayed,
        ];
    }

    public function fromCreateResult(TransferFundsResult $result): array
    {
        $payload = $this->fromTransfer($result->transfer, $result->wasReplayed);
        unset($payload['failureReason'], $payload['createdAt']);

        return $payload;
    }

    /**
     * @param PaginatedResult<Transfer> $result
     */
    public function fromPaginatedResult(PaginatedResult $result): array
    {
        return [
            'items' => array_map(
                fn (Transfer $transfer) => $this->fromTransfer($transfer),
                $result->items,
            ),
            'pagination' => [
                'page' => $result->page,
                'limit' => $result->limit,
                'total' => $result->total,
                'totalPages' => $result->totalPages(),
                'hasNextPage' => $result->hasNextPage(),
            ],
        ];
    }
}
