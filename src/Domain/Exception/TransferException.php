<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class TransferException extends DomainException
{
    public static function sameAccount(): self
    {
        return new self('Cannot transfer to the same account.', 'SAME_ACCOUNT');
    }

    public static function accountNotFound(string $accountId): self
    {
        return new self(sprintf('Account not found: %s', $accountId), 'ACCOUNT_NOT_FOUND');
    }

    public static function transferNotFound(string $reference): self
    {
        return new self(sprintf('Transfer not found: %s', $reference), 'TRANSFER_NOT_FOUND');
    }

    public static function accountLocked(string $accountId): self
    {
        return new self(sprintf('Account is temporarily locked: %s', $accountId), 'ACCOUNT_LOCKED');
    }

    public static function invalidStatus(string $status): self
    {
        return new self(
            sprintf('Invalid transfer status: %s. Allowed: pending, completed, failed.', $status),
            'INVALID_STATUS',
        );
    }
}
