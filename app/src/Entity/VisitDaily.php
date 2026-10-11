<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VisitDailyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: VisitDailyRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_VISIT_DATE', columns: ['visit_date'])]
class VisitDaily
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $visitDate = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $pageViews = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $uniqueVisitors = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitDate(): ?\DateTimeImmutable
    {
        return $this->visitDate;
    }

    public function setVisitDate(\DateTimeImmutable $visitDate): static
    {
        $this->visitDate = $visitDate;

        return $this;
    }

    public function getPageViews(): int
    {
        return $this->pageViews;
    }

    public function setPageViews(int $pageViews): static
    {
        $this->pageViews = $pageViews;

        return $this;
    }

    public function incrementPageViews(): static
    {
        ++$this->pageViews;

        return $this;
    }

    public function getUniqueVisitors(): int
    {
        return $this->uniqueVisitors;
    }

    public function setUniqueVisitors(int $uniqueVisitors): static
    {
        $this->uniqueVisitors = $uniqueVisitors;

        return $this;
    }

    public function incrementUniqueVisitors(): static
    {
        ++$this->uniqueVisitors;

        return $this;
    }
}
