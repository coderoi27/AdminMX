<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Storage;

use App\Domain\CatalogMedia\CatalogMediaObjectMetadata;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUploadContext;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Infrastructure\Storage\CloudflareR2CatalogMediaStorage;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class CloudflareR2CatalogMediaStorageTest extends TestCase
{
    public function testCreateUploadIntentUsesUuidAndDoesNotExposeOriginalFilenameInObjectKey(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $command = $this->createStub(CommandInterface::class);
        $client->expects(self::once())
            ->method('getCommand')
            ->with('PutObject', self::callback(static fn (array $args): bool => str_starts_with((string) $args['Key'], 'catalog-media/general/images/')))
            ->willReturn($command);
        $client->expects(self::once())
            ->method('createPresignedRequest')
            ->willReturn(new Request('PUT', 'https://r2.example.test/presigned'));

        $storage = $this->storage($client);
        $intent = $storage->createUploadIntent(
            'rodrigo@example.com menu.jpg',
            'image/jpeg',
            2048,
            new CatalogMediaUploadContext(CatalogMediaType::IMAGE, uuid: '550e8400-e29b-41d4-a716-446655440000')
        );

        self::assertSame('https://r2.example.test/presigned', $intent->uploadUrl);
        self::assertSame(['Content-Type' => 'image/jpeg'], $intent->requiredHeaders);
        self::assertStringContainsString('550e8400-e29b-41d4-a716-446655440000', $intent->objectKey);
        self::assertStringNotContainsString('rodrigo', $intent->objectKey);
        self::assertStringNotContainsString('@', $intent->objectKey);
        self::assertStringEndsWith('/original.jpg', $intent->objectKey);
    }

    public function testCategoryContextUsesSanitizedOrganizationalPath(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $command = $this->createStub(CommandInterface::class);
        $client->expects(self::once())->method('getCommand')->willReturn($command);
        $client->expects(self::once())->method('createPresignedRequest')->willReturn(new Request('PUT', 'https://r2.example.test/presigned'));

        $storage = $this->storage($client);
        $intent = $storage->createUploadIntent(
            'cover.webp',
            'image/webp',
            2048,
            new CatalogMediaUploadContext(CatalogMediaType::IMAGE, CatalogMediaUsageSlot::CATEGORY_COVER, 'taquerias', '550e8400-e29b-41d4-a716-446655440000')
        );

        self::assertSame('categories/taquerias/cover/550e8400-e29b-41d4-a716-446655440000/original.webp', $intent->objectKey);
    }

    public function testInspectObjectUsesHeadObjectAndPublicCdnUrl(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->expects(self::once())
            ->method('__call')
            ->with('headObject', [[
                'Bucket' => 'catalog-assets',
                'Key' => 'catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp',
            ]])
            ->willReturn(new Result([
                'ContentLength' => 2048,
                'ContentType' => 'image/webp',
                'ETag' => '"etag-123"',
            ]));

        $storage = $this->storage($client);
        $object = $storage->inspectObject('catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp');

        self::assertSame('etag-123', $object->metadata->checksum);
        self::assertSame('https://assets.mimonchis.mx/catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp', $object->publicUrl);
        self::assertStringNotContainsString('admin.mimonchis.mx', $object->publicUrl);
        self::assertStringNotContainsString('r2.cloudflarestorage.com', $object->publicUrl);
        self::assertStringNotContainsString('catalog-assets', $object->publicUrl);
    }

    public function testConfirmUploadedObjectValidatesSizeMimeAndEtag(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->expects(self::once())->method('__call')->willReturn(new Result([
            'ContentLength' => 2048,
            'ContentType' => 'image/webp',
            'ETag' => '"etag-123"',
        ]));

        $storage = $this->storage($client);
        $object = $storage->confirmUploadedObject(
            'catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp',
            new CatalogMediaObjectMetadata('catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp', 'image/webp', 2048, 'etag-123')
        );

        self::assertSame('etag-123', $object->metadata->checksum);
    }

    public function testPublicUrlDoesNotExposeR2EndpointOrNumericId(): void
    {
        $storage = $this->storage();
        $url = $storage->publicUrlFor('catalog-media/general/images/2026/07/550e8400-e29b-41d4-a716-446655440000/original.webp');

        self::assertStringStartsWith('https://assets.mimonchis.mx/', $url);
        self::assertStringNotContainsString('admin.mimonchis.mx', $url);
        self::assertStringNotContainsString('r2.cloudflarestorage.com', $url);
        self::assertStringNotContainsString('/123/', $url);
    }

    private function storage(?S3ClientInterface $client = null): CloudflareR2CatalogMediaStorage
    {
        return new CloudflareR2CatalogMediaStorage(
            endpoint: 'https://account.r2.cloudflarestorage.com',
            region: 'auto',
            bucketName: 'catalog-assets',
            accessKeyId: 'catalog-key',
            secretAccessKey: 'catalog-secret',
            publicBaseUrl: 'https://assets.mimonchis.mx',
            s3Client: $client,
        );
    }
}
