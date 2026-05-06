<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_logs')]
class EventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $eventName;

    #[ORM\Column(length: 32)]
    private string $actorType;

    #[ORM\Column(nullable: true)]
    private ?int $actorId = null;

    #[ORM\Column(length: 64)]
    private string $entityType;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 32)]
    private string $sourceApp;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadataJson = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->occurredAt = $now;
        $this->createdAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getActorType(): string
    {
        return $this->actorType;
    }

    public function setActorType(string $actorType): self
    {
        $this->actorType = $actorType;

        return $this;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function setActorId(?int $actorId): self
    {
        $this->actorId = $actorId;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;

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

    public function getMetadataJson(): ?array
    {
        return $this->metadataJson;
    }

    public function setMetadataJson(?array $metadataJson): self
    {
        $this->metadataJson = $metadataJson;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
