<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_media_items')]
#[ORM\HasLifecycleCallbacks]
class LocationMediaItem
{
    public const TYPE_PHOTO = 'photo';
    public const TYPE_LOGO = 'logo';
    public const TYPE_MENU = 'menu';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class, inversedBy: 'mediaItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column(length: 32)]
    private string $mediaType = self::TYPE_PHOTO;

    #[ORM\Column(length: 1024)]
    private string $url = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isPrimary = false;

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

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): self
    {
        if (!in_array($mediaType, self::mediaTypes(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported media type "%s".', $mediaType));
        }

        $this->mediaType = $mediaType;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title !== null && trim($title) !== '' ? trim($title) : null;

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText !== null && trim($altText) !== '' ? trim($altText) : null;

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

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;

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
    public static function mediaTypes(): array
    {
        return [self::TYPE_PHOTO, self::TYPE_LOGO, self::TYPE_MENU];
    }
}
