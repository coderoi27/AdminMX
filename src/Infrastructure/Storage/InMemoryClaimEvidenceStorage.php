<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Claim\ClaimEvidenceStorageInterface;

final class InMemoryClaimEvidenceStorage implements ClaimEvidenceStorageInterface
{
    private array $storage = [];

    public function prepareUpload(string $claimUuid, string $evidenceType, string $mimeType, int $sizeBytes): array
    {
        $objectId = bin2hex(random_bytes(16));
        $objectKey = sprintf('claims/%s/%s_%s', $claimUuid, $evidenceType, $objectId);

        // Pre-register for testing
        $this->storage[$objectKey] = [
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'status' => 'pending',
        ];

        return [
            'upload_url' => 'https://fake-storage.local/upload/' . $objectKey,
            'object_key' => $objectKey,
            'storage_provider' => 'in_memory',
            'bucket_name' => 'fake_bucket',
        ];
    }

    public function inspectObject(string $objectKey): array
    {
        if (!isset($this->storage[$objectKey])) {
            throw new \RuntimeException('Object not found in fake storage');
        }

        // Simulate that the object has been successfully uploaded for testing
        $this->storage[$objectKey]['status'] = 'uploaded';

        return [
            'size_bytes' => $this->storage[$objectKey]['size_bytes'],
            'mime_type' => $this->storage[$objectKey]['mime_type'],
            'checksum_sha256' => hash('sha256', 'fake_content'),
        ];
    }

    public function createReadUrl(string $objectKey, int $expiresInSeconds = 3600): string
    {
        return 'https://fake-storage.local/read/' . $objectKey . '?expires=' . (time() + $expiresInSeconds);
    }

    public function deleteObject(string $objectKey): void
    {
        unset($this->storage[$objectKey]);
    }

    // Test helper
    public function simulateUpload(string $objectKey): void
    {
        if (isset($this->storage[$objectKey])) {
            $this->storage[$objectKey]['status'] = 'uploaded';
        }
    }
}
