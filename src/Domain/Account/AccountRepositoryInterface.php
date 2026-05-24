<?php

declare(strict_types=1);

namespace App\Domain\Account;

interface AccountRepositoryInterface
{
    public function findById(string $id): ?Account;

    /**
     * Loads accounts with pessimistic write locks for transfer integrity.
     *
     * @param list<string> $ids
     * @return array<string, Account> keyed by account id
     */
    public function findByIdsForUpdate(array $ids): array;

    public function save(Account $account): void;
}
