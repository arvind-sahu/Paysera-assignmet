<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidMoneyException extends DomainException
{
    public static function negativeAmount(): self
    {
        return new self('Amount cannot be negative.', 'INVALID_AMOUNT');
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(sprintf('Invalid currency: %s', $currency), 'INVALID_CURRENCY');
    }

    public static function invalidAmount(string $amount): self
    {
        return new self(sprintf('Invalid amount: %s', $amount), 'INVALID_AMOUNT');
    }

    public static function tooManyDecimalPlaces(): self
    {
        return new self('Amount supports at most 2 decimal places.', 'INVALID_AMOUNT');
    }

    public static function insufficientFunds(): self
    {
        return new self('Insufficient funds.', 'INSUFFICIENT_FUNDS');
    }

    public static function currencyMismatch(string $a, string $b): self
    {
        return new self(sprintf('Currency mismatch: %s vs %s', $a, $b), 'CURRENCY_MISMATCH');
    }
}
