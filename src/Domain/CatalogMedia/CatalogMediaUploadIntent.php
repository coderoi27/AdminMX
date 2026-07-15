<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final readonly class CatalogMediaUploadIntent
{
    /**
     * @param array<string, string> $requiredHeaders
     */
    public function __construct(
        public string $uploadUrl,
        public string $objectKey,
        public string $storageProvider,
        public string $logicalBucket,
        public \DateTimeImmutable $expiresAt,
        public array $requiredHeaders = [],
    ) {
    }
}
