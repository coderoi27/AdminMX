<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

interface CatalogMediaStorageInterface
{
    public function createUploadIntent(string $originalFilename, string $mimeType, int $sizeBytes, ?CatalogMediaUploadContext $context = null): CatalogMediaUploadIntent;

    public function confirmUploadedObject(string $objectKey, ?CatalogMediaObjectMetadata $expectedMetadata = null): CatalogMediaUploadedObject;

    public function inspectObject(string $objectKey): CatalogMediaUploadedObject;

    public function publicUrlFor(string $objectKey): string;

    public function deleteOrArchiveObject(string $objectKey): void;

    public function normalizeObjectKey(string $objectKey): string;

    public function validateObjectMetadata(CatalogMediaObjectMetadata $metadata): void;
}
