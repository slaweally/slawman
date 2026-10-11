<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ImportMediaDownloader
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/zip', 'text/plain',
    ];

    /** @var array<string, string> */
    private array $urlMap = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
    ) {
    }

    /** @param array<string, string> $urlMap */
    public function setUrlMap(array $urlMap): void
    {
        $this->urlMap = $urlMap;
    }

    /** @return array<string, string> */
    public function getUrlMap(): array
    {
        return $this->urlMap;
    }

    public function resolveUrl(string $url): ?string
    {
        if (isset($this->urlMap[$url])) {
            return $this->publicUrlExists($this->urlMap[$url]) ? $this->urlMap[$url] : null;
        }

        $normalized = $this->normalizeUrl($url);
        foreach ($this->urlMap as $source => $local) {
            if ($this->normalizeUrl($source) === $normalized) {
                return $this->publicUrlExists($local) ? $local : null;
            }
        }

        $basename = basename(parse_url($url, PHP_URL_PATH) ?? '');
        if ($basename !== '') {
            foreach ($this->urlMap as $source => $local) {
                if (basename(parse_url($source, PHP_URL_PATH) ?? '') === $basename) {
                    return $this->publicUrlExists($local) ? $local : null;
                }
            }
        }

        return null;
    }

    public function download(string $url, User $uploader, string $importBatch, ?string $alt = null): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if ($existing = $this->resolveUrl($url)) {
            return $existing;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'follow_location' => 1,
                    'user_agent' => 'Slawman-WP-Importer/1.0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $data = @file_get_contents($url, false, $context);
            if ($data === false || $data === '') {
                return null;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'wpimp_');
            if ($tmpFile === false) {
                return null;
            }

            file_put_contents($tmpFile, $data);

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpFile) ?: 'application/octet-stream';
            finfo_close($finfo);

            if (!in_array($mime, self::ALLOWED_MIMES, true)) {
                @unlink($tmpFile);

                return null;
            }

            $originalName = basename(parse_url($url, PHP_URL_PATH) ?? 'file.bin');
            $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: $this->extFromMime($mime);
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;

            $batchDir = $this->uploadDir . '/wp-import/' . $importBatch;
            if (!is_dir($batchDir) && !mkdir($batchDir, 0755, true) && !is_dir($batchDir)) {
                @unlink($tmpFile);

                return null;
            }

            $targetPath = $batchDir . '/' . $filename;
            if (!rename($tmpFile, $targetPath)) {
                @unlink($tmpFile);

                return null;
            }

            $media = new Media();
            $media->setFilename('wp-import/' . $importBatch . '/' . $filename);
            $media->setOriginalName($originalName);
            $media->setMimeType($mime);
            $media->setSize(strlen($data));
            $media->setPath($targetPath);
            $media->setType($this->resolveType($mime));
            $media->setUploader($uploader);
            $media->setAlt($alt);
            $media->setImportBatch($importBatch);

            $this->entityManager->persist($media);

            $publicUrl = '/uploads/wp-import/' . $importBatch . '/' . $filename;
            $this->registerUrl($url, $publicUrl);

            return $publicUrl;
        } catch (\Throwable) {
            return null;
        }
    }

    public function registerUrl(string $sourceUrl, string $publicUrl): void
    {
        $this->urlMap[$sourceUrl] = $publicUrl;
        $this->urlMap[$this->normalizeUrl($sourceUrl)] = $publicUrl;
    }

    public function replaceContentUrls(string $content): string
    {
        return (string) preg_replace_callback(
            '#(https?://[^"\'\s<>]+)?(/wp-content/uploads/[^"\'\s<>]+)#i',
            function (array $matches): string {
                $full = ($matches[1] ?? '') . ($matches[2] ?? '');
                $resolved = $this->resolveUrl($full) ?? $this->resolveUrl($matches[2] ?? $full);

                return $resolved ?? $full;
            },
            $content
        );
    }

    private function normalizeUrl(string $url): string
    {
        return rtrim(strtolower($url), '/');
    }

    private function publicUrlExists(string $publicUrl): bool
    {
        if (!str_starts_with($publicUrl, '/uploads/')) {
            return false;
        }

        $relative = ltrim(substr($publicUrl, strlen('/uploads/')), '/');

        return $relative !== '' && is_file($this->uploadDir . '/' . $relative);
    }

    private function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            default => 'bin',
        };
    }

    private function resolveType(string $mime): MediaType
    {
        return match (true) {
            str_starts_with($mime, 'image/') => MediaType::Image,
            str_starts_with($mime, 'video/') => MediaType::Video,
            str_starts_with($mime, 'audio/') => MediaType::Audio,
            in_array($mime, ['application/zip', 'application/x-zip-compressed'], true) => MediaType::Archive,
            str_starts_with($mime, 'application/') => MediaType::Document,
            default => MediaType::Other,
        };
    }
}
