<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Enriches each log record with request correlation fields for centralized logging.
 */
final class RequestContextProcessor
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $record;
        }

        $requestId = $request->headers->get('X-Request-Id');
        if ($requestId === null || $requestId === '') {
            $requestId = bin2hex(random_bytes(16));
            $request->headers->set('X-Request-Id', $requestId);
        }

        return $record->with(extra: array_merge($record->extra, [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'client_ip' => $request->getClientIp(),
            'api_key' => $request->headers->get('X-Api-Key'),
            'idempotency_key' => $request->headers->get('Idempotency-Key'),
        ]));
    }
}
