<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_albums')]
#[ORM\HasLifecycleCallbacks]
class CatalogMediaAlbum
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

    #[ORM\ManyToOne(targetEntity: CatalogMediaAsset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CatalogMediaAsset $coverAsset = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private int $version = 1;

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

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
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

    public function setCoverAsset(?CatalogMediaAsset $coverAsset): self
    {
        $this->coverAsset = $coverAsset;

        return $this;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function setVersion(int $version): self
    {
        if ($version < 1) {
            throw new \InvalidArgumentException('Catalog media album version must be positive.');
        }

        $this->version = $version;

        return $this;
    }
}
