<?php

declare(strict_types=1);

namespace App\Service\SystemExec;

enum SystemExecCommand: string
{
    case CacheClear = 'cache_clear';
    case ComposerInstall = 'composer_install';
    case ComposerUpdate = 'composer_update';
    case MigrationsMigrate = 'migrations_migrate';
    case NpmBuild = 'npm_build';

    public function label(): string
    {
        return match ($this) {
            self::CacheClear => 'Önbelleği Temizle',
            self::ComposerInstall => 'Composer Install',
            self::ComposerUpdate => 'Composer Update',
            self::MigrationsMigrate => 'Veritabanı Migration',
            self::NpmBuild => 'Ön Yüz Derleme (npm build)',
        };
    }

    public function estimatedDuration(): string
    {
        return match ($this) {
            self::CacheClear => 'birkaç saniye',
            self::ComposerInstall => '~1-3 dakika',
            self::ComposerUpdate => '~2-10 dakika',
            self::MigrationsMigrate => '~10-60 saniye',
            self::NpmBuild => '~1-10 dakika',
        };
    }

    public function isProcessBased(): bool
    {
        return $this !== self::CacheClear;
    }

    public function requiresPreflightBackup(): bool
    {
        return $this !== self::CacheClear;
    }
}
