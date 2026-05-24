<?php

declare(strict_types=1);

namespace App\Domain\Exception;

abstract class DomainException extends \DomainException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
