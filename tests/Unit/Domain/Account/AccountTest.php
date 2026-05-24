<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account;

use App\Domain\Account\Account;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testDebitAndCreditUpdateBalance(): void
    {
        $account = new Account(
            'id',
            'LT0001',
            Money::fromMajor('100.00', 'EUR'),
        );

        $account->debit(Money::fromMajor('25.50', 'EUR'));
        self::assertSame('74.50', $account->balance()->toMajorString());

        $account->credit(Money::fromMajor('10.00', 'EUR'));
        self::assertSame('84.50', $account->balance()->toMajorString());
    }

    public function testHasSufficientFunds(): void
    {
        $account = new Account('id', 'LT0001', Money::fromMajor('10.00', 'EUR'));

        self::assertTrue($account->hasSufficientFunds(Money::fromMajor('10.00', 'EUR')));
        self::assertFalse($account->hasSufficientFunds(Money::fromMajor('10.01', 'EUR')));
    }
}
