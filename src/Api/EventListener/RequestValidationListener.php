<?php

declare(strict_types=1);

namespace App\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestValidationListener implements EventSubscriberInterface
{
    private const IDEMPOTENCY_PATTERN = '/^[A-Za-z0-9:_-]{8,64}$/';
    private const API_KEY_PATTERN = '/^[A-Za-z0-9._-]{8,128}$/';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 25]];
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

        $apiKey = $request->headers->get('X-Api-Key');
        if ($apiKey !== null && !preg_match(self::API_KEY_PATTERN, $apiKey)) {
            $event->setResponse(new JsonResponse([
                'error' => 'invalid_api_key_format',
                'message' => 'X-Api-Key contains invalid characters.',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        if (!$request->isMethod('POST')) {
            return;
        }

        $path = $request->getPathInfo();
        if ($path !== '/api/v1/transfers' && $path !== '/api/v2/transfers') {
            return;
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (!str_starts_with(strtolower($contentType), 'application/json')) {
            $event->setResponse(new JsonResponse([
                'error' => 'invalid_content_type',
                'message' => 'Content-Type must be application/json.',
            ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE));

            return;
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if ($idempotencyKey === null || $idempotencyKey === '') {
            $event->setResponse(new JsonResponse([
                'error' => 'missing_idempotency_key',
                'message' => 'Idempotency-Key header is required.',
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        if (!preg_match(self::IDEMPOTENCY_PATTERN, $idempotencyKey)) {
            $event->setResponse(new JsonResponse([
                'error' => 'invalid_idempotency_key',
                'message' => 'Idempotency-Key must be 8-64 chars using letters, numbers, colon, underscore, or hyphen.',
            ], Response::HTTP_BAD_REQUEST));
        }
    }
}
