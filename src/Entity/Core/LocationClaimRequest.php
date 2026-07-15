<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_claim_requests')]
#[ORM\HasLifecycleCallbacks]
class LocationClaimRequest
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_EMAIL_VERIFICATION = 'pending_email_verification';
    public const STATUS_PENDING_EVIDENCE = 'pending_evidence';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_NEEDS_INFO = 'needs_info';
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

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $claimantName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

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

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $claimUuid = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $claimantRole = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $claimantPhoneE164 = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $businessPhoneE164 = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $proposedName = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $proposedAddressJson = null;

    #[ORM\Column(nullable: true)]
    private ?float $confirmedLatitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $confirmedLongitude = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalAcceptanceReference = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resumeTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resumeTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resumeTokenRevokedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastCompletedStep = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $underReviewAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $needsInfoAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $convertedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $submissionMode = null;

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

    public function getClaimUuid(): ?string
    {
        return $this->claimUuid;
    }

    public function setClaimUuid(?string $claimUuid): self
    {
        $this->claimUuid = $claimUuid;

        return $this;
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

    public function getClaimantName(): ?string
    {
        return $this->claimantName;
    }

    public function setClaimantName(?string $claimantName): self
    {
        $this->claimantName = $claimantName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email === null ? null : mb_strtolower($email);

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

        $now = new \DateTimeImmutable();
        match ($status) {
            self::STATUS_SUBMITTED => $this->submittedAt = $now,
            self::STATUS_UNDER_REVIEW => $this->underReviewAt = $now,
            self::STATUS_NEEDS_INFO => $this->needsInfoAt = $now,
            self::STATUS_APPROVED => $this->approvedAt = $now,
            self::STATUS_REJECTED => $this->rejectedAt = $now,
            self::STATUS_CONVERTED => $this->convertedAt = $now,
            default => null,
        };

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

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
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
            self::STATUS_DRAFT,
            self::STATUS_PENDING_EMAIL_VERIFICATION,
            self::STATUS_PENDING_EVIDENCE,
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_NEEDS_INFO,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CONVERTED,
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'draft',
            self::STATUS_PENDING_EMAIL_VERIFICATION => 'pending_email_verification',
            self::STATUS_PENDING_EVIDENCE => 'pending_evidence',
            self::STATUS_SUBMITTED => 'submitted',
            self::STATUS_UNDER_REVIEW => 'under_review',
            self::STATUS_NEEDS_INFO => 'needs_info',
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
            self::STATUS_DRAFT => [self::STATUS_PENDING_EMAIL_VERIFICATION],
            self::STATUS_PENDING_EMAIL_VERIFICATION => [self::STATUS_PENDING_EVIDENCE],
            self::STATUS_PENDING_EVIDENCE => [self::STATUS_SUBMITTED],
            self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW],
            self::STATUS_UNDER_REVIEW => [self::STATUS_NEEDS_INFO, self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_NEEDS_INFO => [self::STATUS_PENDING_EVIDENCE, self::STATUS_SUBMITTED, self::STATUS_REJECTED],
            self::STATUS_APPROVED => [self::STATUS_CONVERTED],
            self::STATUS_REJECTED => [],
            self::STATUS_CONVERTED => [],
        ];
    }

    public function getSubmissionMode(): ?string
    {
        return $this->submissionMode;
    }

    public function setSubmissionMode(?string $submissionMode): self
    {
        $this->submissionMode = $submissionMode;

        return $this;
    }

    public function getClaimantRole(): ?string
    {
        return $this->claimantRole;
    }

    public function getClaimantPhoneE164(): ?string
    {
        return $this->claimantPhoneE164;
    }

    public function getBusinessPhoneE164(): ?string
    {
        return $this->businessPhoneE164;
    }

    public function getProposedName(): ?string
    {
        return $this->proposedName;
    }

    public function getProposedAddressJson(): ?array
    {
        return $this->proposedAddressJson;
    }

    public function getConfirmedLatitude(): ?float
    {
        return $this->confirmedLatitude;
    }

    public function getConfirmedLongitude(): ?float
    {
        return $this->confirmedLongitude;
    }

    public function getLegalAcceptanceReference(): ?string
    {
        return $this->legalAcceptanceReference;
    }

    public function setLegalAcceptanceReference(?string $legalAcceptanceReference): self
    {
        $this->legalAcceptanceReference = $legalAcceptanceReference;

        return $this;
    }

    public function getResumeTokenHash(): ?string
    {
        return $this->resumeTokenHash;
    }

    public function setResumeTokenHash(?string $resumeTokenHash): self
    {
        $this->resumeTokenHash = $resumeTokenHash;

        return $this;
    }

    public function getResumeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resumeTokenExpiresAt;
    }

    public function setResumeTokenExpiresAt(?\DateTimeImmutable $resumeTokenExpiresAt): self
    {
        $this->resumeTokenExpiresAt = $resumeTokenExpiresAt;

        return $this;
    }

    public function getResumeTokenRevokedAt(): ?\DateTimeImmutable
    {
        return $this->resumeTokenRevokedAt;
    }

    public function setResumeTokenRevokedAt(?\DateTimeImmutable $resumeTokenRevokedAt): self
    {
        $this->resumeTokenRevokedAt = $resumeTokenRevokedAt;

        return $this;
    }

    public function getLastCompletedStep(): ?string
    {
        return $this->lastCompletedStep;
    }

    public function setLastCompletedStep(?string $lastCompletedStep): self
    {
        $this->lastCompletedStep = $lastCompletedStep;

        return $this;
    }

    /**
     * Get the datetime when the email was verified.
     */
    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /**
     * Mark the email as verified.
     *
     * @param \DateTimeImmutable|null $dateTime Use a specific datetime or defaults to now.
     */
    public function markEmailVerified(?\DateTimeImmutable $dateTime = null): self
    {
        $this->emailVerifiedAt = $dateTime ?? new \DateTimeImmutable();
        return $this;
    }
}
