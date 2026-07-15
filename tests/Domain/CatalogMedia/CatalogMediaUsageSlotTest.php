<?php

declare(strict_types=1);

namespace App\Tests\Domain\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use PHPUnit\Framework\TestCase;

final class CatalogMediaUsageSlotTest extends TestCase
{
    public function testUsageSlotsValidateMediaTypes(): void
    {
        self::assertTrue(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::STORY_VIDEO, CatalogMediaType::VIDEO));
        self::assertFalse(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::STORY_VIDEO, CatalogMediaType::IMAGE));
        self::assertTrue(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::LOCATION_GALLERY, CatalogMediaType::IMAGE));
        self::assertFalse(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::LOCATION_GALLERY, CatalogMediaType::VIDEO));
        self::assertTrue(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::CATEGORY_ICON, CatalogMediaType::ICON));
        self::assertTrue(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::CATEGORY_ICON, CatalogMediaType::IMAGE));
        self::assertTrue(CatalogMediaUsageSlot::acceptsMediaType(CatalogMediaUsageSlot::SOURCE_BADGE, CatalogMediaType::ICON));
    }

    public function testUsageSlotRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CatalogMediaUsageSlot::assertValid('google_photo');
    }
}
