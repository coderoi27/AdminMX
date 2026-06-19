<?php

declare(strict_types=1);

namespace App\Domain\Claim;

interface ClaimEvidenceStorageInterface
{
    /**
     * Prepares an upload destination for a claim evidence file.
     * Returns a payload containing the object key and upload URL (if applicable).
     */
    public function prepareUpload(string $claimUuid, string $evidenceType, string $mimeType, int $sizeBytes): array;

    /**
     * Inspects a stored evidence object to verify it exists and matches expectations.
     * Returns metadata about the object.
     */
    public function inspectObject(string $objectKey): array;

    /**
     * Creates a temporary, signed read URL for a stored evidence object.
     */
    public function createReadUrl(string $objectKey, int $expiresInSeconds = 3600): string;

    /**
     * Deletes a stored evidence object.
     */
    public function deleteObject(string $objectKey): void;
}
