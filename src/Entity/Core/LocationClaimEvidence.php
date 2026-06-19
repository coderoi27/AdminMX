<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_claim_evidences')]
#[ORM\HasLifecycleCallbacks]
class LocationClaimEvidence
{
    public const STATUS_PENDING_UPLOAD = 'pending_upload';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_DELETED = 'deleted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocationClaimRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocationClaimRequest $claim;

    #[ORM\Column(length: 64)]
    private string $evidenceType = '';

    #[ORM\Column(length: 64)]
    private string $storageProvider = '';

    #[ORM\Column(length: 120)]
    private string $bucketName = '';

    #[ORM\Column(length: 512)]
    private string $objectKey = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storageObjectId = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = '';

    #[ORM\Column]
    private int $sizeBytes = 0;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING_UPLOAD;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadataJson = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $replacedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

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

    public function getId(): ?int { return $this->id; }
    public function getClaim(): LocationClaimRequest { return $this->claim; }
    public function setClaim(LocationClaimRequest $claim): self { $this->claim = $claim; return $this; }
    public function getEvidenceType(): string { return $this->evidenceType; }
    public function setEvidenceType(string $evidenceType): self { $this->evidenceType = $evidenceType; return $this; }
    public function getStorageProvider(): string { return $this->storageProvider; }
    public function setStorageProvider(string $storageProvider): self { $this->storageProvider = $storageProvider; return $this; }
    public function getBucketName(): string { return $this->bucketName; }
    public function setBucketName(string $bucketName): self { $this->bucketName = $bucketName; return $this; }
    public function getObjectKey(): string { return $this->objectKey; }
    public function setObjectKey(string $objectKey): self { $this->objectKey = $objectKey; return $this; }
    public function getStorageObjectId(): ?string { return $this->storageObjectId; }
    public function setStorageObjectId(?string $storageObjectId): self { $this->storageObjectId = $storageObjectId; return $this; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $originalFilename): self { $this->originalFilename = $originalFilename; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): self { $this->mimeType = $mimeType; return $this; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $sizeBytes): self { $this->sizeBytes = $sizeBytes; return $this; }
    public function getChecksumSha256(): ?string { return $this->checksumSha256; }
    public function setChecksumSha256(?string $checksumSha256): self { $this->checksumSha256 = $checksumSha256; return $this; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function setDurationSeconds(?int $durationSeconds): self { $this->durationSeconds = $durationSeconds; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getMetadataJson(): ?array { return $this->metadataJson; }
    public function setMetadataJson(?array $metadataJson): self { $this->metadataJson = $metadataJson; return $this; }
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): self { $this->uploadedAt = $uploadedAt; return $this; }
    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self { $this->verifiedAt = $verifiedAt; return $this; }
    public function getReplacedAt(): ?\DateTimeImmutable { return $this->replacedAt; }
    public function setReplacedAt(?\DateTimeImmutable $replacedAt): self { $this->replacedAt = $replacedAt; return $this; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self { $this->deletedAt = $deletedAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
