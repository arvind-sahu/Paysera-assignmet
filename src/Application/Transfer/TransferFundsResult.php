<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Transfer\Transfer;

final readonly class TransferFundsResult
{
    public function __construct(
        public Transfer $transfer,
        public bool $wasReplayed = false,
    ) {
    }
}
