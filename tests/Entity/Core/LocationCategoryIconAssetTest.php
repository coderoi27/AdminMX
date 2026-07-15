<?php

declare(strict_types=1);

namespace App\Tests\Entity\Core;

use App\Entity\Core\LocationCategory;
use PHPUnit\Framework\TestCase;

final class LocationCategoryIconAssetTest extends TestCase
{
    public function testIconAssetUrlDoesNotReplaceSemanticIconKey(): void
    {
        $category = (new LocationCategory())
            ->setName('Tacos')
            ->setSlug('tacos')
            ->setIconKey('taco')
            ->setIconAssetUrl('/media/categories/tacos/icon/icon.png');

        self::assertSame('taco', $category->getIconKey());
        self::assertSame('/media/categories/tacos/icon/icon.png', $category->getIconAssetUrl());

        $category->setIconAssetUrl(null);

        self::assertSame('taco', $category->getIconKey());
        self::assertNull($category->getIconAssetUrl());
    }
}
