<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_social_links')]
#[ORM\UniqueConstraint(name: 'uniq_location_social_platform', columns: ['location_id', 'platform'])]
#[ORM\HasLifecycleCallbacks]
class LocationSocialLink
{
    public const PLATFORM_INSTAGRAM = 'instagram';
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TIKTOK = 'tiktok';
    public const PLATFORM_WEBSITE = 'website';
    public const PLATFORM_MENU = 'menu';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column(length: 32)]
    private string $platform = self::PLATFORM_WEBSITE;

    #[ORM\Column(length: 1024)]
    private string $url = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private int $sortOrder = 0;

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

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): self
    {
        if (!in_array($platform, self::platforms(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported social platform "%s".', $platform));
        }

        $this->platform = $platform;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = trim($url);

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

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

    /**
     * @return list<string>
     */
    public static function platforms(): array
    {
        return [
            self::PLATFORM_INSTAGRAM,
            self::PLATFORM_FACEBOOK,
            self::PLATFORM_TIKTOK,
            self::PLATFORM_WEBSITE,
            self::PLATFORM_MENU,
        ];
    }
}
