<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CatalogMediaIsolationTest extends TestCase
{
    public function testCatalogMediaDomainDoesNotDependOnClaimOrPublicRuntime(): void
    {
        $files = array_merge(
            glob(__DIR__.'/../../src/Domain/CatalogMedia/*.php') ?: [],
            glob(__DIR__.'/../../src/Entity/Core/CatalogMedia*.php') ?: [],
            glob(__DIR__.'/../../src/Service/CatalogMedia/*.php') ?: [],
            glob(__DIR__.'/../../src/Infrastructure/Storage/*CatalogMediaStorage*.php') ?: [],
        );

        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('ClaimEvidenceStorageInterface', $contents, $file);
            self::assertStringNotContainsString('CloudflareR2ClaimEvidenceStorage', $contents, $file);
            self::assertStringNotContainsString('App\\Controller\\Public', $contents, $file);
            self::assertStringNotContainsString('GooglePlaces', $contents, $file);
            self::assertStringNotContainsString('ReviewMedia', $contents, $file);
        }
    }

    public function testLocationCategoryLegacyFieldsStillExist(): void
    {
        $contents = file_get_contents(__DIR__.'/../../src/Entity/Core/LocationCategory.php');
        self::assertIsString($contents);
        self::assertStringContainsString('private ?string $iconAssetUrl', $contents);
        self::assertStringContainsString('private ?string $defaultPhotoUrl', $contents);
        self::assertStringContainsString('private ?string $coverPhotoUrl', $contents);
    }
}
