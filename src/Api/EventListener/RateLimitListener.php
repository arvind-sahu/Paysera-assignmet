<?php

declare(strict_types=1);

namespace App\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiLimiter,
        private readonly RateLimiterFactory $transferLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 20]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $key = $request->headers->get('X-Api-Key') ?? $request->getClientIp() ?? 'anonymous';

        try {
            $isTransferCreate = $request->isMethod('POST')
                && ($request->getPathInfo() === '/api/v1/transfers' || $request->getPathInfo() === '/api/v2/transfers');

            $limiterFactory = $isTransferCreate ? $this->transferLimiter : $this->apiLimiter;
            $limiter = $limiterFactory->create($key);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    'error' => 'rate_limit_exceeded',
                    'message' => $isTransferCreate
                        ? 'Too many transactions. Limit is 120 per minute.'
                        : 'Too many requests. Please retry later.',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                ], Response::HTTP_TOO_MANY_REQUESTS));
            }
        } catch (\Throwable) {
            // Fail open: allow traffic when the rate limiter backend is unavailable.
        }
    }
}
