<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Account\Account;
use App\Domain\Account\AccountRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use App\Infrastructure\Persistence\Doctrine\Mapper\AccountMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class AccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountMapper $mapper,
    ) {
    }

    public function findById(string $id): ?Account
    {
        $entity = $this->em->find(AccountEntity::class, $id);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByIdsForUpdate(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AccountEntity::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('a.id', 'ASC');

        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);

        $result = [];
        foreach ($query->getResult() as $entity) {
            assert($entity instanceof AccountEntity);
            $result[$entity->id] = $this->mapper->toDomain($entity);
        }

        return $result;
    }

    public function save(Account $account): void
    {
        $entity = $this->em->find(AccountEntity::class, $account->id);
        if ($entity === null) {
            throw new \RuntimeException('Account entity not found for save.');
        }

        $this->mapper->syncEntity($entity, $account);
        $this->em->flush();
    }
}
