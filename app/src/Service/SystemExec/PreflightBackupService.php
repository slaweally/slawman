<?php

declare(strict_types=1);

namespace App\Service\SystemExec;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Riskli sistem işlemlerinden önce (composer/npm/migration) hafif bir yedek alır.
 * DB dump alınamazsa migration engellenir — geri dönüşü olmayan bir işlem için
 * yedeksiz risk almamak amacıyla.
 */
class PreflightBackupService
{
    /** İsim sırası önemli: MariaDB 10.5+ mariadb-dump'ı önceliklendirir, mysqldump'a geriye dönük uyumluluk için düşer. */
    private const DUMP_BINARY_NAMES = ['mariadb-dump', 'mysqldump'];

    /** Windows'ta PATH'e eklenmemiş yaygın kurulum dizinleri (Laragon, XAMPP, WAMP). */
    private const WINDOWS_FALLBACK_GLOBS = [
        'C:/laragon/bin/mysql/*/bin',
        'C:/xampp/mysql/bin',
        'C:/wamp64/bin/mariadb/*/bin',
        'C:/wamp64/bin/mysql/*/bin',
        'C:/Program Files/MySQL/*/bin',
        'C:/Program Files/MariaDB */bin',
    ];

    private ?string $resolvedDumpBinaryPath = null;
    private bool $resolutionAttempted = false;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(default::MYSQLDUMP_PATH)%')]
        private readonly ?string $mysqldumpPathOverride,
        #[Autowire('%env(DATABASE_URL)%')]
        private readonly string $databaseUrl,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * mysqldump/mariadb-dump binary'sini bulur: önce .env'deki MYSQLDUMP_PATH override'ı,
     * sonra sistem PATH'i, sonra (yalnızca Windows'ta) yaygın kurulum dizinleri denenir.
     * Hiçbiri bulunamazsa null döner — arayan taraf bunu "yedek alınamıyor" olarak ele almalı.
     */
    public function resolveDumpBinaryPath(): ?string
    {
        if ($this->resolutionAttempted) {
            return $this->resolvedDumpBinaryPath;
        }
        $this->resolutionAttempted = true;

        if ($this->mysqldumpPathOverride !== null && $this->mysqldumpPathOverride !== '') {
            if (is_file($this->mysqldumpPathOverride)) {
                return $this->resolvedDumpBinaryPath = $this->mysqldumpPathOverride;
            }

            return null;
        }

        foreach (self::DUMP_BINARY_NAMES as $binaryName) {
            $found = $this->findOnPath($binaryName);
            if ($found !== null) {
                return $this->resolvedDumpBinaryPath = $found;
            }
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            foreach (self::WINDOWS_FALLBACK_GLOBS as $pattern) {
                foreach (self::DUMP_BINARY_NAMES as $binaryName) {
                    $matches = glob($pattern . '/' . $binaryName . '.exe') ?: [];
                    if ($matches !== []) {
                        return $this->resolvedDumpBinaryPath = end($matches);
                    }
                }
            }
        }

        return null;
    }

    private function findOnPath(string $binaryName): ?string
    {
        $finder = \DIRECTORY_SEPARATOR === '\\' ? 'where' : 'which';
        $process = new Process([$finder, $binaryName]);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $lines = array_filter(array_map('trim', explode("\n", $process->getOutput())));
        $first = reset($lines);

        return $first !== false && is_file($first) ? $first : null;
    }

    /** @return array{ok: bool, message: string, path: ?string} */
    public function backupLockFiles(string $timestamp): array
    {
        $backupDir = $this->projectDir . '/slw-content/backup/' . $timestamp;
        $this->filesystem->mkdir($backupDir);

        $copied = [];
        foreach (['composer.lock', 'app/package-lock.json'] as $relative) {
            $source = $this->projectDir . '/' . $relative;
            if (!is_file($source)) {
                continue;
            }
            $target = $backupDir . '/' . basename($relative);
            $this->filesystem->copy($source, $target, true);
            $copied[] = basename($relative);
        }

        if ($copied === []) {
            return ['ok' => false, 'message' => 'Yedeklenecek lock dosyası bulunamadı.', 'path' => null];
        }

        return [
            'ok' => true,
            'message' => sprintf('Yedeklendi: %s', implode(', ', $copied)),
            'path' => $backupDir,
        ];
    }

    /** @return array{ok: bool, message: string, path: ?string} */
    public function backupDatabase(string $timestamp): array
    {
        $dumpBinaryPath = $this->resolveDumpBinaryPath();
        if ($dumpBinaryPath === null) {
            return [
                'ok' => false,
                'message' => 'mysqldump/mariadb-dump bulunamadı. Sistem PATH\'inde değilse .env içinde MYSQLDUMP_PATH ile binary yolunu belirtin.',
                'path' => null,
            ];
        }

        $parts = parse_url($this->databaseUrl);
        if ($parts === false || !isset($parts['host'], $parts['user'], $parts['path'])) {
            return ['ok' => false, 'message' => 'DATABASE_URL çözümlenemedi.', 'path' => null];
        }

        $host = $parts['host'];
        $port = (string) ($parts['port'] ?? '3306');
        $user = urldecode($parts['user']);
        $password = urldecode($parts['pass'] ?? '');
        $dbName = ltrim($parts['path'], '/');
        $dbName = explode('?', $dbName)[0];

        $backupDir = $this->projectDir . '/slw-content/backup/' . $timestamp;
        $this->filesystem->mkdir($backupDir);
        $dumpFile = $backupDir . '/db.sql';

        $process = new Process([
            $dumpBinaryPath,
            '-h', $host,
            '-P', $port,
            '-u', $user,
            '--single-transaction',
            '--result-file=' . $dumpFile,
            $dbName,
        ], $this->projectDir, ['MYSQL_PWD' => $password], null, 120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            return ['ok' => false, 'message' => 'DB dump başarısız: ' . $e->getMessage(), 'path' => null];
        }

        if (!is_file($dumpFile) || filesize($dumpFile) === 0) {
            return ['ok' => false, 'message' => 'DB dump dosyası oluşmadı veya boş.', 'path' => null];
        }

        return ['ok' => true, 'message' => sprintf('DB yedeklendi: %s', basename($dumpFile)), 'path' => $dumpFile];
    }
}
