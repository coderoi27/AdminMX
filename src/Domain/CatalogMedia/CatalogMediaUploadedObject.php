<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final readonly class CatalogMediaUploadedObject
{
    public function __construct(
        public string $objectKey,
        public string $publicUrl,
        public CatalogMediaObjectMetadata $metadata,
    ) {
    }
}
