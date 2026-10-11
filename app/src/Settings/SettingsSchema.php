<?php

declare(strict_types=1);

namespace App\Settings;

use App\Service\SettingsRegistry;

class SettingsSchema
{
    public function __construct(private readonly SettingsRegistry $settingsRegistry)
    {
    }

    /** @return array<string, mixed> */
    public function groups(): array
    {
        return [
            'general' => ['label' => 'Genel', 'icon' => 'bi bi-gear'],
            'display' => ['label' => 'Görünüm', 'icon' => 'bi bi-layout-sidebar'],
            'seo' => ['label' => 'SEO', 'icon' => 'bi bi-search'],
            'permalinks' => ['label' => 'Kalıcı Bağlantılar', 'icon' => 'bi bi-link-45deg'],
            'smtp' => ['label' => 'E-posta', 'icon' => 'bi bi-envelope'],
            'performance' => ['label' => 'Performans', 'icon' => 'bi bi-speedometer2'],
            'security' => ['label' => 'Güvenlik', 'icon' => 'bi bi-shield-lock'],
            'tracker' => ['label' => 'Takip Paneli', 'icon' => 'bi bi-broadcast'],
            'admin_theme' => ['label' => 'Admin Teması', 'icon' => 'bi bi-palette'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function definitionsByGroup(string $group): array
    {
        $groupMap = [
            'general' => [SettingsRegistry::GROUP_GENERAL],
            'display' => [SettingsRegistry::GROUP_HOME, SettingsRegistry::GROUP_SIDEBAR],
            'seo' => [SettingsRegistry::GROUP_SEO],
            'permalinks' => [SettingsRegistry::GROUP_PERMALINKS],
            'smtp' => [SettingsRegistry::GROUP_SMTP, SettingsRegistry::GROUP_NOTIFICATIONS],
            'performance' => [SettingsRegistry::GROUP_PERFORMANCE],
            'security' => [SettingsRegistry::GROUP_TURNSTILE, SettingsRegistry::GROUP_COMMENTS, SettingsRegistry::GROUP_WAF],
            'tracker' => [SettingsRegistry::GROUP_TRACKER],
            'admin_theme' => [SettingsRegistry::GROUP_APPEARANCE],
        ];

        $sourceGroups = $groupMap[$group] ?? [SettingsRegistry::GROUP_GENERAL];
        $all = $this->settingsRegistry->all();

        return array_filter(
            $all,
            static fn (array $meta): bool => in_array($meta['group'], $sourceGroups, true)
        );
    }
}
