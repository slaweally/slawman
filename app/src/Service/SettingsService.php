<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SiteSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsService
{
    /** @var array<string, string> */
    private array $envFallbacks;

    public function __construct(
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly CacheInterface $cache,
        private readonly SettingsRegistry $settingsRegistry,
        #[Autowire('%env(default::SITE_TITLE)%')]
        ?string $envSiteTitle = '',
        #[Autowire('%env(default::SITE_DESCRIPTION)%')]
        ?string $envSiteDescription = '',
        #[Autowire('%env(default::SITE_URL)%')]
        ?string $envSiteUrl = '',
        #[Autowire('%env(default::SITE_AUTHOR)%')]
        ?string $envSiteAuthor = '',
        #[Autowire('%env(default::SITE_EMAIL)%')]
        ?string $envSiteEmail = '',
    ) {
        $this->envFallbacks = array_filter([
            'site_title' => $envSiteTitle,
            'site_description' => $envSiteDescription,
            'site_url' => $envSiteUrl,
            'site_author' => $envSiteAuthor,
            'site_email' => $envSiteEmail,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $registryDefault = $this->settingsRegistry->all()[$key]['default'] ?? null;
        $fallback = $default ?? $registryDefault;

        $value = $this->cache->get('setting_' . $key, function (ItemInterface $item) use ($key, $fallback): ?string {
            $item->expiresAfter($this->getCacheTtl());
            $setting = $this->settingsRepository->findOneBy(['settingKey' => $key]);
            $dbValue = $setting?->getSettingValue();
            if ($dbValue !== null && $dbValue !== '') {
                return $dbValue;
            }

            return $this->envFallbacks[$key] ?? $fallback;
        });

        return $value;
    }

    public function isTruthy(string $key): bool
    {
        // NOT: get() içindeki `$default ?? $registryDefault` mantığı yüzünden burada
        // sabit 'false' geçmek registry varsayılanını (örn. checkbox default 'true')
        // her zaman ezerdi. Registry varsayılanını önce çözüp öyle geçiyoruz.
        $registryDefault = $this->settingsRegistry->all()[$key]['default'] ?? 'false';
        $value = strtolower((string) $this->get($key, $registryDefault));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->get($key, (string) $default) ?? $default);
    }

    public function set(string $key, ?string $value, ?string $group = null): void
    {
        $setting = $this->settingsRepository->findOneBy(['settingKey' => $key]);
        if ($setting === null) {
            $setting = new \App\Entity\SiteSettings();
            $setting->setSettingKey($key);
        }
        $setting->setSettingValue($value);
        if ($group !== null) {
            $setting->setSettingGroup($group);
        } elseif (isset($this->settingsRegistry->all()[$key])) {
            $setting->setSettingGroup($this->settingsRegistry->all()[$key]['group']);
        }

        $this->settingsRepository->getEntityManager()->persist($setting);
        $this->settingsRepository->getEntityManager()->flush();
        $this->cache->delete('setting_' . $key);
    }

    /** @param array<string, string|null> $settings */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set((string) $key, is_string($value) ? $value : null);
        }
    }

    /** @return array<string, string|null> */
    public function getAllValues(): array
    {
        $values = [];
        foreach ($this->settingsRegistry->all() as $key => $meta) {
            $values[$key] = $this->get($key, $meta['default'] ?? null);
        }

        return $values;
    }

    public function warmAll(): void
    {
        foreach (array_keys($this->settingsRegistry->all()) as $key) {
            $this->get($key);
        }
    }

    private function getCacheTtl(): int
    {
        $ttl = (int) ($this->settingsRepository->findOneBy(['settingKey' => 'settings_cache_ttl'])?->getSettingValue() ?? 3600);

        return max(60, $ttl);
    }
}
