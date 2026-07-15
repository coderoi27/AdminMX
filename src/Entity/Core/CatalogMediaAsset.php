<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Entity\Admin\AdminUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_assets')]
#[ORM\HasLifecycleCallbacks]
class CatalogMediaAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(length: 64)]
    private string $storageProvider = 'local';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $logicalBucket = null;

    #[ORM\Column(length: 1024)]
    private string $objectKey = '';

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $publicUrl = null;

    #[ORM\Column(length: 120)]
    private string $mimeType = '';

    #[ORM\Column(length: 16)]
    private string $mediaType = CatalogMediaType::IMAGE;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column]
    private int $bytes = 0;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $altText = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(length: 32)]
    private string $sourceType = CatalogMediaSourceType::UNKNOWN;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $licenseName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $attribution = null;

    #[ORM\Column]
    private bool $rightsVerified = false;

    #[ORM\Column]
    private bool $aiGenerated = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $aiTool = null;

    #[ORM\Column(length: 32)]
    private string $status = CatalogMediaStatus::UPLOADING;

    #[ORM\Column(length: 32)]
    private string $moderationStatus = CatalogMediaModerationStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?? Uuid::v4()->toRfc4122();
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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = trim($originalFilename);

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setStorageProvider(string $storageProvider): self
    {
        $this->storageProvider = trim($storageProvider);

        return $this;
    }

    public function getStorageProvider(): string
    {
        return $this->storageProvider;
    }

    public function setLogicalBucket(?string $logicalBucket): self
    {
        $this->logicalBucket = $this->nullableTrim($logicalBucket);

        return $this;
    }

    public function getLogicalBucket(): ?string
    {
        return $this->logicalBucket;
    }

    public function setObjectKey(string $objectKey): self
    {
        $objectKey = trim($objectKey);
        if ($objectKey === '' || str_starts_with($objectKey, '/') || str_contains($objectKey, '..') || str_contains($objectKey, "\0")) {
            throw new \InvalidArgumentException('Catalog media object key is not safe.');
        }

        $this->objectKey = $objectKey;

        return $this;
    }

    public function getObjectKey(): string
    {
        return $this->objectKey;
    }

    public function setPublicUrl(?string $publicUrl): self
    {
        $this->publicUrl = $this->nullableTrim($publicUrl);

        return $this;
    }

    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = trim($mimeType);

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMediaType(string $mediaType): self
    {
        CatalogMediaType::assertValid($mediaType);
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function setDimensions(?int $width, ?int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setBytes(int $bytes): self
    {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Catalog media bytes cannot be negative.');
        }

        $this->bytes = $bytes;

        return $this;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function setChecksum(?string $checksum): self
    {
        $this->checksum = $this->nullableTrim($checksum);

        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setAltText(string $altText): self
    {
        $this->altText = trim($altText);

        return $this;
    }

    public function getAltText(): string
    {
        return $this->altText;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $this->nullableTrim($description);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $this->nullableTrim($author);

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setSourceType(string $sourceType): self
    {
        CatalogMediaSourceType::assertValid($sourceType);
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $this->nullableTrim($sourceUrl);

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setLicenseName(?string $licenseName): self
    {
        $this->licenseName = $this->nullableTrim($licenseName);

        return $this;
    }

    public function getLicenseName(): ?string
    {
        return $this->licenseName;
    }

    public function setAttribution(?string $attribution): self
    {
        $this->attribution = $this->nullableTrim($attribution);

        return $this;
    }

    public function getAttribution(): ?string
    {
        return $this->attribution;
    }

    public function setRightsVerified(bool $rightsVerified): self
    {
        $this->rightsVerified = $rightsVerified;

        return $this;
    }

    public function isRightsVerified(): bool
    {
        return $this->rightsVerified;
    }

    public function setAiGenerated(bool $aiGenerated): self
    {
        $this->aiGenerated = $aiGenerated;
        if ($aiGenerated) {
            $this->sourceType = CatalogMediaSourceType::AI_GENERATED;
        }

        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->aiGenerated;
    }

    public function setAiTool(?string $aiTool): self
    {
        $this->aiTool = $this->nullableTrim($aiTool);

        return $this;
    }

    public function getAiTool(): ?string
    {
        return $this->aiTool;
    }

    public function setStatus(string $status): self
    {
        CatalogMediaStatus::assertValid($status);
        if ($status === CatalogMediaStatus::ACTIVE && !$this->rightsVerified) {
            throw new \InvalidArgumentException('Catalog media asset cannot be activated without verified rights.');
        }

        $this->status = $status;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setModerationStatus(string $moderationStatus): self
    {
        CatalogMediaModerationStatus::assertValid($moderationStatus);
        $this->moderationStatus = $moderationStatus;

        return $this;
    }

    public function getModerationStatus(): string
    {
        return $this->moderationStatus;
    }

    public function setCreatedBy(?AdminUser $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?AdminUser
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === CatalogMediaStatus::ACTIVE && $this->moderationStatus === CatalogMediaModerationStatus::APPROVED;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
