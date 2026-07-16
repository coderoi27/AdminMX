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

    public const MODERATION_PENDING = 'pending';
    public const MODERATION_APPROVED = 'approved';
    public const MODERATION_REJECTED = 'rejected';
    public const MODERATION_QUARANTINED = 'quarantined';

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

    #[ORM\Column(length: 32)]
    private string $moderationStatus = self::MODERATION_APPROVED;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $moderationReasonCategory = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderationNotes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $moderatedAt = null;

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

    public function getModerationStatus(): string
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(string $moderationStatus): self
    {
        if (!in_array($moderationStatus, self::moderationStatuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported media moderation status "%s".', $moderationStatus));
        }

        $this->moderationStatus = $moderationStatus;
        $this->isActive = $moderationStatus === self::MODERATION_APPROVED;

        return $this;
    }

    public function getModerationReasonCategory(): ?string
    {
        return $this->moderationReasonCategory;
    }

    public function setModerationReasonCategory(?string $moderationReasonCategory): self
    {
        $this->moderationReasonCategory = $moderationReasonCategory !== null && trim($moderationReasonCategory) !== ''
            ? mb_substr(trim($moderationReasonCategory), 0, 64)
            : null;

        return $this;
    }

    public function getModerationNotes(): ?string
    {
        return $this->moderationNotes;
    }

    public function setModerationNotes(?string $moderationNotes): self
    {
        $this->moderationNotes = $moderationNotes !== null && trim($moderationNotes) !== '' ? trim($moderationNotes) : null;

        return $this;
    }

    public function getModeratedAt(): ?\DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function moderate(string $status, ?string $reasonCategory = null, ?string $notes = null): self
    {
        $this
            ->setModerationStatus($status)
            ->setModerationReasonCategory($reasonCategory)
            ->setModerationNotes($notes);
        $this->moderatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function mediaTypes(): array
    {
        return [self::TYPE_PHOTO, self::TYPE_LOGO, self::TYPE_MENU];
    }

    /**
     * @return list<string>
     */
    public static function moderationStatuses(): array
    {
        return [
            self::MODERATION_PENDING,
            self::MODERATION_APPROVED,
            self::MODERATION_REJECTED,
            self::MODERATION_QUARANTINED,
        ];
    }
}
