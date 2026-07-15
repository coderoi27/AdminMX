<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_categories')]
#[ORM\HasLifecycleCallbacks]
class LocationCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $iconKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconAssetUrl = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $colorHex = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultPhotoUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPhotoUrl = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $regionalStrategy = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $featuredRegionScope = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $googlePlaceTypeMappings = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'primaryCategory', targetEntity: MerchantLocation::class)]
    private Collection $locations;

    public function __construct()
    {
        $this->locations = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim(mb_strtolower($slug));

        return $this;
    }

    public function getIconKey(): ?string
    {
        return $this->iconKey;
    }

    public function setIconKey(?string $iconKey): self
    {
        $this->iconKey = $iconKey !== null && trim($iconKey) !== '' ? trim($iconKey) : null;

        return $this;
    }

    public function getIconAssetUrl(): ?string
    {
        return $this->iconAssetUrl;
    }

    public function setIconAssetUrl(?string $iconAssetUrl): self
    {
        $this->iconAssetUrl = $iconAssetUrl !== null && trim($iconAssetUrl) !== '' ? trim($iconAssetUrl) : null;

        return $this;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function setColorHex(?string $colorHex): self
    {
        $normalized = $colorHex !== null ? strtoupper(trim($colorHex)) : null;
        $this->colorHex = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getDefaultPhotoUrl(): ?string
    {
        return $this->defaultPhotoUrl;
    }

    public function setDefaultPhotoUrl(?string $defaultPhotoUrl): self
    {
        $this->defaultPhotoUrl = $defaultPhotoUrl !== null && trim($defaultPhotoUrl) !== '' ? trim($defaultPhotoUrl) : null;

        return $this;
    }

    public function getCoverPhotoUrl(): ?string
    {
        return $this->coverPhotoUrl;
    }

    public function setCoverPhotoUrl(?string $coverPhotoUrl): self
    {
        $this->coverPhotoUrl = $coverPhotoUrl !== null && trim($coverPhotoUrl) !== '' ? trim($coverPhotoUrl) : null;

        return $this;
    }

    public function getRegionalStrategy(): ?string
    {
        return $this->regionalStrategy;
    }

    public function setRegionalStrategy(?string $regionalStrategy): self
    {
        $this->regionalStrategy = $regionalStrategy !== null && trim($regionalStrategy) !== '' ? trim($regionalStrategy) : null;

        return $this;
    }

    public function getFeaturedRegionScope(): ?string
    {
        return $this->featuredRegionScope;
    }

    public function setFeaturedRegionScope(?string $featuredRegionScope): self
    {
        $this->featuredRegionScope = $featuredRegionScope !== null && trim($featuredRegionScope) !== '' ? trim($featuredRegionScope) : null;

        return $this;
    }

    public function getGooglePlaceTypeMappings(): ?array
    {
        return $this->googlePlaceTypeMappings;
    }

    public function setGooglePlaceTypeMappings(?array $googlePlaceTypeMappings): self
    {
        $this->googlePlaceTypeMappings = $googlePlaceTypeMappings;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLocations(): Collection
    {
        return $this->locations;
    }
}
