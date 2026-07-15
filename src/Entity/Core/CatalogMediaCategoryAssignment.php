<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_category_assignments')]
#[ORM\Index(name: 'IDX_CATALOG_MEDIA_CATEGORY_SLOT', columns: ['category_id', 'usage_slot'])]
#[ORM\HasLifecycleCallbacks]
class CatalogMediaCategoryAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocationCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocationCategory $category;

    #[ORM\ManyToOne(targetEntity: CatalogMediaUsagePool::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CatalogMediaUsagePool $pool = null;

    #[ORM\ManyToOne(targetEntity: CatalogMediaAsset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CatalogMediaAsset $asset = null;

    #[ORM\Column(length: 40)]
    private string $usageSlot = CatalogMediaUsageSlot::CATEGORY_DEFAULT;

    #[ORM\Column]
    private int $priority = 0;

    #[ORM\Column]
    private int $weight = 1;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

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

    public function setCategory(LocationCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): LocationCategory
    {
        return $this->category;
    }

    public function setPool(?CatalogMediaUsagePool $pool): self
    {
        $this->pool = $pool;

        return $this;
    }

    public function getPool(): ?CatalogMediaUsagePool
    {
        return $this->pool;
    }

    public function setAsset(?CatalogMediaAsset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function getAsset(): ?CatalogMediaAsset
    {
        return $this->asset;
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

    public function setPriority(int $priority): self
    {
        if ($priority < 0) {
            throw new \InvalidArgumentException('Catalog media assignment priority cannot be negative.');
        }

        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setWeight(int $weight): self
    {
        if ($weight < 0) {
            throw new \InvalidArgumentException('Catalog media assignment weight cannot be negative.');
        }

        $this->weight = $weight;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
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

    public function setValidity(?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo): self
    {
        if ($validFrom instanceof \DateTimeImmutable && $validTo instanceof \DateTimeImmutable && $validFrom > $validTo) {
            throw new \InvalidArgumentException('Catalog media assignment valid_from must be before valid_to.');
        }

        $this->validFrom = $validFrom;
        $this->validTo = $validTo;

        return $this;
    }

    public function assertValid(): void
    {
        if (!$this->pool instanceof CatalogMediaUsagePool && !$this->asset instanceof CatalogMediaAsset) {
            throw new \InvalidArgumentException('Catalog media assignment requires a pool or an asset.');
        }

        if ($this->asset instanceof CatalogMediaAsset) {
            CatalogMediaUsageSlot::assertAcceptsMediaType($this->usageSlot, $this->asset->getMediaType());
        }

        if ($this->pool instanceof CatalogMediaUsagePool && $this->pool->getUsageSlot() !== $this->usageSlot) {
            throw new \InvalidArgumentException('Catalog media assignment pool usage slot must match assignment usage slot.');
        }
    }
}
