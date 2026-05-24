<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transfers')]
#[ORM\UniqueConstraint(name: 'uniq_transfer_reference', columns: ['reference'])]
#[ORM\UniqueConstraint(name: 'uniq_idempotency_key', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_from_account', columns: ['from_account_id'])]
#[ORM\Index(name: 'idx_to_account', columns: ['to_account_id'])]
#[ORM\Index(name: 'idx_transfers_created_at', columns: ['created_at'])]
class TransferEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $reference;

    #[ORM\Column(name: 'from_account_id', type: 'string', length: 36)]
    public string $fromAccountId;

    #[ORM\Column(name: 'to_account_id', type: 'string', length: 36)]
    public string $toAccountId;

    #[ORM\Column(name: 'amount_minor', type: 'bigint')]
    public int $amountMinor;

    #[ORM\Column(type: 'string', length: 3)]
    public string $currency;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 64, nullable: true)]
    public ?string $idempotencyKey = null;

    #[ORM\Column(name: 'failure_reason', type: 'string', length: 255, nullable: true)]
    public ?string $failureReason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;
}
