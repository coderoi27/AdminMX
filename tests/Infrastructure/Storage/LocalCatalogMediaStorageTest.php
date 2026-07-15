<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Storage;

use App\Domain\CatalogMedia\CatalogMediaObjectMetadata;
use App\Domain\CatalogMedia\CatalogMediaStorageInterface;
use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Infrastructure\Storage\LocalCatalogMediaStorage;
use PHPUnit\Framework\TestCase;

final class LocalCatalogMediaStorageTest extends TestCase
{
    public function testStorageInterfaceIsNotClaimEvidenceStorage(): void
    {
        $storage = new LocalCatalogMediaStorage('https://assets.mimonchis.mx');

        self::assertInstanceOf(CatalogMediaStorageInterface::class, $storage);
        self::assertNotInstanceOf(ClaimEvidenceStorageInterface::class, $storage);
    }

    public function testUploadIntentUsesCatalogMediaPrefixAndDoesNotExposeOriginalFilename(): void
    {
        $storage = new LocalCatalogMediaStorage('https://assets.mimonchis.mx');
        $intent = $storage->createUploadIntent('rodrigo@example.com menu.jpg', 'image/jpeg', 2048);

        self::assertStringStartsWith('catalog-media/general/images/', $intent->objectKey);
        self::assertStringNotContainsString('rodrigo', $intent->objectKey);
        self::assertStringNotContainsString('@', $intent->objectKey);
        self::assertStringEndsWith('/original.jpg', $intent->objectKey);
        self::assertMatchesRegularExpression('#/[0-9a-f-]{36}/original\.jpg$#', $intent->objectKey);
    }

    public function testPublicUrlUsesConfiguredPublicBaseUrl(): void
    {
        $storage = new LocalCatalogMediaStorage('https://assets.mimonchis.mx');

        self::assertSame(
            'https://assets.mimonchis.mx/catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp',
            $storage->publicUrlFor('catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp'),
        );
    }

    public function testRejectsUnsafeObjectKeys(): void
    {
        $storage = new LocalCatalogMediaStorage();

        $this->expectException(\InvalidArgumentException::class);

        $storage->publicUrlFor('claims/private/evidence.jpg');
    }

    public function testMetadataValidationRejectsOversizedImages(): void
    {
        $storage = new LocalCatalogMediaStorage(maxImageBytes: 10);

        $this->expectException(\InvalidArgumentException::class);

        $storage->validateObjectMetadata(new CatalogMediaObjectMetadata(
            'catalog-media/originals/2026/07/id/original.jpg',
            'image/jpeg',
            11,
        ));
    }

    public function testLocalStorageNormalizesParametrizedMimeType(): void
    {
        $storage = new LocalCatalogMediaStorage('https://assets.mimonchis.mx');
        $intent = $storage->createUploadIntent('clip.mov', 'video/mp4;codecs=h264', 2048);

        self::assertSame(['Content-Type' => 'video/mp4'], $intent->requiredHeaders);
        self::assertStringContainsString('/videos/', $intent->objectKey);
        self::assertStringEndsWith('/original.mp4', $intent->objectKey);
    }
}
