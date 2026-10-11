<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackerIdentityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir IP adresinin (HMAC ile hash'lenmiş) sabit takma adını tutar.
 * Ham IP burada asla saklanmaz; amaç aynı ziyaretçinin panelde hep aynı
 * isimle görünmesini sağlamaktır.
 */
#[ORM\Entity(repositoryClass: TrackerIdentityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tracker_identity_ip_hash', columns: ['ip_hash'])]
class TrackerIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $ipHash = '';

    #[ORM\Column(length: 100)]
    private string $displayName = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct()
    {
        $this->firstSeenAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->firstSeenAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): static
    {
        $this->ipHash = $ipHash;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(): static
    {
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }
}
