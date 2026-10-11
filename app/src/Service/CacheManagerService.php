<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CacheManagerService
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'cache.global_clearer')]
        private readonly CacheClearerInterface $cacheClearer,
        private readonly CacheInterface $cache,
        private readonly SettingsService $settingsService,
        private readonly RedisCacheService $redisCacheService,
        private readonly VarnishCacheService $varnishCacheService,
        private readonly SystemMaintenanceService $systemMaintenance,
    ) {
    }

    public function clearAll(): void
    {
        $this->cacheClearer->clear('');
    }

    public function clearSettingsCache(): void
    {
        if ($this->cache instanceof AdapterInterface) {
            $this->cache->clear();
        }
    }

    public function invalidateSetting(string $key): void
    {
        $this->cache->delete('setting_' . $key);
    }

    /** @return array{success: bool, message: string, cleared: list<string>, details: list<string>, warnings: list<string>} */
    public function clearAndReport(): array
    {
        try {
            $cleared = [];

            $this->clearAll();
            $cleared[] = 'Symfony önbelleği (' . $this->systemMaintenance->getEnvironment() . ')';

            $maintenance = $this->systemMaintenance->run();

            if ($this->redisCacheService->isEnabled()) {
                $this->redisCacheService->clear();
                $cleared[] = 'Redis';
            }

            if ($this->varnishCacheService->isEnabled()) {
                $varnish = $this->varnishCacheService->purge();
                if ($varnish['success']) {
                    $cleared[] = 'Varnish';
                }
            }

            $this->settingsService->warmAll();

            $warnings = array_merge($maintenance['warnings'], $maintenance['errors']);
            $hasIssues = $warnings !== [] || $maintenance['errors'] !== [];

            return [
                'success' => !$hasIssues,
                'message' => $hasIssues ? 'Bakım tamamlandı, bazı uyarılar var.' : 'Bakım başarıyla tamamlandı.',
                'cleared' => $cleared,
                'details' => $maintenance['details'],
                'warnings' => $warnings,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Bakım başarısız: ' . $e->getMessage(),
                'cleared' => [],
                'details' => [],
                'warnings' => [],
            ];
        }
    }
}
