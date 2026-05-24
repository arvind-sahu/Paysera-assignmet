<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Transfer\ListTransfersHandler;
use App\Application\Transfer\ListTransfersQuery;
use App\Domain\Account\Account;
use App\Domain\Account\AccountRepositoryInterface;
use App\Domain\Exception\TransferException;
use App\Domain\Shared\PaginatedResult;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferListCriteria;
use App\Domain\Transfer\TransferRepositoryInterface;
use App\Domain\Transfer\TransferStatus;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ListTransfersHandlerTest extends TestCase
{
    public function testListsTransfersWithoutAccountFilter(): void
    {
        $transfer = $this->createTransfer();
        $paginated = new PaginatedResult([$transfer], 1, 1, 20);

        $transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $transferRepository->expects(self::once())
            ->method('findByCriteria')
            ->with(self::callback(function (TransferListCriteria $criteria): bool {
                return $criteria->accountId === null
                    && $criteria->status === null
                    && $criteria->fromDate === null
                    && $criteria->page === 1
                    && $criteria->limit === 20;
            }))
            ->willReturn($paginated);

        $accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $accountRepository->expects(self::never())->method('findById');

        $handler = new ListTransfersHandler($transferRepository, $accountRepository);
        $result = $handler->handle(new ListTransfersQuery());

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->items);
    }

    public function testThrowsWhenAccountDoesNotExist(): void
    {
        $accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $accountRepository->method('findById')->willReturn(null);

        $transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $transferRepository->expects(self::never())->method('findByCriteria');

        $handler = new ListTransfersHandler($transferRepository, $accountRepository);

        $this->expectException(TransferException::class);
        $handler->handle(new ListTransfersQuery(accountId: '11111111-1111-4111-8111-111111111111'));
    }

    public function testAppliesRecentDaysFilter(): void
    {
        $transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $transferRepository->expects(self::once())
            ->method('findByCriteria')
            ->with(self::callback(function (TransferListCriteria $criteria): bool {
                return $criteria->fromDate !== null;
            }))
            ->willReturn(new PaginatedResult([], 0, 1, 20));

        $accountRepository = $this->createMock(AccountRepositoryInterface::class);

        $handler = new ListTransfersHandler($transferRepository, $accountRepository);
        $handler->handle(new ListTransfersQuery(days: 30));
    }

    private function createTransfer(): Transfer
    {
        return new Transfer(
            'transfer-id',
            '019262e0-7c8a-7000-8000-000000000001',
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            Money::fromMajor('10.00', 'EUR'),
            TransferStatus::Completed,
            createdAt: new \DateTimeImmutable('-1 day'),
            completedAt: new \DateTimeImmutable('-1 day'),
        );
    }
}
