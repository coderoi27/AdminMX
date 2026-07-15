<?php

declare(strict_types=1);

namespace App\Tests\Entity\Core;

use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\CatalogMediaCategoryAssignment;
use App\Entity\Core\CatalogMediaUsagePool;
use PHPUnit\Framework\TestCase;

final class CatalogMediaCategoryAssignmentTest extends TestCase
{
    public function testAssignmentRequiresPoolOrAsset(): void
    {
        $assignment = new CatalogMediaCategoryAssignment();

        $this->expectException(\InvalidArgumentException::class);

        $assignment->assertValid();
    }

    public function testAssignmentRejectsIncompatibleAssetForSlot(): void
    {
        $asset = (new CatalogMediaAsset())->setMediaType(CatalogMediaType::VIDEO);
        $assignment = (new CatalogMediaCategoryAssignment())
            ->setUsageSlot(CatalogMediaUsageSlot::MAP_CARD)
            ->setAsset($asset);

        $this->expectException(\InvalidArgumentException::class);

        $assignment->assertValid();
    }

    public function testAssignmentRejectsMismatchedPoolSlot(): void
    {
        $pool = (new CatalogMediaUsagePool())->setUsageSlot(CatalogMediaUsageSlot::STORY_VIDEO);
        $assignment = (new CatalogMediaCategoryAssignment())
            ->setUsageSlot(CatalogMediaUsageSlot::STORY_IMAGE)
            ->setPool($pool);

        $this->expectException(\InvalidArgumentException::class);

        $assignment->assertValid();
    }

    public function testAssignmentRejectsNegativeWeightAndPriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CatalogMediaCategoryAssignment())->setWeight(-1);
    }

    public function testAssignmentRejectsInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogMediaCategoryAssignment())->setValidity(
            new \DateTimeImmutable('2026-07-08'),
            new \DateTimeImmutable('2026-07-07'),
        );
    }
}
