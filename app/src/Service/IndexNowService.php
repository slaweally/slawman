<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * IndexNow protokolü (Bing/Yandex ortak anlık indeksleme API'si). Google bu
 * protokolü desteklemiyor — Google için Search Console/sitemap ping yeterli.
 */
class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settingsService->isTruthy('seo_indexnow_enabled');
    }

    /** Ayarlarda anahtar yoksa üretir ve kaydeder; varsa mevcut anahtarı döner. */
    public function getOrCreateKey(): string
    {
        $key = (string) ($this->settingsService->get('seo_indexnow_key', '') ?? '');
        if ($key !== '') {
            return $key;
        }

        $key = bin2hex(random_bytes(16));
        $this->settingsService->set('seo_indexnow_key', $key);

        return $key;
    }

    /**
     * Tek bir URL'nin yayınlandığını/güncellendiğini Bing+Yandex'e bildirir.
     * Best-effort: ağ hatası olursa sessizce loglanır, çağıran akışı bozmaz.
     */
    public function submitUrl(string $url): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $siteUrl = trim((string) ($this->settingsService->get('site_url', '') ?? ''));
        if ($siteUrl === '') {
            return;
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return;
        }

        $key = $this->getOrCreateKey();

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'url' => $url,
                    'key' => $key,
                    'keyLocation' => rtrim($siteUrl, '/') . '/' . $key . '.txt',
                ],
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
            ]);
            // HttpClient istekleri tembel (lazy) tetikler — status code okunmadan
            // gerçek ağ isteği garanti gönderilmeyebilir, bu yüzden burada okunuyor.
            $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->warning('IndexNow bildirimi başarısız.', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}
