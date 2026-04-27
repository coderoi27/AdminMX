<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'system_plugins')]
#[ORM\HasLifecycleCallbacks]
class SystemPlugin
{
    public const DEMO_SEED_LOCATIONS = 'demo_seed_locations';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_MAINTENANCE = 'maintenance';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $pluginKey;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column]
    private bool $isEnabled = false;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_DISABLED;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configJson = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'plugin', targetEntity: DemoSeedBatch::class)]
    private Collection $demoSeedBatches;

    public function __construct()
    {
        $this->demoSeedBatches = new ArrayCollection();
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

    public function getPluginKey(): string
    {
        return $this->pluginKey;
    }

    public function setPluginKey(string $pluginKey): self
    {
        $this->pluginKey = $pluginKey;

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

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported plugin status "%s".', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getConfigJson(): ?array
    {
        return $this->configJson;
    }

    public function setConfigJson(?array $configJson): self
    {
        $this->configJson = $configJson;

        return $this;
    }

    public function getLastRunAt(): ?\DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self
    {
        $this->lastRunAt = $lastRunAt;

        return $this;
    }

    public function getDemoSeedBatches(): Collection
    {
        return $this->demoSeedBatches;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_DISABLED,
            self::STATUS_MAINTENANCE,
        ];
    }
}
