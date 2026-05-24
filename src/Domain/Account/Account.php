<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\ValueObject\Money;

/**
 * Domain model — persistence mapping lives in Infrastructure layer.
 */
final class Account
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountNumber,
        private Money $balance,
        public readonly bool $active = true,
        public readonly int $version = 0,
    ) {
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function debit(Money $amount): void
    {
        if (!$this->active) {
            throw new \DomainException('Account is inactive.');
        }

        $this->balance = $this->balance->subtract($amount);
    }

    public function credit(Money $amount): void
    {
        if (!$this->active) {
            throw new \DomainException('Account is inactive.');
        }

        $this->balance = $this->balance->add($amount);
    }

    public function hasSufficientFunds(Money $amount): bool
    {
        return $this->balance->amountMinor >= $amount->amountMinor
            && $this->balance->currency === $amount->currency;
    }

    public function withBalance(Money $balance): self
    {
        return new self(
            $this->id,
            $this->accountNumber,
            $balance,
            $this->active,
            $this->version,
        );
    }
}
