<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_opening_exceptions')]
#[ORM\UniqueConstraint(name: 'uniq_location_opening_exception_date', columns: ['location_id', 'exception_date'])]
#[ORM\HasLifecycleCallbacks]
class LocationOpeningException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class, inversedBy: 'openingExceptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $exceptionDate;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

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

    public function getExceptionDate(): \DateTimeImmutable
    {
        return $this->exceptionDate;
    }

    public function setExceptionDate(\DateTimeImmutable $exceptionDate): self
    {
        $this->exceptionDate = $exceptionDate;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label !== null && trim($label) !== '' ? trim($label) : null;

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
