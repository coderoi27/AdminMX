<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final readonly class CatalogMediaUploadContext
{
    public function __construct(
        public string $mediaType,
        public ?string $usageSlot = null,
        public ?string $categorySlug = null,
        public ?string $uuid = null,
    ) {
        CatalogMediaType::assertValid($mediaType);

        if ($usageSlot !== null) {
            CatalogMediaUsageSlot::assertAcceptsMediaType($usageSlot, $mediaType);
        }
    }
}
