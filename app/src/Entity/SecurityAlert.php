<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SecurityAlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityAlertRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_security_alert_created_at')]
#[ORM\Index(columns: ['severity'], name: 'idx_security_alert_severity')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_security_alert_ip')]
#[ORM\UniqueConstraint(name: 'uniq_security_alert_ip_type', columns: ['ip_address', 'type'])]
class SecurityAlert
{
    public const TYPE_SQLI = 'sqli';
    public const TYPE_XSS = 'xss';
    public const TYPE_TRAVERSAL = 'traversal';
    public const TYPE_RECON = 'recon';
    public const TYPE_LOGIN_FAILED = 'login_failed';
    public const TYPE_RATE_LIMIT = 'rate_limit';

    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_SQLI;

    #[ORM\Column(length: 10)]
    private string $severity = self::SEVERITY_MEDIUM;

    #[ORM\Column(length: 45)]
    private string $ipAddress = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestPath = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $requestMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private bool $mailSent = false;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $hitCount = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getRequestPath(): ?string
    {
        return $this->requestPath;
    }

    public function setRequestPath(?string $requestPath): static
    {
        $this->requestPath = $requestPath;

        return $this;
    }

    public function getRequestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function setRequestMethod(?string $requestMethod): static
    {
        $this->requestMethod = $requestMethod;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function isMailSent(): bool
    {
        return $this->mailSent;
    }

    public function setMailSent(bool $mailSent): static
    {
        $this->mailSent = $mailSent;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function setHitCount(int $hitCount): static
    {
        $this->hitCount = $hitCount;

        return $this;
    }

    public function incrementHitCount(int $by = 1): static
    {
        $this->hitCount += $by;

        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
