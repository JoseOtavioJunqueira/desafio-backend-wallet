<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Backs the optional `Idempotency-Key` header on `POST /transfer`. A client that times out and
 * retries with the same key gets back the exact response of the original attempt instead of
 * risking a second transfer; entries expire after 24h (config/packages/cache.yaml).
 */
final class IdempotencyGuard
{
    public function __construct(
        #[Autowire(service: 'cache.idempotency')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function find(string $idempotencyKey): ?StoredResponse
    {
        $item = $this->cache->getItem($this->cacheKey($idempotencyKey));

        /** @var StoredResponse|null $value */
        $value = $item->isHit() ? $item->get() : null;

        return $value;
    }

    /** @param array<string, mixed> $body */
    public function remember(string $idempotencyKey, int $statusCode, array $body): void
    {
        $item = $this->cache->getItem($this->cacheKey($idempotencyKey));
        $item->set(new StoredResponse($statusCode, $body));
        $this->cache->save($item);
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'transfer_' . hash('sha256', $idempotencyKey);
    }
}
