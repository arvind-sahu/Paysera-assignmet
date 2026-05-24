<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\ValueObject\Money;

final class Transfer
{
    public function __construct(
        public readonly string $id,
        public readonly string $reference,
        public readonly string $fromAccountId,
        public readonly string $toAccountId,
        public readonly Money $amount,
        public TransferStatus $status,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $failureReason = null,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public readonly ?\DateTimeImmutable $completedAt = null,
    ) {
    }

    public static function createPending(
        string $id,
        string $reference,
        string $fromAccountId,
        string $toAccountId,
        Money $amount,
        ?string $idempotencyKey = null,
    ): self {
        return new self(
            $id,
            $reference,
            $fromAccountId,
            $toAccountId,
            $amount,
            TransferStatus::Pending,
            $idempotencyKey,
        );
    }

    public function complete(): self
    {
        return new self(
            $this->id,
            $this->reference,
            $this->fromAccountId,
            $this->toAccountId,
            $this->amount,
            TransferStatus::Completed,
            $this->idempotencyKey,
            null,
            $this->createdAt,
            new \DateTimeImmutable(),
        );
    }

    public function fail(string $reason): self
    {
        return new self(
            $this->id,
            $this->reference,
            $this->fromAccountId,
            $this->toAccountId,
            $this->amount,
            TransferStatus::Failed,
            $this->idempotencyKey,
            $reason,
            $this->createdAt,
            new \DateTimeImmutable(),
        );
    }
}
