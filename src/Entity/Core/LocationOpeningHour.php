<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_opening_hours')]
#[ORM\UniqueConstraint(name: 'uniq_location_opening_day', columns: ['location_id', 'day_of_week'])]
#[ORM\HasLifecycleCallbacks]
class LocationOpeningHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class, inversedBy: 'openingHours')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column(type: 'smallint')]
    private int $dayOfWeek = 1;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column]
    private bool $isClosed = true;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): MerchantLocation
    {
        return $this->location;
    }

    public function setLocation(MerchantLocation $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            throw new \InvalidArgumentException('Day of week must be between 1 and 7.');
        }

        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTimeImmutable $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): self
    {
        $this->isClosed = $isClosed;

        return $this;
    }
}
