<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Transfer\TransferFundsCommand;
use App\Application\Transfer\TransferFundsHandler;
use App\Domain\Account\Account;
use App\Domain\Account\AccountRepositoryInterface;
use App\Domain\Exception\InvalidMoneyException;
use App\Domain\Exception\TransferException;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferRepositoryInterface;
use App\Domain\Transfer\TransferStatus;
use App\Domain\ValueObject\Money;
use App\Application\Port\AccountBalanceCacheInterface;
use App\Application\Port\IdempotencyStoreInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TransferFundsHandlerTest extends TestCase
{
    public function testRejectsSameAccountTransfer(): void
    {
        $handler = $this->createHandler();

        $this->expectException(TransferException::class);
        $this->expectExceptionMessage('same account');

        $handler->handle(new TransferFundsCommand(
            '11111111-1111-4111-8111-111111111111',
            '11111111-1111-4111-8111-111111111111',
            '10.00',
            'EUR',
        ));
    }

    public function testReturnsCachedIdempotentTransfer(): void
    {
        $existing = Transfer::createPending(
            'tid',
            'ref-123',
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            Money::fromMajor('1.00', 'EUR'),
            'key-abc',
        )->complete();

        $idempotency = $this->createMock(IdempotencyStoreInterface::class);
        $idempotency->method('get')->with('key-abc')->willReturn('ref-123');

        $transferRepo = $this->createMock(TransferRepositoryInterface::class);
        $transferRepo->method('findByReference')->with('ref-123')->willReturn($existing);

        $handler = $this->createHandler(
            idempotencyStore: $idempotency,
            transferRepository: $transferRepo,
        );

        $result = $handler->handle(new TransferFundsCommand(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            '1.00',
            'EUR',
            'key-abc',
        ));

        self::assertTrue($result->wasReplayed);
        self::assertSame('ref-123', $result->transfer->reference);
    }

    public function testInsufficientFundsInTransaction(): void
    {
        $from = new Account('a1', 'LT1', Money::fromMajor('1.00', 'EUR'));
        $to = new Account('a2', 'LT2', Money::fromMajor('0.00', 'EUR'));

        $accountRepo = $this->createMock(AccountRepositoryInterface::class);
        $accountRepo->method('findByIdsForUpdate')->willReturn([
            'a1' => $from,
            'a2' => $to,
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $cb) => $cb(),
        );

        $handler = $this->createHandler(accountRepository: $accountRepo, entityManager: $em);

        $this->expectException(InvalidMoneyException::class);

        $handler->handle(new TransferFundsCommand('a1', 'a2', '5.00', 'EUR'));
    }

    private function createHandler(
        ?AccountRepositoryInterface $accountRepository = null,
        ?TransferRepositoryInterface $transferRepository = null,
        ?IdempotencyStoreInterface $idempotencyStore = null,
        ?AccountBalanceCacheInterface $balanceCache = null,
        ?EntityManagerInterface $entityManager = null,
    ): TransferFundsHandler {
        return new TransferFundsHandler(
            $accountRepository ?? $this->createMock(AccountRepositoryInterface::class),
            $transferRepository ?? $this->createMock(TransferRepositoryInterface::class),
            $idempotencyStore ?? $this->createMock(IdempotencyStoreInterface::class),
            $balanceCache ?? $this->createMock(AccountBalanceCacheInterface::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );
    }
}
