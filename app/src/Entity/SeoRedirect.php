<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeoRedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: SeoRedirectRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEO_REDIRECT_SOURCE', columns: ['source_path'])]
class SeoRedirect
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, name: 'source_path')]
    private string $sourcePath = '';

    #[ORM\Column(length: 255, name: 'target_path')]
    private string $targetPath = '';

    #[ORM\Column(options: ['default' => 301])]
    private int $statusCode = 301;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): static
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function setTargetPath(string $targetPath): static
    {
        $this->targetPath = $targetPath;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = in_array($statusCode, [301, 302, 307, 308], true) ? $statusCode : 301;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
