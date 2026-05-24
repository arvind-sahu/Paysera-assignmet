<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Shared\PaginatedResult;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferListCriteria;
use App\Domain\Transfer\TransferRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\TransferEntity;
use App\Infrastructure\Persistence\Doctrine\Mapper\TransferMapper;
use Doctrine\ORM\EntityManagerInterface;

final class TransferRepository implements TransferRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransferMapper $mapper,
    ) {
    }

    public function findByReference(string $reference): ?Transfer
    {
        $entity = $this->em->getRepository(TransferEntity::class)->findOneBy(['reference' => $reference]);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByIdempotencyKey(string $key): ?Transfer
    {
        $entity = $this->em->getRepository(TransferEntity::class)->findOneBy(['idempotencyKey' => $key]);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByCriteria(TransferListCriteria $criteria): PaginatedResult
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from(TransferEntity::class, 't');

        if ($criteria->accountId !== null) {
            $qb->andWhere('t.fromAccountId = :accountId OR t.toAccountId = :accountId')
                ->setParameter('accountId', $criteria->accountId);
        }

        if ($criteria->status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $criteria->status->value);
        }

        if ($criteria->fromDate !== null) {
            $qb->andWhere('t.createdAt >= :fromDate')
                ->setParameter('fromDate', $criteria->fromDate);
        }

        if ($criteria->toDate !== null) {
            $qb->andWhere('t.createdAt <= :toDate')
                ->setParameter('toDate', $criteria->toDate);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        $sortOrder = strtoupper($criteria->sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $items = $qb
            ->orderBy('t.createdAt', $sortOrder)
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->limit)
            ->getQuery()
            ->getResult();

        $transfers = array_map(
            fn (TransferEntity $entity) => $this->mapper->toDomain($entity),
            $items,
        );

        return new PaginatedResult($transfers, $total, $criteria->page, $criteria->limit);
    }

    public function save(Transfer $transfer): void
    {
        $existing = $this->em->find(TransferEntity::class, $transfer->id);

        if ($existing === null) {
            $this->em->persist($this->mapper->toEntity($transfer));
        } else {
            $this->mapper->syncEntity($existing, $transfer);
        }

        $this->em->flush();
    }
}
