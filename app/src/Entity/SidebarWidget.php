<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SidebarWidgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SidebarWidgetRepository::class)]
class SidebarWidget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 50, options: ['default' => 'html'])]
    private string $type = 'html';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /** @param array<string, mixed> $settings */
    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /** @return list<array{icon: string, url: string}> */
    public function getSocialLinks(): array
    {
        return $this->settings['social_links'] ?? [];
    }

    public function getArchiveIcon(): string
    {
        return $this->settings['archive_icon'] ?? 'fas fa-archive';
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

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
