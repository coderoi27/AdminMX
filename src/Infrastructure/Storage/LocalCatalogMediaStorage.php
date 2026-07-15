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
use Symfony\Component\Uid\Uuid;

final class LocalCatalogMediaStorage implements CatalogMediaStorageInterface
{
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    public function __construct(
        private readonly string $publicBaseUrl = '/media',
        private readonly int $uploadUrlTtl = 900,
        private readonly int $maxImageBytes = 5242880,
        private readonly int $maxVideoBytes = 52428800,
    ) {
    }

    public function createUploadIntent(string $originalFilename, string $mimeType, int $sizeBytes, ?CatalogMediaUploadContext $context = null): CatalogMediaUploadIntent
    {
        $mimeType = $this->normalizeMimeType($mimeType);
        $extension = self::MIME_TO_EXT[$mimeType] ?? null;
        if ($extension === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media MIME type "%s".', $mimeType));
        }

        $mediaType = $context?->mediaType ?? $this->mediaTypeForMimeType($mimeType);
        $uuid = $context?->uuid ?? Uuid::v4()->toRfc4122();
        $objectKey = $this->objectKeyFor($uuid, $mediaType, $extension, $context);

        $this->validateObjectMetadata(new CatalogMediaObjectMetadata($objectKey, $mimeType, $sizeBytes));

        $now = new \DateTimeImmutable();

        return new CatalogMediaUploadIntent(
            uploadUrl: $this->publicUrlFor($objectKey),
            objectKey: $objectKey,
            storageProvider: 'local_catalog_media',
            logicalBucket: 'catalog_media_public',
            expiresAt: $now->modify(sprintf('+%d seconds', $this->uploadUrlTtl)),
            requiredHeaders: ['Content-Type' => $mimeType],
        );
    }

    public function confirmUploadedObject(string $objectKey, ?CatalogMediaObjectMetadata $expectedMetadata = null): CatalogMediaUploadedObject
    {
        $objectKey = $this->normalizeObjectKey($objectKey);
        $metadata = $expectedMetadata ?? new CatalogMediaObjectMetadata($objectKey, 'application/octet-stream', 0);
        $this->validateObjectMetadata($metadata);

        return new CatalogMediaUploadedObject($objectKey, $this->publicUrlFor($objectKey), $metadata);
    }

    public function inspectObject(string $objectKey): CatalogMediaUploadedObject
    {
        return $this->confirmUploadedObject($objectKey);
    }

    public function publicUrlFor(string $objectKey): string
    {
        return rtrim($this->publicBaseUrl, '/').'/'.$this->normalizeObjectKey($objectKey);
    }

    public function deleteOrArchiveObject(string $objectKey): void
    {
        $this->normalizeObjectKey($objectKey);
    }

    public function normalizeObjectKey(string $objectKey): string
    {
        $objectKey = trim(rawurldecode($objectKey));
        if ($objectKey === '' || str_starts_with($objectKey, '/') || str_contains($objectKey, "\0") || str_contains($objectKey, '..')) {
            throw new \InvalidArgumentException('Catalog media object key is not safe.');
        }

        $allowedPrefixes = ['catalog-media/', 'categories/', 'branding/', 'source-badges/', 'placeholders/'];
        foreach ($allowedPrefixes as $prefix) {
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

    private function objectKeyFor(string $uuid, string $mediaType, string $extension, ?CatalogMediaUploadContext $context): string
    {
        $uuid = Uuid::fromString($uuid)->toRfc4122();

        if ($context?->categorySlug !== null && $context->usageSlot !== null) {
            $categorySlug = $this->safeSlug($context->categorySlug);
            $slotPath = $this->slotPath($context->usageSlot);

            return sprintf('categories/%s/%s/%s/original.%s', $categorySlug, $slotPath, $uuid, $extension);
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

    private function mediaTypeForMimeType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return CatalogMediaType::VIDEO;
        }

        return CatalogMediaType::IMAGE;
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
