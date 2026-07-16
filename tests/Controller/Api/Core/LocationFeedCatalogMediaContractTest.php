<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Core;

use PHPUnit\Framework\TestCase;

final class LocationFeedCatalogMediaContractTest extends TestCase
{
    public function testFeedControllerExposesResolvedMediaWhileKeepingLegacyFields(): void
    {
        $contents = file_get_contents(__DIR__.'/../../../../src/Controller/Api/Core/LocationFeedController.php');
        self::assertIsString($contents);

        self::assertStringContainsString("'resolved_media' => \$resolvedMedia", $contents);
        self::assertStringContainsString("'photo_url' => \$photoUrl ?? \$resolvedMedia['cover']['url']", $contents);
        self::assertStringContainsString("'media_items' => \$this->mediaItemsPayload(\$location)", $contents);
        self::assertStringContainsString("'category_icon_asset_url'", $contents);
        self::assertStringContainsString("'category_default_photo_url'", $contents);
        self::assertStringContainsString("'category_cover_photo_url'", $contents);
        self::assertStringContainsString("'resolved_media' => 'alpha-v1'", $contents);
    }

    public function testFeedControllerDoesNotExposeCatalogStorageInternals(): void
    {
        $contents = file_get_contents(__DIR__.'/../../../../src/Controller/Api/Core/LocationFeedController.php');
        self::assertIsString($contents);

        self::assertStringNotContainsString('object_key', $contents);
        self::assertStringNotContainsString('logical_bucket', $contents);
        self::assertStringNotContainsString('getObjectKey()', $contents);
        self::assertStringNotContainsString('getLogicalBucket()', $contents);
        self::assertStringContainsString('catalogAssignmentsByCategory', $contents);
    }
}
