<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Sitenin genel yayınlarında (front-end, e-postalar, footer) gösterilecek
 * tek "site sahibi" kimliğini sağlar. Tek adminli bir kurulum olduğu için
 * bu her zaman ilk (veya ROLE_SUPER_ADMIN'e sahip) User kaydıdır.
 */
class SiteOwnerService
{
    private ?User $ownerCache = null;
    private bool $resolved = false;

    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function getOwner(): ?User
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->ownerCache = $this->userRepository->findOwner();
        }

        return $this->ownerCache;
    }

    public function getDisplayName(string $default = 'Slaweally'): string
    {
        return $this->getOwner()?->getDisplayName() ?? $default;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->getOwner()?->getAvatar();
    }

    public function getBio(): ?string
    {
        return $this->getOwner()?->getBio();
    }

    public function getGithub(): ?string
    {
        return $this->getOwner()?->getGithub();
    }

    public function getTwitter(): ?string
    {
        return $this->getOwner()?->getTwitter();
    }

    public function getLinkedin(): ?string
    {
        return $this->getOwner()?->getLinkedin();
    }

    /** @return list<string> */
    public function getStackTags(): array
    {
        return $this->getOwner()?->getStackTagsList() ?? [];
    }
}
