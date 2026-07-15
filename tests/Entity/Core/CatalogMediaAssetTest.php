<?php

declare(strict_types=1);

namespace App\Tests\Entity\Core;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Entity\Core\CatalogMediaAsset;
use PHPUnit\Framework\TestCase;

final class CatalogMediaAssetTest extends TestCase
{
    public function testAssetRequiresRightsBeforeActivation(): void
    {
        $asset = (new CatalogMediaAsset())
            ->setOriginalFilename('cover.jpg')
            ->setObjectKey('catalog-media/originals/2026/07/asset/original.jpg')
            ->setMimeType('image/jpeg')
            ->setMediaType(CatalogMediaType::IMAGE)
            ->setBytes(1200)
            ->setTitle('Cover')
            ->setAltText('Mesa con comida')
            ->setSourceType(CatalogMediaSourceType::OWN);

        $this->expectException(\InvalidArgumentException::class);

        $asset->setStatus(CatalogMediaStatus::ACTIVE);
    }

    public function testAssetCanBecomeActiveWithVerifiedRightsAndApprovedModeration(): void
    {
        $asset = (new CatalogMediaAsset())
            ->setOriginalFilename('cover.jpg')
            ->setObjectKey('catalog-media/originals/2026/07/asset/original.jpg')
            ->setMimeType('image/jpeg')
            ->setMediaType(CatalogMediaType::IMAGE)
            ->setBytes(1200)
            ->setTitle('Cover')
            ->setAltText('Mesa con comida')
            ->setSourceType(CatalogMediaSourceType::OWN)
            ->setRightsVerified(true)
            ->setModerationStatus(CatalogMediaModerationStatus::APPROVED)
            ->setStatus(CatalogMediaStatus::ACTIVE);

        self::assertTrue($asset->isRightsVerified());
        self::assertTrue($asset->isActive());
    }

    public function testAiGeneratedSetsSourceType(): void
    {
        $asset = (new CatalogMediaAsset())->setAiGenerated(true);

        self::assertTrue($asset->isAiGenerated());
        self::assertSame(CatalogMediaSourceType::AI_GENERATED, $asset->getSourceType());
    }

    public function testObjectKeyRejectsUnsafePath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogMediaAsset())->setObjectKey('../claims/private.jpg');
    }
}
