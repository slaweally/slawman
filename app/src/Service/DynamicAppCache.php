<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * Delegates to Redis when enabled and verified, otherwise filesystem cache.
 */
class DynamicAppCache implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $filesystemCache,
        private readonly RedisCacheService $redisCacheService,
    ) {
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->resolve()->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->resolve()->delete($key);
    }

    private function resolve(): CacheInterface
    {
        $adapter = $this->redisCacheService->createAdapter();

        return $adapter ?? $this->filesystemCache;
    }
}
