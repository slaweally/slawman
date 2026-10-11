<?php

declare(strict_types=1);

namespace App\Service;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sunucu bakım işlemleri: dizinler, medya yolu, OPcache.
 * Önbellek dosyalarına dokunmaz — aktif istek sırasında silmek container'ı bozar.
 */
class SystemMaintenanceService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
    ) {
    }

    /**
     * @return array{
     *     details: list<string>,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function run(): array
    {
        $details = [];
        $warnings = [];
        $errors = [];

        if (function_exists('opcache_reset') && @opcache_reset()) {
            $details[] = 'OPcache sıfırlandı';
        }

        foreach ($this->migrateLegacyUploads() as $line) {
            $details[] = $line;
        }

        foreach ($this->ensureRuntimeDirectories() as $result) {
            if ($result['ok']) {
                $details[] = $result['message'];
            } else {
                $errors[] = $result['message'];
            }
        }

        if (str_contains($this->uploadDir, DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'uploads')) {
            $warnings[] = 'UPLOAD_DIR hâlâ var/uploads — .env içinde slw-content/uploads kullanın (medya 404 önlenir)';
        }

        $cspFile = $this->projectDir . '/app/src/EventListener/SecurityHeadersListener.php';
        $cspContent = is_readable($cspFile) ? (string) file_get_contents($cspFile) : '';
        if (!str_contains($cspContent, "script-src 'self' 'unsafe-inline'")) {
            $warnings[] = 'Admin CSP güncellemesi eksik (WordPress import JS çalışmayabilir)';
        }

        $probe = $this->writeUploadProbe();
        if ($probe['ok']) {
            $details[] = $probe['message'];
        } else {
            $errors[] = $probe['message'];
        }

        if ($this->environment === 'dev') {
            $warnings[] = 'APP_ENV=dev — canlı sunucuda .env içinde APP_ENV=prod ve APP_DEBUG=0 kullanın';
        }

        $prodCacheDir = $this->projectDir . '/slw-content/cache/prod';
        if ($this->environment === 'prod' && is_dir($prodCacheDir) && !is_writable($prodCacheDir)) {
            $errors[] = 'slw-content/cache/prod yazılamıyor — FTP ile izinleri 755/775 yapın';
        }

        return [
            'details' => $details,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /** @return list<string> */
    private function migrateLegacyUploads(): array
    {
        $legacyDir = $this->projectDir . '/var/uploads';
        if (!is_dir($legacyDir . '/wp-import') || realpath($legacyDir) === realpath($this->uploadDir)) {
            return [];
        }

        $migrated = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($legacyDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($legacyDir) + 1);
            $target = $this->uploadDir . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }

            if (!is_file($target) && @copy($item->getPathname(), $target)) {
                ++$migrated;
            }
        }

        if ($migrated === 0) {
            return [];
        }

        return [sprintf('Eski var/uploads → slw-content/uploads: %d dosya kopyalandı', $migrated)];
    }

    /**
     * @return list<array{ok: bool, message: string}>
     */
    private function ensureRuntimeDirectories(): array
    {
        $dirs = [
            $this->projectDir . '/slw-content/log',
            $this->projectDir . '/slw-content/import-temp',
            $this->projectDir . '/slw-content/import-jobs',
            $this->uploadDir,
            $this->uploadDir . '/wp-import',
        ];

        $results = [];
        foreach ($dirs as $dir) {
            $results[] = $this->ensureDirectory($dir);
        }

        return $results;
    }

    /** @return array{ok: bool, message: string} */
    private function ensureDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['ok' => false, 'message' => 'Dizin oluşturulamadı: ' . $dir];
            }

            return ['ok' => true, 'message' => 'Dizin oluşturuldu: ' . $this->shortPath($dir)];
        }

        if (!is_writable($dir)) {
            return ['ok' => false, 'message' => 'Dizin yazılamıyor: ' . $this->shortPath($dir)];
        }

        return ['ok' => true, 'message' => 'Dizin hazır: ' . $this->shortPath($dir)];
    }

    /** @return array{ok: bool, message: string} */
    private function writeUploadProbe(): array
    {
        $probeFile = $this->uploadDir . '/.maintenance-probe.txt';
        if (@file_put_contents($probeFile, 'ok') === false) {
            return ['ok' => false, 'message' => 'Medya dizinine yazılamıyor'];
        }

        $ok = is_file($probeFile);
        @unlink($probeFile);

        $publicRoot = realpath($this->projectDir . '/slw-content/uploads');
        $uploadRoot = realpath($this->uploadDir);
        $webHint = ($publicRoot !== false && $uploadRoot !== false && $publicRoot === $uploadRoot)
            ? ' (/uploads/ üzerinden erişilebilir)'
            : '';

        return [
            'ok' => $ok,
            'message' => 'Medya yazma testi OK' . $webHint,
        ];
    }

    private function shortPath(string $path): string
    {
        $project = realpath($this->projectDir) ?: $this->projectDir;
        if (str_starts_with($path, $project)) {
            return ltrim(substr($path, strlen($project)), '/\\');
        }

        return $path;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }
}
