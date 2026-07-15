<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_media_asset_tags')]
#[ORM\UniqueConstraint(name: 'UNIQ_CATALOG_MEDIA_ASSET_TAG', columns: ['asset_id', 'tag_id'])]
class CatalogMediaAssetTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogMediaAsset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CatalogMediaAsset $asset;

    #[ORM\ManyToOne(targetEntity: CatalogMediaTag::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CatalogMediaTag $tag;

    public function setAsset(CatalogMediaAsset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }

    public function setTag(CatalogMediaTag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }
}
