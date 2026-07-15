<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_tags')]
#[ORM\HasLifecycleCallbacks]
class CatalogMediaTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $uuid;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(name: 'tag_group', length: 80, nullable: true)]
    private ?string $group = null;

    #[ORM\Column]
    private bool $active = true;

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

    public function setGroup(?string $group): self
    {
        $group = $group !== null ? trim($group) : null;
        $this->group = $group !== '' ? $group : null;

        return $this;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
