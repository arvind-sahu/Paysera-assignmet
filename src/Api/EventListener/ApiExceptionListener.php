<?php

declare(strict_types=1);

namespace App\Api\EventListener;

use App\Domain\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $previous = $throwable->getPrevious();

        if ($previous instanceof ValidationFailedException) {
            $violations = [];
            foreach ($previous->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            $event->setResponse(new JsonResponse([
                'error' => 'validation_failed',
                'violations' => $violations,
            ], Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        if ($throwable instanceof DomainException) {
            $status = match ($throwable->errorCode) {
                'ACCOUNT_NOT_FOUND', 'TRANSFER_NOT_FOUND' => Response::HTTP_NOT_FOUND,
                'INSUFFICIENT_FUNDS', 'SAME_ACCOUNT', 'INVALID_AMOUNT', 'INVALID_CURRENCY', 'CURRENCY_MISMATCH', 'INVALID_STATUS' => Response::HTTP_UNPROCESSABLE_ENTITY,
                default => Response::HTTP_BAD_REQUEST,
            };

            $event->setResponse(new JsonResponse([
                'error' => $throwable->errorCode,
                'message' => $throwable->getMessage(),
            ], $status));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return;
        }

        $this->logger->error('Unhandled API exception', [
            'exception' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);

        $event->setResponse(new JsonResponse([
            'error' => 'internal_error',
            'message' => 'An unexpected error occurred.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR));
    }
}
