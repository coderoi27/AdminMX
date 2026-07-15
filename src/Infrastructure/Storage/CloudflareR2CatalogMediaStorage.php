<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\CatalogMedia\CatalogMediaObjectMetadata;
use App\Domain\CatalogMedia\CatalogMediaStorageInterface;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUploadContext;
use App\Domain\CatalogMedia\CatalogMediaUploadedObject;
use App\Domain\CatalogMedia\CatalogMediaUploadIntent;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Symfony\Component\Uid\Uuid;

final class CloudflareR2CatalogMediaStorage implements CatalogMediaStorageInterface
{
    private const STORAGE_PROVIDER = 'cloudflare_r2_catalog_media';
    private const LOGICAL_BUCKET = 'catalog_media_public';
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    private ?S3ClientInterface $s3Client = null;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucketName,
        private readonly string $accessKeyId,
        private readonly string $secretAccessKey,
        private readonly string $publicBaseUrl,
        private readonly int $uploadUrlTtl = 900,
        private readonly int $maxImageBytes = 5242880,
        private readonly int $maxVideoBytes = 52428800,
        ?S3ClientInterface $s3Client = null,
    ) {
        $this->s3Client = $s3Client;
    }

    public function assertConfigured(): void
    {
        $missing = [];
        foreach ([
            'CATALOG_MEDIA_R2_ENDPOINT' => $this->endpoint,
            'CATALOG_MEDIA_R2_REGION' => $this->region,
            'CATALOG_MEDIA_R2_BUCKET' => $this->bucketName,
            'CATALOG_MEDIA_R2_ACCESS_KEY_ID' => $this->accessKeyId,
            'CATALOG_MEDIA_R2_SECRET_ACCESS_KEY' => $this->secretAccessKey,
            'CATALOG_MEDIA_PUBLIC_BASE_URL' => $this->publicBaseUrl,
        ] as $name => $value) {
            if (trim($value) === '') {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(sprintf('Catalog media R2 storage is missing configuration: %s.', implode(', ', $missing)));
        }
    }

    public function createUploadIntent(string $originalFilename, string $mimeType, int $sizeBytes, ?CatalogMediaUploadContext $context = null): CatalogMediaUploadIntent
    {
        $this->assertConfigured();
        $mimeType = $this->normalizeMimeType($mimeType);
        $extension = self::MIME_TO_EXT[$mimeType] ?? null;
        if ($extension === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media MIME type "%s".', $mimeType));
        }

        $mediaType = $context?->mediaType ?? $this->mediaTypeForMimeType($mimeType);
        $uuid = $context?->uuid ?? Uuid::v4()->toRfc4122();
        $objectKey = $this->objectKeyFor($uuid, $mediaType, $extension, $context);
        $this->validateObjectMetadata(new CatalogMediaObjectMetadata($objectKey, $mimeType, $sizeBytes));

        $command = $this->client()->getCommand('PutObject', [
            'Bucket' => $this->bucketName,
            'Key' => $objectKey,
            'ContentType' => $mimeType,
        ]);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $this->uploadUrlTtl));
        $request = $this->client()->createPresignedRequest($command, sprintf('+%d seconds', $this->uploadUrlTtl));

        return new CatalogMediaUploadIntent(
            uploadUrl: (string) $request->getUri(),
            objectKey: $objectKey,
            storageProvider: self::STORAGE_PROVIDER,
            logicalBucket: self::LOGICAL_BUCKET,
            expiresAt: $expiresAt,
            requiredHeaders: ['Content-Type' => $mimeType],
        );
    }

    public function confirmUploadedObject(string $objectKey, ?CatalogMediaObjectMetadata $expectedMetadata = null): CatalogMediaUploadedObject
    {
        $uploadedObject = $this->inspectObject($objectKey);
        if ($expectedMetadata === null) {
            return $uploadedObject;
        }

        $this->validateObjectMetadata($expectedMetadata);
        if ($uploadedObject->metadata->sizeBytes !== $expectedMetadata->sizeBytes) {
            throw new \RuntimeException('Catalog media uploaded object size does not match the prepared metadata.');
        }

        if ($this->normalizeMimeType($uploadedObject->metadata->mimeType) !== $this->normalizeMimeType($expectedMetadata->mimeType)) {
            throw new \RuntimeException('Catalog media uploaded object MIME type does not match the prepared metadata.');
        }

        if ($expectedMetadata->checksum !== null && $uploadedObject->metadata->checksum !== null && $expectedMetadata->checksum !== $uploadedObject->metadata->checksum) {
            throw new \RuntimeException('Catalog media uploaded object ETag does not match.');
        }

        return $uploadedObject;
    }

    public function inspectObject(string $objectKey): CatalogMediaUploadedObject
    {
        $this->assertConfigured();
        $objectKey = $this->normalizeObjectKey($objectKey);
        $result = $this->client()->headObject([
            'Bucket' => $this->bucketName,
            'Key' => $objectKey,
        ]);

        $metadata = new CatalogMediaObjectMetadata(
            objectKey: $objectKey,
            mimeType: $this->normalizeMimeType((string) ($result['ContentType'] ?? 'application/octet-stream')),
            sizeBytes: (int) ($result['ContentLength'] ?? 0),
            checksum: $this->normalizeEtag((string) ($result['ETag'] ?? '')),
        );
        $this->validateObjectMetadata($metadata);

        return new CatalogMediaUploadedObject($objectKey, $this->publicUrlFor($objectKey), $metadata);
    }

    public function publicUrlFor(string $objectKey): string
    {
        return rtrim($this->publicBaseUrl, '/').'/'.$this->normalizeObjectKey($objectKey);
    }

    public function deleteOrArchiveObject(string $objectKey): void
    {
        $this->assertConfigured();
        $this->client()->deleteObject([
            'Bucket' => $this->bucketName,
            'Key' => $this->normalizeObjectKey($objectKey),
        ]);
    }

    public function normalizeObjectKey(string $objectKey): string
    {
        $objectKey = trim(rawurldecode($objectKey));
        if ($objectKey === '' || str_starts_with($objectKey, '/') || str_contains($objectKey, "\0") || str_contains($objectKey, '..')) {
            throw new \InvalidArgumentException('Catalog media object key is not safe.');
        }

        foreach (['catalog-media/', 'categories/', 'branding/', 'source-badges/', 'placeholders/'] as $prefix) {
            if (str_starts_with($objectKey, $prefix)) {
                return $objectKey;
            }
        }

        throw new \InvalidArgumentException('Catalog media object key must use an allowed public catalog prefix.');
    }

    public function validateObjectMetadata(CatalogMediaObjectMetadata $metadata): void
    {
        $this->normalizeObjectKey($metadata->objectKey);
        $mimeType = $this->normalizeMimeType($metadata->mimeType);
        $isVideo = str_starts_with($mimeType, 'video/');
        $isImage = str_starts_with($mimeType, 'image/');
        if (!$isVideo && !$isImage && $mimeType !== 'application/octet-stream') {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media MIME type "%s".', $mimeType));
        }

        $maxBytes = $isVideo ? $this->maxVideoBytes : $this->maxImageBytes;
        if ($metadata->sizeBytes > $maxBytes) {
            throw new \InvalidArgumentException('Catalog media object exceeds the configured maximum size.');
        }
    }

    private function client(): S3ClientInterface
    {
        $this->assertConfigured();
        if ($this->s3Client === null) {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'endpoint' => $this->endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $this->accessKeyId,
                    'secret' => $this->secretAccessKey,
                ],
            ]);
        }

        return $this->s3Client;
    }

    private function objectKeyFor(string $uuid, string $mediaType, string $extension, ?CatalogMediaUploadContext $context): string
    {
        $uuid = Uuid::fromString($uuid)->toRfc4122();
        if ($context?->categorySlug !== null && $context->usageSlot !== null) {
            return sprintf(
                'categories/%s/%s/%s/original.%s',
                $this->safeSlug($context->categorySlug),
                $this->slotPath($context->usageSlot),
                $uuid,
                $extension
            );
        }

        $now = new \DateTimeImmutable();

        return sprintf(
            'catalog-media/general/%s/%s/%s/%s/original.%s',
            $this->mediaTypePath($mediaType),
            $now->format('Y'),
            $now->format('m'),
            $uuid,
            $extension
        );
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return mb_strtolower(trim(strtok($mimeType, ';') ?: $mimeType));
    }

    private function normalizeEtag(string $etag): ?string
    {
        $etag = trim($etag, " \t\n\r\0\x0B\"");

        return $etag !== '' ? $etag : null;
    }

    private function mediaTypeForMimeType(string $mimeType): string
    {
        return str_starts_with($mimeType, 'video/') ? CatalogMediaType::VIDEO : CatalogMediaType::IMAGE;
    }

    private function mediaTypePath(string $mediaType): string
    {
        return match ($mediaType) {
            CatalogMediaType::VIDEO => 'videos',
            CatalogMediaType::ICON => 'icons',
            default => 'images',
        };
    }

    private function slotPath(string $usageSlot): string
    {
        return match ($usageSlot) {
            CatalogMediaUsageSlot::CATEGORY_ICON => 'icon',
            CatalogMediaUsageSlot::CATEGORY_DEFAULT => 'default',
            CatalogMediaUsageSlot::CATEGORY_COVER => 'cover',
            CatalogMediaUsageSlot::LOCATION_COVER => 'location-cover',
            CatalogMediaUsageSlot::LOCATION_GALLERY => 'gallery',
            CatalogMediaUsageSlot::STORY_IMAGE => 'stories/images',
            CatalogMediaUsageSlot::STORY_VIDEO => 'stories/videos',
            default => $this->safeSlug($usageSlot),
        };
    }

    private function safeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-_');
        if ($slug === '') {
            throw new \InvalidArgumentException('Catalog media slug is not safe.');
        }

        return $slug;
    }
}
