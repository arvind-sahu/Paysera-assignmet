<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
