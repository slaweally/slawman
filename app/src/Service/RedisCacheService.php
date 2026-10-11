<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SiteSettingsRepository;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class RedisCacheService
{
    public function __construct(private readonly SiteSettingsRepository $settingsRepository)
    {
    }

    public function getDsn(): string
    {
        return $this->getRaw('cache_redis_dsn', 'redis://127.0.0.1:6379');
    }

    public function isEnabled(): bool
    {
        return $this->isTruthy('cache_redis_enabled') && $this->isTruthy('cache_redis_test_ok');
    }

    /** @return array{success: bool, message: string} */
    public function test(?string $dsn = null): array
    {
        $dsn ??= $this->getDsn();
        if ($dsn === '') {
            return ['success' => false, 'message' => 'Redis DSN boş.'];
        }

        try {
            if (class_exists(\Redis::class)) {
                $redis = RedisAdapter::createConnection($dsn);
                $pong = $redis->ping();
                if ($pong === false || $pong === -1) {
                    return ['success' => false, 'message' => 'Redis PING yanıt vermedi.'];
                }
                $testKey = 'slawman_cache_test_' . bin2hex(random_bytes(4));
                $redis->setex($testKey, 10, 'ok');
                $value = $redis->get($testKey);
                $redis->del($testKey);
                if ($value !== 'ok') {
                    return ['success' => false, 'message' => 'Redis yazma/okuma testi başarısız.'];
                }

                return ['success' => true, 'message' => 'Redis bağlantısı başarılı.'];
            }

            return $this->testViaSocket($dsn);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Redis bağlantı hatası: ' . $e->getMessage()];
        }
    }

    public function clear(): void
    {
        if (!$this->isEnabled() || !class_exists(\Redis::class)) {
            return;
        }

        $redis = RedisAdapter::createConnection($this->getDsn());
        $redis->flushDB();
    }

    public function createAdapter(): ?RedisAdapter
    {
        if (!$this->isEnabled() || !class_exists(\Redis::class)) {
            return null;
        }

        try {
            return new RedisAdapter(
                RedisAdapter::createConnection($this->getDsn()),
                'slawman',
                0
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{success: bool, message: string} */
    private function testViaSocket(string $dsn): array
    {
        $parts = parse_url($dsn);
        if ($parts === false) {
            return ['success' => false, 'message' => 'Geçersiz Redis DSN.'];
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int) ($parts['port'] ?? 6379);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr, 3);
        if ($socket === false) {
            return ['success' => false, 'message' => sprintf('Redis sunucusuna bağlanılamadı (%s).', $errstr ?: (string) $errno)];
        }

        fclose($socket);

        return ['success' => false, 'message' => 'Redis erişilebilir ancak PHP ext-redis yüklü değil. Önbellek için ext-redis kurun.'];
    }

    private function getRaw(string $key, string $default = ''): string
    {
        return $this->settingsRepository->findOneBy(['settingKey' => $key])?->getSettingValue() ?? $default;
    }

    private function isTruthy(string $key): bool
    {
        return in_array(strtolower($this->getRaw($key, 'false')), ['1', 'true', 'yes', 'on'], true);
    }
}
