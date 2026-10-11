<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploadService
{
    private const IMAGE_VARIANTS = [
        'thumb' => 320,
        'medium' => 768,
        'large' => 1280,
    ];
    private const SERVE_INLINE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain',
    ];
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'image/x-icon', 'image/vnd.microsoft.icon',
        'application/pdf', 'application/zip', 'text/plain',
    ];

    /** @var list<string> */
    private const SOFTWARE_DOWNLOAD_MIMES = [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        'application/x-gzip',
        'application/x-tar',
        'application/vnd.android.package-archive',
        'application/x-msdownload',
        'application/x-apple-diskimage',
        'application/vnd.debian.binary-package',
        'application/x-rpm',
        'application/x-msi',
        'application/java-archive',
        'application/pdf',
        'application/octet-stream',
    ];

    /** @var list<string> */
    private const SOFTWARE_DOWNLOAD_EXTENSIONS = [
        'zip', '7z', 'tar', 'gz', 'tgz', 'apk', 'exe', 'msi', 'dmg', 'deb', 'rpm', 'jar', 'pdf', 'bin',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
        #[Autowire(param: 'max_upload_size')]
        private readonly int $maxUploadSize,
    ) {
    }

    public function upload(UploadedFile $file, User $uploader): Media
    {
        if ($file->getSize() > $this->maxUploadSize) {
            throw new \InvalidArgumentException('Dosya boyutu limiti aşıldı.');
        }

        $mime = $this->detectMime($file);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Geçersiz dosya türü.');
        }

        return $this->storeUploadedFile($file, $uploader, $mime);
    }

    private function storeUploadedFile(UploadedFile $file, User $uploader, string $mime): Media
    {
        $originalName = $file->getClientOriginalName();
        $size = (int) $file->getSize();

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->uploadDir, $filename);

        if ($mime === 'image/svg+xml') {
            $this->sanitizeSvg($this->uploadDir . '/' . $filename);
        }

        $media = new Media();
        $media->setFilename($filename);
        $media->setOriginalName($originalName);
        $media->setMimeType($mime);
        $media->setSize($size);
        $media->setPath($this->uploadDir . '/' . $filename);
        $media->setType($this->resolveType($mime));
        $media->setUploader($uploader);

        if (str_starts_with($mime, 'image/')) {
            $this->createThumbnail($this->uploadDir . '/' . $filename);
            $this->createResponsiveVariants($this->uploadDir . '/' . $filename);
        }

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    public function uploadSoftwarePackage(UploadedFile $file, User $uploader): Media
    {
        if ($file->getSize() > $this->maxUploadSize) {
            throw new \InvalidArgumentException('Dosya boyutu limiti aşıldı.');
        }

        $mime = $this->detectMime($file);
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($mime, self::SOFTWARE_DOWNLOAD_MIMES, true)
            && !in_array($extension, self::SOFTWARE_DOWNLOAD_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Geçersiz yazılım paketi türü. ZIP, 7z, EXE, MSI, APK, DMG, DEB, RPM, JAR veya PDF yükleyin.');
        }

        return $this->storeUploadedFile($file, $uploader, $mime);
    }

    public function getPublicUrl(Media $media): string
    {
        return '/uploads/' . $media->getFilename();
    }

    public function getThumbnailUrl(Media $media): string
    {
        if ($media->getType() !== MediaType::Image) {
            return $this->getPublicUrl($media);
        }

        $thumbFilename = preg_replace('/(\.[^.]+)$/', '_thumb$1', $media->getFilename()) ?? $media->getFilename();
        $thumbPath = $this->uploadDir . '/' . $thumbFilename;

        if (is_file($thumbPath)) {
            return '/uploads/' . $thumbFilename;
        }

        return $this->getPublicUrl($media);
    }

    public function isInlineMime(string $mime): bool
    {
        return in_array($mime, self::SERVE_INLINE_MIMES, true);
    }

    public function deleteFiles(Media $media): void
    {
        $base = $this->uploadDir . '/' . $media->getFilename();
        $paths = [$base];
        $paths[] = preg_replace('/(\.[^.]+)$/', '_thumb$1', $base);
        foreach (array_keys(self::IMAGE_VARIANTS) as $suffix) {
            $paths[] = preg_replace('/(\.[^.]+)$/', '_' . $suffix . '$1', $base);
            $paths[] = preg_replace('/\.[^.]+$/', '_' . $suffix . '.webp', $base);
        }

        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function validateWxrFile(UploadedFile $file): void
    {
        $allowedMimes = ['text/xml', 'application/xml', 'application/rss+xml', 'text/plain'];
        $mime = $this->detectMime($file);
        if (!in_array($mime, $allowedMimes, true)) {
            throw new \InvalidArgumentException('WXR dosya türü geçersiz.');
        }

        $content = (string) @file_get_contents($file->getPathname(), false, null, 0, 2048);
        if (!str_contains($content, '<rss') && !str_contains($content, '<wp:wxr_version')) {
            throw new \InvalidArgumentException('Geçerli bir WordPress WXR içeriği bulunamadı.');
        }
    }

    private function sanitizeSvg(string $path): void
    {
        $dirty = @file_get_contents($path);
        if ($dirty === false) {
            return;
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($dirty);
        if ($clean === false) {
            throw new \InvalidArgumentException('SVG dosyası temizlenemedi (bozuk veya güvensiz içerik).');
        }

        file_put_contents($path, $clean);
    }

    private function detectMime(UploadedFile $file): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file->getPathname());
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
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

    private function createThumbnail(string $path): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $size = min(300, $width, $height);
        $thumb = imagecreatetruecolor($size, $size);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $size, $size, $width, $height);

        $thumbPath = preg_replace('/(\.[^.]+)$/', '_thumb$1', $path);
        imagejpeg($thumb, $thumbPath, 85);

        imagedestroy($src);
        imagedestroy($thumb);
    }

    private function createResponsiveVariants(string $path): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return;
        }

        $sourceWidth = imagesx($src);
        $sourceHeight = imagesy($src);

        foreach (self::IMAGE_VARIANTS as $suffix => $targetWidth) {
            if ($sourceWidth <= $targetWidth) {
                continue;
            }

            $targetHeight = (int) round(($sourceHeight / $sourceWidth) * $targetWidth);
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            $variantPath = preg_replace('/(\.[^.]+)$/', '_' . $suffix . '$1', $path);
            if (is_string($variantPath)) {
                imagejpeg($canvas, $variantPath, 84);
                if (function_exists('imagewebp')) {
                    $webpPath = preg_replace('/\.[^.]+$/', '_' . $suffix . '.webp', $path);
                    if (is_string($webpPath)) {
                        imagewebp($canvas, $webpPath, 82);
                    }
                }
            }

            imagedestroy($canvas);
        }

        imagedestroy($src);
    }
}
