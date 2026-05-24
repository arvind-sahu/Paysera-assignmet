<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidMoneyException;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromMajorParsesDecimals(): void
    {
        $money = Money::fromMajor('10.50', 'eur');

        self::assertSame(1050, $money->amountMinor);
        self::assertSame('EUR', $money->currency);
    }

    public function testSubtractThrowsOnInsufficientFunds(): void
    {
        $this->expectException(InvalidMoneyException::class);
        $this->expectExceptionMessage('Insufficient funds');

        Money::fromMajor('5.00', 'EUR')->subtract(Money::fromMajor('10.00', 'EUR'));
    }

    #[DataProvider('invalidAmountProvider')]
    public function testFromMajorRejectsInvalidInput(string $amount): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::fromMajor($amount, 'EUR');
    }

    public static function invalidAmountProvider(): array
    {
        return [
            ['-1.00'],
            ['abc'],
            ['10.999'],
        ];
    }

    public function testCurrencyMismatchOnAdd(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::fromMajor('1.00', 'EUR')->add(Money::fromMajor('1.00', 'USD'));
    }
}
