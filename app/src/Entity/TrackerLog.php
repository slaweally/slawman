<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackerLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackerLogRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_tracker_log_created_at')]
#[ORM\Index(columns: ['session_key'], name: 'idx_tracker_log_session_key')]
class TrackerLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $eventType = 'visit';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(length: 45)]
    private string $ipAddress = '';

    #[ORM\Column(length: 45)]
    private string $maskedIp = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pageUrl = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sessionKey = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $templateId = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

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

    public function getMaskedIp(): string
    {
        return $this->maskedIp;
    }

    public function setMaskedIp(string $maskedIp): static
    {
        $this->maskedIp = $maskedIp;

        return $this;
    }

    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    public function setPageUrl(?string $pageUrl): static
    {
        $this->pageUrl = $pageUrl;

        return $this;
    }

    public function getSessionKey(): ?string
    {
        return $this->sessionKey;
    }

    public function setSessionKey(?string $sessionKey): static
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

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

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function setTemplateId(?int $templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }
}
