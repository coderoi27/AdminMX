<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demo_zones')]
#[ORM\HasLifecycleCallbacks]
class DemoZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DemoCity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DemoCity $city;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 160)]
    private string $slug;

    #[ORM\Column(length: 16)]
    private string $zoneType = 'tag';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $polygonJson = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
