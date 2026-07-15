<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_usage_pools')]
#[ORM\HasLifecycleCallbacks]
class CatalogMediaUsagePool
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 160, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 40)]
    private string $usageSlot = CatalogMediaUsageSlot::CATEGORY_DEFAULT;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $poolVersion = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?? Uuid::v4()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim(mb_strtolower($slug));

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setDescription(?string $description): self
    {
        $description = $description !== null ? trim($description) : null;
        $this->description = $description !== '' ? $description : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setUsageSlot(string $usageSlot): self
    {
        CatalogMediaUsageSlot::assertValid($usageSlot);
        $this->usageSlot = $usageSlot;

        return $this;
    }

    public function getUsageSlot(): string
    {
        return $this->usageSlot;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setPoolVersion(int $poolVersion): self
    {
        if ($poolVersion < 1) {
            throw new \InvalidArgumentException('Catalog media pool version must be positive.');
        }

        $this->poolVersion = $poolVersion;

        return $this;
    }

    public function getPoolVersion(): int
    {
        return $this->poolVersion;
    }
}
