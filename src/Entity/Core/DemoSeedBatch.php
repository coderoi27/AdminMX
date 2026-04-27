<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\Admin\AdminUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demo_seed_batches')]
#[ORM\HasLifecycleCallbacks]
class DemoSeedBatch
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_PURGED = 'purged';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SystemPlugin::class, inversedBy: 'demoSeedBatches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SystemPlugin $plugin;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 2)]
    private string $countryCode = 'MX';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $regionLabel = null;

    #[ORM\ManyToOne(targetEntity: DemoCity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DemoCity $demoCity = null;

    #[ORM\ManyToOne(targetEntity: DemoZone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DemoZone $demoZone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceAddress = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $centerLatitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $centerLongitude = null;

    #[ORM\Column]
    private int $radiusMeters = 1500;

    #[ORM\Column]
    private int $requestedLocationsCount = 0;

    #[ORM\Column]
    private int $generatedLocationsCount = 0;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $seedValue = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $dictionaryVersion = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configJson = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $seededAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $disabledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $purgedAt = null;

    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $createdByAdmin = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'demoSeedBatch', targetEntity: DemoSeedBatchItem::class)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
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

    public function getPlugin(): SystemPlugin
    {
        return $this->plugin;
    }

    public function setPlugin(SystemPlugin $plugin): self
    {
        $this->plugin = $plugin;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported batch status "%s".', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = strtoupper($countryCode);

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getRegionLabel(): ?string
    {
        return $this->regionLabel;
    }

    public function setRegionLabel(?string $regionLabel): self
    {
        $this->regionLabel = $regionLabel;

        return $this;
    }

    public function getRequestedLocationsCount(): int
    {
        return $this->requestedLocationsCount;
    }

    public function setRequestedLocationsCount(int $requestedLocationsCount): self
    {
        $this->requestedLocationsCount = $requestedLocationsCount;

        return $this;
    }

    public function getGeneratedLocationsCount(): int
    {
        return $this->generatedLocationsCount;
    }

    public function setGeneratedLocationsCount(int $generatedLocationsCount): self
    {
        $this->generatedLocationsCount = $generatedLocationsCount;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
            self::STATUS_DISABLED,
            self::STATUS_PURGED,
            self::STATUS_FAILED,
        ];
    }
}
