<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SiteSettingsRepository;

class VarnishCacheService
{
    public const MODE_CLOUDPANEL = 'cloudpanel';
    public const MODE_STANDALONE = 'standalone';

    private const DEFAULT_CLOUDPANEL_PORT = 6081;
    private const DEFAULT_STANDALONE_PORT = 6082;

    public function __construct(private readonly SiteSettingsRepository $settingsRepository)
    {
    }

    public function isEnabled(): bool
    {
        return $this->isTruthy('cache_varnish_enabled') && $this->isTruthy('cache_varnish_test_ok');
    }

    /** @return array{success: bool, message: string} */
    public function test(
        ?string $host = null,
        ?int $port = null,
        ?string $secret = null,
        ?string $purgeUrl = null,
        ?string $mode = null,
        ?string $tagPrefix = null,
    ): array {
        $mode ??= $this->getMode();

        if ($mode === self::MODE_CLOUDPANEL) {
            return $this->testCloudPanel($host, $port, $tagPrefix);
        }

        return $this->testStandalone($host, $port, $secret, $purgeUrl);
    }

    /** @return array{success: bool, message: string} */
    public function purge(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Varnish aktif değil.'];
        }

        if ($this->getMode() === self::MODE_CLOUDPANEL) {
            return $this->purgeCloudPanel();
        }

        return $this->purgeStandalone();
    }

    /** @return array<string, mixed>|null */
    public function detectCloudPanelSettings(): ?array
    {
        return $this->loadCloudPanelSettings();
    }

    /** @return array{success: bool, message: string} */
    private function testCloudPanel(?string $host, ?int $port, ?string $tagPrefix): array
    {
        $config = $this->resolveCloudPanelConfig($host, $port, $tagPrefix);
        if ($config['tagPrefix'] === '') {
            return [
                'success' => false,
                'message' => 'Cache Tag Prefix gerekli. CloudPanel → Varnish Cache → Cache Tag Prefix değerini girin.',
            ];
        }

        return $this->sendCloudPanelPurge(
            $config['serverUrl'],
            $config['tagPrefix'],
            true,
        );
    }

    /** @return array{success: bool, message: string} */
    private function purgeCloudPanel(): array
    {
        $config = $this->resolveCloudPanelConfig(null, null, null);

        if ($config['tagPrefix'] === '') {
            return ['success' => false, 'message' => 'Cache Tag Prefix tanımlı değil.'];
        }

        return $this->sendCloudPanelPurge(
            $config['serverUrl'],
            $config['tagPrefix'],
            false,
        );
    }

    /**
     * @return array{serverUrl: string, tagPrefix: string, host: string, port: int}
     */
    private function resolveCloudPanelConfig(?string $host, ?int $port, ?string $tagPrefix): array
    {
        $clp = $this->loadCloudPanelSettings();

        if ($host === null || $host === '') {
            $host = $this->getRaw('cache_varnish_host', '127.0.0.1');
        }
        if ($port === null || $port <= 0) {
            $port = (int) $this->getRaw('cache_varnish_port', (string) self::DEFAULT_CLOUDPANEL_PORT);
        }
        if ($tagPrefix === null || $tagPrefix === '') {
            $tagPrefix = $this->getRaw('cache_varnish_tag_prefix', '');
        }

        if ($clp !== null) {
            if ($host === '127.0.0.1' && isset($clp['server'])) {
                [$parsedHost, $parsedPort] = $this->parseServer($clp['server']);
                $host = $parsedHost ?: $host;
                $port = $parsedPort ?: $port;
            }
            if ($tagPrefix === '' && !empty($clp['cacheTagPrefix'])) {
                $tagPrefix = (string) $clp['cacheTagPrefix'];
            }
        }

        return [
            'serverUrl' => $this->buildServerUrl($host, $port),
            'tagPrefix' => $tagPrefix,
            'host' => $host,
            'port' => $port,
        ];
    }

    /** @return array{success: bool, message: string} */
    private function testStandalone(?string $host, ?int $port, ?string $secret, ?string $purgeUrl): array
    {
        $host ??= $this->getRaw('cache_varnish_host', '127.0.0.1');
        $port ??= (int) $this->getRaw('cache_varnish_port', (string) self::DEFAULT_STANDALONE_PORT);
        $secret ??= $this->getRaw('cache_varnish_secret', '');
        $purgeUrl ??= $this->getRaw('cache_varnish_purge_url', '');

        $messages = [];

        if ($secret !== '') {
            $cliResult = $this->testCli($host, $port, $secret);
            if ($cliResult['success']) {
                return $cliResult;
            }
            $messages[] = $cliResult['message'];
        }

        if ($purgeUrl !== '') {
            $httpResult = $this->testHttpPurge($purgeUrl);
            if ($httpResult['success']) {
                return $httpResult;
            }
            $messages[] = $httpResult['message'];
        }

        if ($secret === '' && $purgeUrl === '') {
            return ['success' => false, 'message' => 'Standalone modda Varnish secret veya PURGE URL tanımlı değil.'];
        }

        return ['success' => false, 'message' => implode(' ', $messages) ?: 'Varnish bağlantı testi başarısız.'];
    }

    /** @return array{success: bool, message: string} */
    private function purgeStandalone(): array
    {
        $host = $this->getRaw('cache_varnish_host', '127.0.0.1');
        $port = (int) $this->getRaw('cache_varnish_port', (string) self::DEFAULT_STANDALONE_PORT);
        $secret = $this->getRaw('cache_varnish_secret', '');
        $purgeUrl = $this->getRaw('cache_varnish_purge_url', '');

        $messages = [];
        $ok = false;

        if ($secret !== '') {
            $ban = $this->sendCliCommand($host, $port, $secret, 'ban req.url ~ .');
            if ($ban && str_contains(strtolower($ban), 'ok')) {
                $messages[] = 'CLI ban gönderildi.';
                $ok = true;
            }
        }

        if ($purgeUrl !== '') {
            $http = $this->sendHttpPurge($purgeUrl);
            $messages[] = $http['message'];
            $ok = $ok || $http['success'];
        }

        return ['success' => $ok, 'message' => implode(' ', $messages) ?: 'Varnish temizleme tamamlandı.'];
    }

    /** @return array{success: bool, message: string} */
    private function sendCloudPanelPurge(string $serverUrl, string $tags, bool $testOnly): array
    {
        $headers = ['X-Cache-Tags: ' . $tags];
        $result = $this->sendPurgeRequest($serverUrl, $headers);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => $testOnly
                    ? 'CloudPanel Varnish bağlantısı başarılı (PURGE + X-Cache-Tags).'
                    : 'CloudPanel Varnish önbelleği temizlendi.',
            ];
        }

        $hint = ' CloudPanel\'de Varnish Cache etkin mi? Host genelde 127.0.0.1, port 6081 olmalı. Cache Tag Prefix, CloudPanel Varnish ayarındaki değerle aynı olmalı.';

        return [
            'success' => false,
            'message' => ($testOnly ? 'CloudPanel Varnish testi başarısız: ' : 'CloudPanel Varnish temizleme başarısız: ')
                . $result['message']
                . $hint,
        ];
    }

    /**
     * @param list<string> $headers
     *
     * @return array{success: bool, message: string}
     */
    private function sendPurgeRequest(string $url, array $headers): array
    {
        if (\function_exists('curl_init')) {
            return $this->sendPurgeRequestViaCurl($url, $headers);
        }

        return $this->sendPurgeRequestViaStream($url, $headers);
    }

    /**
     * @param list<string> $headers
     *
     * @return array{success: bool, message: string}
     */
    private function sendPurgeRequestViaCurl(string $url, array $headers): array
    {
        $curl = curl_init();
        if ($curl === false) {
            return ['success' => false, 'message' => 'cURL başlatılamadı.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PURGE',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'HTTP ' . $httpCode];
        }

        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }

        return ['success' => false, 'message' => 'HTTP ' . ($httpCode > 0 ? (string) $httpCode : 'yanıt yok')];
    }

    /**
     * @param list<string> $headers
     *
     * @return array{success: bool, message: string}
     */
    private function sendPurgeRequestViaStream(string $url, array $headers): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'PURGE',
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);

            @file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';

            if (str_contains($statusLine, '200') || str_contains($statusLine, '204')) {
                return ['success' => true, 'message' => trim($statusLine)];
            }

            return ['success' => false, 'message' => $statusLine !== '' ? $statusLine : 'yanıt yok'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{success: bool, message: string} */
    private function testCli(string $host, int $port, string $secret): array
    {
        $pong = $this->sendCliCommand($host, $port, $secret, 'ping');
        if ($pong && str_contains(strtolower($pong), 'pong')) {
            return ['success' => true, 'message' => 'Varnish yönetim arayüzü bağlantısı başarılı.'];
        }

        return ['success' => false, 'message' => 'Varnish CLI ping başarısız: ' . ($pong ?: 'yanıt yok')];
    }

    /** @return array{success: bool, message: string} */
    private function testHttpPurge(string $url): array
    {
        return $this->sendHttpPurge($url);
    }

    /** @return array{success: bool, message: string} */
    private function sendHttpPurge(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $headers = $host ? ['Host: ' . $host] : [];

        return $this->sendPurgeRequest($url, $headers);
    }

    private function sendCliCommand(string $host, int $port, string $secret, string $command): ?string
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 3);
        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, 3);
        $challenge = $this->readLine($socket);
        if ($challenge === null) {
            fclose($socket);

            return null;
        }

        $authHash = hash('sha256', $challenge . $secret . $challenge);
        fwrite($socket, "auth {$authHash}\n");
        $authResponse = $this->readLine($socket);
        if ($authResponse === null || str_starts_with($authResponse, '107')) {
            fclose($socket);

            return null;
        }

        fwrite($socket, "{$command}\n");
        $response = $this->readLine($socket);
        fclose($socket);

        return $response;
    }

    private function readLine($socket): ?string
    {
        $line = fgets($socket);
        if ($line === false) {
            return null;
        }

        return trim($line);
    }

    /** @return array<string, mixed>|null */
    private function loadCloudPanelSettings(): ?array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        if ($home === '') {
            return null;
        }

        $path = rtrim(str_replace('\\', '/', $home), '/') . '/.varnish-cache/settings.json';
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return \is_array($decoded) ? $decoded : null;
    }

    /** @return array{0: string, 1: int} */
    private function parseServer(string $server): array
    {
        $server = trim($server);
        if ($server === '') {
            return ['127.0.0.1', self::DEFAULT_CLOUDPANEL_PORT];
        }

        if (!str_contains($server, '://')) {
            $server = 'http://' . $server;
        }

        $parts = parse_url($server);
        $host = (string) ($parts['host'] ?? '127.0.0.1');
        $port = (int) ($parts['port'] ?? self::DEFAULT_CLOUDPANEL_PORT);

        return [$host, $port];
    }

    private function buildServerUrl(string $host, int $port): string
    {
        return sprintf('http://%s:%d', $host, $port);
    }

    private function getMode(): string
    {
        $mode = strtolower($this->getRaw('cache_varnish_mode', self::MODE_CLOUDPANEL));

        return $mode === self::MODE_STANDALONE ? self::MODE_STANDALONE : self::MODE_CLOUDPANEL;
    }

    private function getRaw(string $key, string $default = ''): string
    {
        return $this->settingsRepository->findOneBy(['settingKey' => $key])?->getSettingValue() ?? $default;
    }

    private function isTruthy(string $key): bool
    {
        return \in_array(strtolower($this->getRaw($key, 'false')), ['1', 'true', 'yes', 'on'], true);
    }
}
