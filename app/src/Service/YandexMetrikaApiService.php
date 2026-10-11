<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Yandex Metrika Stat/Management API portu (`yandex-metrica-wordpress/includes/Yandex_Metrica.php`).
 */
final class YandexMetrikaApiService
{
    private const MANAGEMENT_URL = 'https://api-metrika.yandex.com/management/v1/counters';
    private const COUNTER_URL = 'https://api-metrika.yandex.com/management/v1/counter/';
    private const STAT_URL = 'https://api-metrika.yandex.com/stat/v1/data/bytime';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly YandexMetrikaOauthService $oauthService,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function getCounters(): array
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return [];
        }

        return $this->cache->get('metrika_counters_' . sha1($token), function (ItemInterface $item) use ($token): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->fetch(self::MANAGEMENT_URL, $token);

                return (array) ($response['counters'] ?? []);
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function isValidCounter(int $counterId): bool
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $result = $this->fetch(self::COUNTER_URL . $counterId, $token);
        } catch (\Throwable) {
            return false;
        }

        if (($result['counter']['code_status'] ?? null) === 'CS_OK') {
            return true;
        }

        return ($result['counter']['status'] ?? null) === 'Active';
    }

    /** @return array{daily: array<string, array<string, float|int>>, total: array<string, float|int>} */
    public function getStatistics(int $counterId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return ['daily' => [], 'total' => []];
        }

        $cacheKey = $this->buildCacheKey('metrika_stats', $counterId, $start, $end);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($counterId, $start, $end, $token): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->fetch(self::STAT_URL, $token, [
                    'date1' => $start->format('Y-m-d'),
                    'date2' => $end->format('Y-m-d'),
                    'metrics' => 'ym:s:pageviews,ym:s:visits,ym:s:users,ym:s:percentNewVisitors,ym:s:pageDepth,ym:s:avgVisitDurationSeconds',
                    'group' => 'day',
                    'ids' => $counterId,
                ]);
            } catch (\Throwable) {
                return ['daily' => [], 'total' => []];
            }

            $data = $response['data'][0]['metrics'] ?? [];
            $pageviews = $data[0] ?? [];
            $visits = $data[1] ?? [];
            $users = $data[2] ?? [];
            $newVisitors = $data[3] ?? [];
            $pageDepth = $data[4] ?? [];
            $duration = $data[5] ?? [];

            $daily = [];
            foreach ((array) ($response['time_intervals'] ?? []) as $key => $time) {
                $daily[$time[0]] = [
                    'pageviews' => $pageviews[$key] ?? 0,
                    'visits' => $visits[$key] ?? 0,
                    'visitors' => $users[$key] ?? 0,
                    'new_visitors' => $newVisitors[$key] ?? 0,
                    'page_depth' => $pageDepth[$key] ?? 0,
                    'duration' => $duration[$key] ?? 0,
                ];
            }

            $totals = (array) ($response['totals'] ?? []);
            $total = [
                'pageviews' => isset($totals[0]) ? (int) array_sum($totals[0]) : 0,
                'visits' => isset($totals[1]) ? (int) array_sum($totals[1]) : 0,
                'visitors' => isset($totals[2]) ? (int) array_sum($totals[2]) : 0,
                'new_visitors' => isset($totals[3]) ? array_sum($totals[3]) / max(1, count($totals[3])) : 0,
                'page_depth' => isset($totals[4]) ? array_sum($totals[4]) / max(1, count($totals[4])) : 0,
                'duration' => isset($totals[5]) ? array_sum($totals[5]) / max(1, count($totals[5])) : 0,
            ];

            return ['daily' => $daily, 'total' => $total];
        });
    }

    /** @return list<array{url: string, pageviews: int}> */
    public function getPopularContent(int $counterId, \DateTimeImmutable $start, \DateTimeImmutable $end, int $perPage = 10): array
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return [];
        }

        $cacheKey = $this->buildCacheKey('metrika_popular', $counterId, $start, $end, $perPage);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($counterId, $start, $end, $perPage, $token): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->fetch(self::STAT_URL, $token, [
                    'date1' => $start->format('Y-m-d'),
                    'date2' => $end->format('Y-m-d'),
                    'dimensions' => 'ym:pv:URL',
                    'metrics' => 'ym:pv:pageviews',
                    'group' => 'day',
                    'ids' => $counterId,
                    'limit' => $perPage,
                ]);
            } catch (\Throwable) {
                return [];
            }

            $result = [];
            foreach ((array) ($response['data'] ?? []) as $data) {
                $result[] = [
                    'url' => $data['dimensions'][0]['name'] ?? '',
                    'pageviews' => isset($data['metrics'][0]) ? (int) array_sum($data['metrics'][0]) : 0,
                ];
                if (count($result) >= $perPage) {
                    break;
                }
            }

            return $result;
        });
    }

    /** @return list<array{url: string, visits: int}> */
    public function getReferrers(int $counterId, \DateTimeImmutable $start, \DateTimeImmutable $end, int $perPage = 10): array
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return [];
        }

        $cacheKey = $this->buildCacheKey('metrika_referrers', $counterId, $start, $end, $perPage);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($counterId, $start, $end, $perPage, $token): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->fetch(self::STAT_URL, $token, [
                    'date1' => $start->format('Y-m-d'),
                    'date2' => $end->format('Y-m-d'),
                    'dimensions' => 'ym:s:referer',
                    'metrics' => 'ym:s:visits',
                    'group' => 'day',
                    'ids' => $counterId,
                    'limit' => $perPage,
                ]);
            } catch (\Throwable) {
                return [];
            }

            $result = [];
            foreach ((array) ($response['data'] ?? []) as $data) {
                $result[] = [
                    'url' => $data['dimensions'][0]['name'] ?? '',
                    'visits' => isset($data['metrics'][0]) ? (int) array_sum($data['metrics'][0]) : 0,
                ];
                if (count($result) >= $perPage) {
                    break;
                }
            }

            return $result;
        });
    }

    /** @return list<array{name: string, url: string, visits: int}> */
    public function getSearchTerms(int $counterId, \DateTimeImmutable $start, \DateTimeImmutable $end, int $perPage = 10): array
    {
        $token = $this->oauthService->getAccessToken();
        if ($token === null || $token === '') {
            return [];
        }

        $cacheKey = $this->buildCacheKey('metrika_search_terms', $counterId, $start, $end, $perPage);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($counterId, $start, $end, $perPage, $token): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->fetch(self::STAT_URL, $token, [
                    'date1' => $start->format('Y-m-d'),
                    'date2' => $end->format('Y-m-d'),
                    'dimensions' => 'ym:s:<attribution>SearchPhrase',
                    'metrics' => 'ym:s:visits',
                    'group' => 'day',
                    'ids' => $counterId,
                    'limit' => $perPage,
                ]);
            } catch (\Throwable) {
                return [];
            }

            $totals = (array) ($response['totals'][0] ?? []);
            $result = [];
            foreach ((array) ($response['data'] ?? []) as $key => $data) {
                $result[] = [
                    'name' => $data['dimensions'][0]['name'] ?? '',
                    'url' => $data['dimensions'][0]['url'] ?? '',
                    'visits' => (int) ($totals[$key] ?? 0),
                ];
                if (count($result) >= $perPage) {
                    break;
                }
            }

            return $result;
        });
    }

    /** @param array<string, mixed> $query */
    private function fetch(string $url, string $token, array $query = []): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'query' => $query,
            'headers' => [
                'Authorization' => 'OAuth ' . $token,
            ],
            'timeout' => 15,
        ]);

        return $response->toArray(false);
    }

    private function buildCacheKey(string $prefix, int $counterId, \DateTimeImmutable $start, \DateTimeImmutable $end, int $perPage = 0): string
    {
        return sprintf(
            '%s_%d_%s_%s_%d',
            $prefix,
            $counterId,
            $start->format('Ymd'),
            $end->format('Ymd'),
            $perPage
        );
    }
}
