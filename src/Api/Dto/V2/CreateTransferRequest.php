<?php

declare(strict_types=1);

namespace App\Api\Dto\V2;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTransferRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $fromAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $toAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Amount must be a positive decimal with up to 2 fractional digits.')]
    public ?string $amount = null;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Currency must be a 3-letter ISO 4217 code.')]
    public ?string $currency = null;

    #[Assert\Length(max: 255)]
    public ?string $description = null;
}
