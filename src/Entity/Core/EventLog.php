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
}
