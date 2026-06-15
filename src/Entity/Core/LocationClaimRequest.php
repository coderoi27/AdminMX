<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_claim_requests')]
#[ORM\HasLifecycleCallbacks]
class LocationClaimRequest
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $sourceType = MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES;

    #[ORM\Column(nullable: true)]
    private ?int $canonicalLocationId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalSourceKey = null;

    #[ORM\Column(length: 180)]
    private string $locationName = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortAddress = null;

    #[ORM\Column(length: 160)]
    private string $claimantName = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsappE164 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_SUBMITTED;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $prefillPayloadJson = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $evidenceLinksJson = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $reviewChecklistJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewNotes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

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

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): self
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getCanonicalLocationId(): ?int
    {
        return $this->canonicalLocationId;
    }

    public function setCanonicalLocationId(?int $canonicalLocationId): self
    {
        $this->canonicalLocationId = $canonicalLocationId;

        return $this;
    }

    public function getExternalSourceKey(): ?string
    {
        return $this->externalSourceKey;
    }

    public function setExternalSourceKey(?string $externalSourceKey): self
    {
        $this->externalSourceKey = $externalSourceKey;

        return $this;
    }

    public function getLocationName(): string
    {
        return $this->locationName;
    }

    public function setLocationName(string $locationName): self
    {
        $this->locationName = $locationName;

        return $this;
    }

    public function getShortAddress(): ?string
    {
        return $this->shortAddress;
    }

    public function setShortAddress(?string $shortAddress): self
    {
        $this->shortAddress = $shortAddress;

        return $this;
    }

    public function getClaimantName(): string
    {
        return $this->claimantName;
    }

    public function setClaimantName(string $claimantName): self
    {
        $this->claimantName = $claimantName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported claim status "%s".', $status));
        }

        if (!$this->canTransitionTo($status)) {
            throw new \InvalidArgumentException(sprintf('Invalid claim status transition "%s" -> "%s".', $this->status, $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getPrefillPayloadJson(): ?array
    {
        return $this->prefillPayloadJson;
    }

    public function setPrefillPayloadJson(?array $prefillPayloadJson): self
    {
        $this->prefillPayloadJson = $prefillPayloadJson;

        return $this;
    }

    public function getEvidenceLinksJson(): ?array
    {
        return $this->evidenceLinksJson;
    }

    public function setEvidenceLinksJson(?array $evidenceLinksJson): self
    {
        $this->evidenceLinksJson = $evidenceLinksJson;

        return $this;
    }

    public function getReviewChecklistJson(): ?array
    {
        return $this->reviewChecklistJson;
    }

    public function setReviewChecklistJson(?array $reviewChecklistJson): self
    {
        $this->reviewChecklistJson = $reviewChecklistJson;

        return $this;
    }

    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function setReviewNotes(?string $reviewNotes): self
    {
        $this->reviewNotes = $reviewNotes;

        return $this;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CONVERTED,
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'submitted',
            self::STATUS_UNDER_REVIEW => 'under_review',
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            self::STATUS_CONVERTED => 'converted',
            default => $status,
        };
    }

    public function canTransitionTo(string $status): bool
    {
        if ($status === $this->status || $this->id === null) {
            return true;
        }

        return in_array($status, self::statusTransitions()[$this->status] ?? [], true);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function statusTransitions(): array
    {
        return [
            self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED],
            self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_APPROVED => [self::STATUS_CONVERTED],
            self::STATUS_REJECTED => [self::STATUS_UNDER_REVIEW],
            self::STATUS_CONVERTED => [],
        ];
    }
}
