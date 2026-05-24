<?php

declare(strict_types=1);

namespace App\Api\Response\V2;

use App\Application\Transfer\TransferFundsResult;
use App\Domain\Shared\PaginatedResult;
use App\Domain\Transfer\Transfer;

final class TransferResourceMapper
{
    public function fromTransfer(Transfer $transfer, bool $replayed = false, ?string $description = null): array
    {
        $payload = [
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

        if ($description !== null) {
            $payload['description'] = $description;
        }

        return $payload;
    }

    public function fromCreateResult(TransferFundsResult $result, ?string $description = null): array
    {
        $payload = $this->fromTransfer($result->transfer, $result->wasReplayed, $description);
        unset($payload['failureReason'], $payload['createdAt']);

        return $payload;
    }

    /**
     * @param PaginatedResult<Transfer> $result
     */
    public function fromPaginatedResult(PaginatedResult $result): array
    {
        $items = array_map(
            fn (Transfer $transfer) => $this->fromTransfer($transfer),
            $result->items,
        );

        return ApiEnvelope::paginated($items, $result);
    }
}
