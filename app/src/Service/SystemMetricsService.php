<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SystemMetricsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SettingsService $settingsService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{
     *   db_size_bytes:int,
     *   project_size_bytes:int,
     *   cache_size_bytes:int,
     *   media_size_bytes:int,
     *   total_disk_bytes:int,
     *   db_ping_ms:float,
     *   php_mem_mb:float,
     *   php_mem_peak_mb:float,
     *   active_cache_systems:list<string>
     * }
     */
    public function collect(): array
    {
        return [
            'db_size_bytes' => $this->resolveDatabaseSize(),
            'project_size_bytes' => $this->directorySize($this->projectDir, ['vendor', 'node_modules', '.git', 'uploads']),
            'cache_size_bytes' => $this->directorySize($this->projectDir . DIRECTORY_SEPARATOR . 'slw-content' . DIRECTORY_SEPARATOR . 'cache'),
            'media_size_bytes' => $this->directorySize($this->projectDir . DIRECTORY_SEPARATOR . 'slw-content' . DIRECTORY_SEPARATOR . 'uploads'),
            'total_disk_bytes' => $this->directorySize($this->projectDir),
            'db_ping_ms' => $this->measureDbPingMs(),
            'php_mem_mb' => round(memory_get_usage(true) / 1048576, 2),
            'php_mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'active_cache_systems' => $this->resolveActiveCaches(),
        ];
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, 2) . ' ' . $units[$power];
    }

    private function measureDbPingMs(): float
    {
        $start = microtime(true);
        $this->connection->executeQuery('SELECT 1')->fetchOne();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2);
    }

    private function resolveDatabaseSize(): int
    {
        $platformClass = strtolower((new \ReflectionClass($this->connection->getDatabasePlatform()))->getShortName());

        if (str_contains($platformClass, 'mysql')) {
            $size = $this->connection->executeQuery(
                'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()'
            )->fetchOne();

            return (int) ($size ?? 0);
        }

        if (str_contains($platformClass, 'sqlite')) {
            $path = (string) $this->connection->executeQuery('PRAGMA database_list')->fetchAssociative()['file'] ?? '';
            if ($path !== '' && is_file($path)) {
                return (int) filesize($path);
            }
        }

        return 0;
    }

    /**
     * @param list<string> $excludeDirs
     */
    private function directorySize(string $path, array $excludeDirs = []): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $pathname = $file->getPathname();
            foreach ($excludeDirs as $excluded) {
                if (str_contains($pathname, DIRECTORY_SEPARATOR . $excluded . DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * @return list<string>
     */
    private function resolveActiveCaches(): array
    {
        $systems = ['Filesystem'];
        if ($this->settingsService->isTruthy('cache_redis_enabled')) {
            $systems[] = 'Redis';
        }
        if ($this->settingsService->isTruthy('cache_varnish_enabled')) {
            $systems[] = 'Varnish';
        }

        return $systems;
    }
}
