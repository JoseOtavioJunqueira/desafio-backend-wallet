<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'cache.idempotency')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** Liveness: is the process up and answering HTTP at all. */
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    /** Readiness: can this instance actually serve traffic (DB + cache reachable). */
    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $healthy = !in_array(false, $checks, true);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $item = $this->cache->getItem('health_check_probe');
            $item->set(true);

            return $this->cache->save($item);
        } catch (\Throwable) {
            return false;
        }
    }
}
