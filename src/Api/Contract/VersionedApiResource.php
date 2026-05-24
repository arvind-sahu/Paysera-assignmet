<?php

declare(strict_types=1);

namespace App\Api\Contract;

/**
 * Marker for versioned API resource controllers (transfers, users, products, orders).
 * Implement in V1/V2 controllers to keep a consistent extension pattern.
 */
interface VersionedApiResource
{
    public static function resourceName(): string;
}
