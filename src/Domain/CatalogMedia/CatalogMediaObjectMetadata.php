<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final readonly class CatalogMediaObjectMetadata
{
    public function __construct(
        public string $objectKey,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $checksum = null,
    ) {
        if ($sizeBytes < 0) {
            throw new \InvalidArgumentException('Catalog media object size cannot be negative.');
        }
    }
}
