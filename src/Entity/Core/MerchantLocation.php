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
    public const SOURCE_TYPE_OWNER_REGISTERED = 'owner_registered';
    public const SOURCE_TYPE_FAKE_SEED = 'fake_seed';
    public const SOURCE_TYPE_GOOGLE_PLACES = 'google_places';
    public const SOURCE_TYPE_CLAIMED = 'claimed';
    public const SOURCE_TYPE_ADMIN_CURATED = 'admin_curated';

    public const PUBLICATION_STATE_HIDDEN = 'hidden';
    public const PUBLICATION_STATE_PENDING_VISIBLE = 'pending_visible';
    public const PUBLICATION_STATE_PUBLIC_VISIBLE = 'public_visible';
    public const PUBLICATION_STATE_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Merchant::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 16)]
    private string $locationType = 'fixed';

    #[ORM\Column(length: 32)]
    private string $status = 'draft';

    #[ORM\Column(length: 32)]
    private string $publicationState = 'hidden';

    #[ORM\Column(length: 32)]
    private string $sourceType = self::SOURCE_TYPE_OWNER_REGISTERED;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalSourceKey = null;

    #[ORM\ManyToOne(targetEntity: LocationCategory::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocationCategory $primaryCategory = null;

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

    #[ORM\Column(length: 24)]
    private string $gemStatus = 'none';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $gemReasonTags = null;

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

    public function getLocationType(): string
    {
        return $this->locationType;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPublicationState(): string
    {
        return $this->publicationState;
    }

    public function setPublicationState(string $publicationState): self
    {
        if (!in_array($publicationState, self::publicationStates(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported publication state "%s".', $publicationState));
        }

        $this->publicationState = $publicationState;

        return $this;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): self
    {
        if (!in_array($sourceType, self::sourceTypes(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported source type "%s".', $sourceType));
        }

        $this->sourceType = $sourceType;

        return $this;
    }

    public function getPrimaryCategory(): ?LocationCategory
    {
        return $this->primaryCategory;
    }

    public function setPrimaryCategory(?LocationCategory $primaryCategory): self
    {
        $this->primaryCategory = $primaryCategory;

        return $this;
    }

    public function getExternalSourceKey(): ?string
    {
        return $this->externalSourceKey;
    }

    public function setExternalSourceKey(?string $externalSourceKey): self
    {
        $this->externalSourceKey = $externalSourceKey !== null && trim($externalSourceKey) !== '' ? trim($externalSourceKey) : null;

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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setIsClaimable(bool $isClaimable): self
    {
        $this->isClaimable = $isClaimable;

        return $this;
    }

    public function getGemStatus(): string
    {
        return $this->gemStatus;
    }

    public function setGemStatus(string $gemStatus): self
    {
        if (!in_array($gemStatus, ['none', 'pending', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported gem status "%s".', $gemStatus));
        }

        $this->gemStatus = $gemStatus;

        return $this;
    }

    public function getGemReasonTags(): ?array
    {
        return $this->gemReasonTags;
    }

    public function setGemReasonTags(?array $gemReasonTags): self
    {
        $this->gemReasonTags = $gemReasonTags !== [] ? $gemReasonTags : null;

        return $this;
    }

    public function isEditorialGem(): bool
    {
        return $this->gemStatus === 'approved';
    }

    public function isClaimable(): bool
    {
        return $this->isClaimable;
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

    public function getPrimaryAddress(): ?PlaceAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->isPrimary()) {
                return $address;
            }
        }

        $firstAddress = $this->addresses->first();

        return $firstAddress instanceof PlaceAddress ? $firstAddress : null;
    }

    /**
     * @return list<string>
     */
    public static function sourceTypes(): array
    {
        return [
            self::SOURCE_TYPE_OWNER_REGISTERED,
            self::SOURCE_TYPE_FAKE_SEED,
            self::SOURCE_TYPE_GOOGLE_PLACES,
            self::SOURCE_TYPE_CLAIMED,
            self::SOURCE_TYPE_ADMIN_CURATED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function publicationStates(): array
    {
        return [
            self::PUBLICATION_STATE_HIDDEN,
            self::PUBLICATION_STATE_PENDING_VISIBLE,
            self::PUBLICATION_STATE_PUBLIC_VISIBLE,
            self::PUBLICATION_STATE_ARCHIVED,
        ];
    }
}
