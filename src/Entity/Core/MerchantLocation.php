<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'merchant_locations')]
#[ORM\HasLifecycleCallbacks]
class MerchantLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Merchant::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(length: 16)]
    private string $locationType = 'fixed';

    #[ORM\Column(length: 32)]
    private string $status = 'draft';

    #[ORM\Column(length: 32)]
    private string $publicationState = 'hidden';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phoneE164 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsappE164 = null;

    #[ORM\Column]
    private bool $whatsappEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column]
    private bool $isClaimable = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'location', targetEntity: PlaceAddress::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $addresses;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
    }

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

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): self
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function setLocationType(string $locationType): self
    {
        $this->locationType = $locationType;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPublicationState(): string
    {
        return $this->publicationState;
    }

    public function setPublicationState(string $publicationState): self
    {
        $this->publicationState = $publicationState;

        return $this;
    }

    public function getPhoneE164(): ?string
    {
        return $this->phoneE164;
    }

    public function setPhoneE164(?string $phoneE164): self
    {
        $this->phoneE164 = $phoneE164;

        return $this;
    }

    public function getWhatsappE164(): ?string
    {
        return $this->whatsappE164;
    }

    public function setWhatsappE164(?string $whatsappE164): self
    {
        $this->whatsappE164 = $whatsappE164;

        return $this;
    }

    public function isWhatsappEnabled(): bool
    {
        return $this->whatsappEnabled;
    }

    public function setWhatsappEnabled(bool $whatsappEnabled): self
    {
        $this->whatsappEnabled = $whatsappEnabled;

        return $this;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function setIsClaimable(bool $isClaimable): self
    {
        $this->isClaimable = $isClaimable;

        return $this;
    }

    public function setClaimedAt(?\DateTimeImmutable $claimedAt): self
    {
        $this->claimedAt = $claimedAt;

        return $this;
    }

    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(PlaceAddress $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setLocation($this);
        }

        return $this;
    }
}
