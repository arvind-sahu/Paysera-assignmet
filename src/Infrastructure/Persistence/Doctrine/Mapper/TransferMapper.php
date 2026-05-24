<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferStatus;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Persistence\Doctrine\Entity\TransferEntity;

final class TransferMapper
{
    public function toDomain(TransferEntity $entity): Transfer
    {
        return new Transfer(
            $entity->id,
            $entity->reference,
            $entity->fromAccountId,
            $entity->toAccountId,
            new Money((int) $entity->amountMinor, $entity->currency),
            TransferStatus::from($entity->status),
            $entity->idempotencyKey,
            $entity->failureReason,
            $entity->createdAt,
            $entity->completedAt,
        );
    }

    public function toEntity(Transfer $transfer): TransferEntity
    {
        $entity = new TransferEntity();
        $entity->id = $transfer->id;
        $entity->reference = $transfer->reference;
        $entity->fromAccountId = $transfer->fromAccountId;
        $entity->toAccountId = $transfer->toAccountId;
        $entity->amountMinor = $transfer->amount->amountMinor;
        $entity->currency = $transfer->amount->currency;
        $entity->status = $transfer->status->value;
        $entity->idempotencyKey = $transfer->idempotencyKey;
        $entity->failureReason = $transfer->failureReason;
        $entity->createdAt = $transfer->createdAt;
        $entity->completedAt = $transfer->completedAt;

        return $entity;
    }

    public function syncEntity(TransferEntity $entity, Transfer $transfer): void
    {
        $entity->status = $transfer->status->value;
        $entity->failureReason = $transfer->failureReason;
        $entity->completedAt = $transfer->completedAt;
    }
}
