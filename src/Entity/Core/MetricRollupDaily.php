<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'metric_rollups_daily')]
#[ORM\UniqueConstraint(name: 'uniq_metric_rollup_day_event_source', columns: ['rollup_date', 'event_name', 'source_app'])]
class MetricRollupDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $rollupDate;

    #[ORM\Column(length: 120)]
    private string $eventName = '';

    #[ORM\Column(length: 32)]
    private string $sourceApp = 'public';

    #[ORM\Column]
    private int $eventCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $computedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRollupDate(): \DateTimeImmutable
    {
        return $this->rollupDate;
    }

    public function setRollupDate(\DateTimeImmutable $rollupDate): self
    {
        $this->rollupDate = $rollupDate;

        return $this;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): self
    {
        $this->eventName = $eventName;

        return $this;
    }

    public function getSourceApp(): string
    {
        return $this->sourceApp;
    }

    public function setSourceApp(string $sourceApp): self
    {
        $this->sourceApp = $sourceApp;

        return $this;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function setEventCount(int $eventCount): self
    {
        $this->eventCount = $eventCount;

        return $this;
    }

    public function setComputedAt(?\DateTimeImmutable $computedAt): self
    {
        $this->computedAt = $computedAt;

        return $this;
    }

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }
}
