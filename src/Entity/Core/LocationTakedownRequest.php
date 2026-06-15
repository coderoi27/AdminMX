<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_takedown_requests')]
#[ORM\HasLifecycleCallbacks]
class LocationTakedownRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public const ACTION_NONE = 'none';
    public const ACTION_HIDE = 'hide';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_ARCHIVE = 'archive';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $location;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 64)]
    private string $reasonCategory = 'other';

    #[ORM\Column(type: 'text')]
    private string $reasonText = '';

    #[ORM\Column(length: 32)]
    private string $reportedByType = 'admin';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reportedByEmail = null;

    #[ORM\Column(length: 32)]
    private string $resolutionAction = self::ACTION_NONE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionNotes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported takedown status "%s".', $status));
        }

        if (!$this->canTransitionTo($status)) {
            throw new \InvalidArgumentException(sprintf('Invalid takedown status transition "%s" -> "%s".', $this->status, $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getReasonCategory(): string
    {
        return $this->reasonCategory;
    }

    public function setReasonCategory(string $reasonCategory): self
    {
        $this->reasonCategory = mb_substr($reasonCategory, 0, 64);

        return $this;
    }

    public function getReasonText(): string
    {
        return $this->reasonText;
    }

    public function setReasonText(string $reasonText): self
    {
        $this->reasonText = $reasonText;

        return $this;
    }

    public function getReportedByType(): string
    {
        return $this->reportedByType;
    }

    public function setReportedByType(string $reportedByType): self
    {
        $this->reportedByType = mb_substr($reportedByType, 0, 32);

        return $this;
    }

    public function getReportedByEmail(): ?string
    {
        return $this->reportedByEmail;
    }

    public function setReportedByEmail(?string $reportedByEmail): self
    {
        $this->reportedByEmail = $reportedByEmail !== null && trim($reportedByEmail) !== ''
            ? mb_strtolower(trim($reportedByEmail))
            : null;

        return $this;
    }

    public function getResolutionAction(): string
    {
        return $this->resolutionAction;
    }

    public function setResolutionAction(string $resolutionAction): self
    {
        if (!in_array($resolutionAction, self::resolutionActions(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported takedown resolution action "%s".', $resolutionAction));
        }

        $this->resolutionAction = $resolutionAction;

        return $this;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $resolutionNotes): self
    {
        $this->resolutionNotes = $resolutionNotes !== null && trim($resolutionNotes) !== '' ? trim($resolutionNotes) : null;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
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
            self::STATUS_PENDING,
            self::STATUS_REVIEWING,
            self::STATUS_RESOLVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function resolutionActions(): array
    {
        return [
            self::ACTION_NONE,
            self::ACTION_HIDE,
            self::ACTION_SUSPEND,
            self::ACTION_ARCHIVE,
        ];
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
            self::STATUS_PENDING => [self::STATUS_REVIEWING, self::STATUS_REJECTED, self::STATUS_RESOLVED],
            self::STATUS_REVIEWING => [self::STATUS_RESOLVED, self::STATUS_REJECTED],
            self::STATUS_RESOLVED => [],
            self::STATUS_REJECTED => [self::STATUS_REVIEWING],
        ];
    }
}
