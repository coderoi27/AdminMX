<?php

declare(strict_types=1);

namespace App\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaUsageSlot;

final readonly class CatalogMediaResolutionRequest
{
    public function __construct(
        public string $sourceType,
        public string $locationIdentity,
        public string $categoryIdentity,
        public string $usageSlot,
        public int $poolVersion,
        public int $requestedCount,
    ) {
        CatalogMediaUsageSlot::assertValid($usageSlot);
        if ($poolVersion < 0) {
            throw new \InvalidArgumentException('Catalog media pool version cannot be negative.');
        }
        if ($requestedCount < 1) {
            throw new \InvalidArgumentException('Catalog media requested count must be positive.');
        }
    }
}
