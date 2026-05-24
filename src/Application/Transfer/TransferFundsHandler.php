<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\AccountRepositoryInterface;
use App\Domain\Exception\DomainException;
use App\Domain\Exception\InvalidMoneyException;
use App\Domain\Exception\TransferException;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Application\Port\AccountBalanceCacheInterface;
use App\Application\Port\IdempotencyStoreInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class TransferFundsHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransferRepositoryInterface $transferRepository,
        private readonly IdempotencyStoreInterface $idempotencyStore,
        private readonly AccountBalanceCacheInterface $balanceCache,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $transferLogger,
    ) {
    }

    public function handle(TransferFundsCommand $command): TransferFundsResult
    {
        if ($command->fromAccountId === $command->toAccountId) {
            throw TransferException::sameAccount();
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotencyStore->get($command->idempotencyKey);
            if ($cached !== null) {
                $existing = $this->transferRepository->findByReference($cached);
                if ($existing !== null) {
                    $this->transferLogger->info('Idempotent replay', [
                        'reference' => $existing->reference,
                        'idempotency_key' => $command->idempotencyKey,
                    ]);

                    return new TransferFundsResult($existing, wasReplayed: true);
                }
            }

            $dbExisting = $this->transferRepository->findByIdempotencyKey($command->idempotencyKey);
            if ($dbExisting !== null) {
                $this->idempotencyStore->remember($command->idempotencyKey, $dbExisting->reference);

                return new TransferFundsResult($dbExisting, wasReplayed: true);
            }
        }

        $amount = Money::fromMajor($command->amount, $command->currency);

        if ($amount->amountMinor <= 0) {
            throw InvalidMoneyException::negativeAmount();
        }

        $reference = (string) Uuid::v7();
        $transferId = (string) Uuid::v4();

        $transfer = Transfer::createPending(
            $transferId,
            $reference,
            $command->fromAccountId,
            $command->toAccountId,
            $amount,
            $command->idempotencyKey,
        );

        $this->entityManager->wrapInTransaction(function () use ($transfer, $command): void {
            $ids = [$command->fromAccountId, $command->toAccountId];
            sort($ids, SORT_STRING);

            $accounts = $this->accountRepository->findByIdsForUpdate($ids);

            $from = $accounts[$command->fromAccountId] ?? null;
            $to = $accounts[$command->toAccountId] ?? null;

            if ($from === null) {
                throw TransferException::accountNotFound($command->fromAccountId);
            }

            if ($to === null) {
                throw TransferException::accountNotFound($command->toAccountId);
            }

            if (!$from->hasSufficientFunds($transfer->amount)) {
                throw InvalidMoneyException::insufficientFunds();
            }

            $from->debit($transfer->amount);
            $to->credit($transfer->amount);

            $this->accountRepository->save($from);
            $this->accountRepository->save($to);

            $completed = $transfer->complete();
            $this->transferRepository->save($completed);

            $this->balanceCache->invalidate($command->fromAccountId);
            $this->balanceCache->invalidate($command->toAccountId);

            if ($command->idempotencyKey !== null) {
                $this->idempotencyStore->remember($command->idempotencyKey, $completed->reference);
            }

            $this->transferLogger->info('Transfer completed', [
                'reference' => $completed->reference,
                'from' => $command->fromAccountId,
                'to' => $command->toAccountId,
                'amount_minor' => $transfer->amount->amountMinor,
                'currency' => $transfer->amount->currency,
            ]);
        });

        $saved = $this->transferRepository->findByReference($reference);

        return new TransferFundsResult($saved ?? $transfer->complete());
    }
}
