<?php

declare(strict_types=1);

namespace App\Api\Dto\V1;

use Symfony\Component\Validator\Constraints as Assert;

final class ListTransfersRequest
{
    #[Assert\Uuid]
    public ?string $accountId = null;

    #[Assert\Choice(choices: ['pending', 'completed', 'failed'])]
    public ?string $status = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(365)]
    public ?int $days = null;

    #[Assert\Positive]
    public int $page = 1;

    #[Assert\Range(min: 1, max: 100)]
    public int $limit = 20;
}
