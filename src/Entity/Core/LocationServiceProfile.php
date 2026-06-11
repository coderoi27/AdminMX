<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_service_profiles')]
#[ORM\HasLifecycleCallbacks]
class LocationServiceProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: MerchantLocation::class, inversedBy: 'serviceProfile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column]
    private bool $offersDelivery = false;

    #[ORM\Column]
    private bool $offersTakeaway = false;

    #[ORM\Column]
    private bool $offersDineIn = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $deliveryNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $serviceNotes = null;

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

    public function offersDelivery(): bool
    {
        return $this->offersDelivery;
    }

    public function setOffersDelivery(bool $offersDelivery): self
    {
        $this->offersDelivery = $offersDelivery;

        return $this;
    }

    public function offersTakeaway(): bool
    {
        return $this->offersTakeaway;
    }

    public function setOffersTakeaway(bool $offersTakeaway): self
    {
        $this->offersTakeaway = $offersTakeaway;

        return $this;
    }

    public function offersDineIn(): bool
    {
        return $this->offersDineIn;
    }

    public function setOffersDineIn(bool $offersDineIn): self
    {
        $this->offersDineIn = $offersDineIn;

        return $this;
    }

    public function getDeliveryNotes(): ?string
    {
        return $this->deliveryNotes;
    }

    public function setDeliveryNotes(?string $deliveryNotes): self
    {
        $this->deliveryNotes = $deliveryNotes !== null && trim($deliveryNotes) !== '' ? trim($deliveryNotes) : null;

        return $this;
    }

    public function getServiceNotes(): ?string
    {
        return $this->serviceNotes;
    }

    public function setServiceNotes(?string $serviceNotes): self
    {
        $this->serviceNotes = $serviceNotes !== null && trim($serviceNotes) !== '' ? trim($serviceNotes) : null;

        return $this;
    }
}
