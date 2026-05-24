<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Infrastructure\Redis\RedisClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(
        EntityManagerInterface $em,
        RedisClientFactory $redis,
    ): JsonResponse {
        $checks = ['app' => 'ok', 'database' => 'unknown', 'redis' => 'unknown'];

        try {
            $em->getConnection()->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'error';
        }

        try {
            $redis->create()->ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable) {
            $checks['redis'] = 'error';
        }

        $healthy = !in_array('error', $checks, true);

        return $this->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
