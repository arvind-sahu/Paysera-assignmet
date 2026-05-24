<?php

declare(strict_types=1);

namespace App\Api\Response\V2;

use App\Domain\Shared\PaginatedResult;

/**
 * V2 wraps all payloads in a consistent { data, meta } envelope.
 * Future resources (users, products, orders) should reuse this pattern.
 */
final class ApiEnvelope
{
    public static function single(mixed $data, array $meta = []): array
    {
        return [
            'data' => $data,
            'meta' => array_merge([
                'apiVersion' => 'v2',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ], $meta),
        ];
    }

    /**
     * @param PaginatedResult<mixed> $result
     */
    public static function paginated(array $items, PaginatedResult $result, array $meta = []): array
    {
        return self::single($items, array_merge($meta, [
            'pagination' => [
                'page' => $result->page,
                'limit' => $result->limit,
                'total' => $result->total,
                'totalPages' => $result->totalPages(),
                'hasNextPage' => $result->hasNextPage(),
            ],
        ]));
    }
}
