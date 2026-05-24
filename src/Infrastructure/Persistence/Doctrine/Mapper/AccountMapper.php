<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Account\Account;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;

final class AccountMapper
{
    public function toDomain(AccountEntity $entity): Account
    {
        return new Account(
            $entity->id,
            $entity->accountNumber,
            new Money((int) $entity->balanceMinor, $entity->currency),
            $entity->active,
            $entity->version,
        );
    }

    public function syncEntity(AccountEntity $entity, Account $account): void
    {
        $entity->balanceMinor = $account->balance()->amountMinor;
        $entity->currency = $account->balance()->currency;
        $entity->active = $account->active;
        $entity->updatedAt = new \DateTimeImmutable();
    }
}
