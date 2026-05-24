<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidMoneyException;

/**
 * Immutable money in minor units (cents) to avoid floating-point errors.
 */
final readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
        if ($amountMinor < 0) {
            throw InvalidMoneyException::negativeAmount();
        }

        if ($currency === '' || strlen($currency) !== 3) {
            throw InvalidMoneyException::invalidCurrency($currency);
        }
    }

    public static function fromMajor(string $amount, string $currency): self
    {
        if (!is_numeric($amount)) {
            throw InvalidMoneyException::invalidAmount($amount);
        }

        $parts = explode('.', $amount);
        $major = (int) $parts[0];

        if (isset($parts[1]) && strlen($parts[1]) > 2) {
            throw InvalidMoneyException::tooManyDecimalPlaces();
        }

        $fraction = isset($parts[1]) ? str_pad(substr($parts[1], 0, 2), 2, '0') : '00';

        $minor = $major * 100 + (int) $fraction;

        return new self($minor, strtoupper($currency));
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($this->amountMinor < $other->amountMinor) {
            throw InvalidMoneyException::insufficientFunds();
        }

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor > $other->amountMinor;
    }

    public function equals(Money $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }

    public function toMajorString(): string
    {
        $major = intdiv($this->amountMinor, 100);
        $minor = $this->amountMinor % 100;

        return sprintf('%d.%02d', $major, $minor);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
    }
}
