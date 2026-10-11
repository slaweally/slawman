<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class BuildAssetDeployService
{
    /** @var list<string> Derlenmiş ön yüz asset'lerinde beklenen, çalıştırılamayan statik dosya uzantıları. */
    private const ALLOWED_EXTENSIONS = [
        'js', 'mjs', 'css', 'map', 'json', 'txt',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'avif',
    ];

    /**
     * @return array{copied_files: int, target_dir: string}
     */
    public function deployFromZip(UploadedFile $archive, string $projectDir): array
    {
        if (strtolower((string) $archive->getClientOriginalExtension()) !== 'zip') {
            throw new \RuntimeException('Yalnizca .zip dosyasi kabul edilir.');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($archive->getPathname());
        if ($openResult !== true) {
            throw new \RuntimeException('ZIP acilamadi. Lutfen arsivi kontrol edin.');
        }

        $tempRoot = $projectDir . '/slw-content/tmp/build-deploy-' . bin2hex(random_bytes(6));
        $extractDir = $tempRoot . '/extract';
        $this->ensureDir($extractDir);

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            $this->removeDir($tempRoot);
            throw new \RuntimeException('ZIP acilamadi. Arsiv icerigi gecersiz olabilir.');
        }
        $zip->close();

        $candidateBuildDir = is_dir($extractDir . '/build') ? $extractDir . '/build' : $extractDir;
        if (!is_file($candidateBuildDir . '/entrypoints.json') || !is_file($candidateBuildDir . '/manifest.json')) {
            $this->removeDir($tempRoot);
            throw new \RuntimeException('Arsivde build/entrypoints.json ve build/manifest.json bulunamadi.');
        }

        $this->assertOnlyAllowedFiles($candidateBuildDir);

        $targetBuildDir = $projectDir . '/public/build';
        $this->removeDir($targetBuildDir);
        $this->ensureDir($targetBuildDir);
        $copied = $this->copyDir($candidateBuildDir, $targetBuildDir);

        $this->removeDir($tempRoot);

        return [
            'copied_files' => $copied,
            'target_dir' => $targetBuildDir,
        ];
    }

    /** ZIP içinde beklenmeyen (özellikle çalıştırılabilir/konfig) uzantılı dosya varsa deploy'u reddeder. */
    private function assertOnlyAllowedFiles(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                continue;
            }

            $extension = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $this->removeDir(dirname($dir));
                throw new \RuntimeException(sprintf(
                    'Arsivde izin verilmeyen dosya turu bulundu: %s (.%s). Yalnizca derlenmis on yuz asset\'lerine izin verilir.',
                    $item->getFilename(),
                    $extension !== '' ? $extension : '?'
                ));
            }
        }
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Klasor olusturulamadi: %s', $path));
        }
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    private function copyDir(string $from, string $to): int
    {
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            $destination = $to . '/' . str_replace('\\', '/', $relative);

            if ($item->isDir()) {
                $this->ensureDir($destination);
                continue;
            }

            $parent = dirname($destination);
            $this->ensureDir($parent);
            if (!copy($item->getPathname(), $destination)) {
                throw new \RuntimeException(sprintf('Dosya kopyalanamadi: %s', $relative));
            }
            ++$count;
        }

        return $count;
    }
}
