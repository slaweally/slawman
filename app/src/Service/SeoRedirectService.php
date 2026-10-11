<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SeoRedirect;
use App\Repository\SeoRedirectRepository;
use Doctrine\ORM\EntityManagerInterface;

class SeoRedirectService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SeoRedirectRepository $seoRedirectRepository,
    ) {
    }

    public function upsert(string $sourcePath, string $targetPath, int $statusCode = 301): void
    {
        $sourcePath = $this->normalizePath($sourcePath);
        $targetPath = $this->normalizePath($targetPath);

        if ($sourcePath === $targetPath || $sourcePath === '/') {
            return;
        }

        $redirect = $this->seoRedirectRepository->findOneBy(['sourcePath' => $sourcePath]) ?? new SeoRedirect();
        $redirect
            ->setSourcePath($sourcePath)
            ->setTargetPath($targetPath)
            ->setStatusCode($statusCode)
            ->setIsActive(true);

        $this->entityManager->persist($redirect);
        $this->entityManager->flush();
    }

    public function resolve(string $path): ?SeoRedirect
    {
        return $this->seoRedirectRepository->findActiveBySourcePath($this->normalizePath($path));
    }

    public function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');

        return rtrim($path, '/') ?: '/';
    }
}
