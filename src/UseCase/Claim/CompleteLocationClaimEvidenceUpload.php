<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimEvidence;
use App\Domain\Claim\ClaimEvidenceStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CompleteLocationClaimEvidenceUpload
{
    private EntityManagerInterface $em;
    private ClaimEvidenceStorageInterface $storage;

    public function __construct(
        EntityManagerInterface $em,
        ClaimEvidenceStorageInterface $storage
    ) {
        $this->em = $em;
        $this->storage = $storage;
    }

    public function execute(LocationClaimRequest $claim, int $evidenceId, ?string $etag): LocationClaimEvidence
    {
        $expectedEtag = $this->normalizeEtag($etag);
        if ($expectedEtag === null) {
            throw new \DomainException('ETag is required to complete evidence upload.');
        }

        $repository = $this->em->getRepository(LocationClaimEvidence::class);
        
        // This is safe because claim relationship is eager/lazy but verified here
        $evidence = $repository->findOneBy(['id' => $evidenceId, 'claim' => $claim]);

        if (!$evidence) {
            throw new \DomainException('Evidence not found for this claim.');
        }

        $reflection = new \ReflectionClass($evidence);
        $status = $reflection->getProperty('status')->getValue($evidence);

        if (in_array($status, [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED], true)) {
            $storedEtag = $this->normalizeEtag($evidence->getChecksumSha256());
            if ($storedEtag !== null && hash_equals($storedEtag, $expectedEtag)) {
                return $evidence;
            }

            throw new \DomainException('Evidence ETag does not match previous upload.');
        }

        if ($status !== LocationClaimEvidence::STATUS_PENDING_UPLOAD) {
            throw new \DomainException('Evidence is not in pending_upload state.');
        }

        // Verify via storage abstraction
        $objectKey = $reflection->getProperty('objectKey')->getValue($evidence);
        $inspectionData = $this->storage->inspectObject($objectKey);
        $this->assertMetadataMatches($evidence, $inspectionData, $expectedEtag);

        // Update entity
        $reflection->getProperty('status')->setValue($evidence, LocationClaimEvidence::STATUS_UPLOADED);
        $reflection->getProperty('uploadedAt')->setValue($evidence, new \DateTimeImmutable());
        
        $reflection->getProperty('checksumSha256')->setValue($evidence, $expectedEtag);

        $this->em->flush();

        return $evidence;
    }

    private function normalizeEtag(?string $etag): ?string
    {
        if ($etag === null) {
            return null;
        }

        $etag = trim($etag);
        $etag = trim($etag, '"');

        return $etag !== '' ? $etag : null;
    }

    /**
     * @param array<string, mixed> $inspectionData
     */
    private function assertMetadataMatches(LocationClaimEvidence $evidence, array $inspectionData, string $expectedEtag): void
    {
        if ((int) ($inspectionData['size_bytes'] ?? -1) !== $evidence->getSizeBytes()) {
            throw new \DomainException('Evidence size does not match uploaded object.');
        }

        if ((string) ($inspectionData['mime_type'] ?? '') !== $evidence->getMimeType()) {
            throw new \DomainException('Evidence MIME type does not match uploaded object.');
        }

        $actualEtag = $this->normalizeEtag(is_string($inspectionData['checksum_sha256'] ?? null) ? $inspectionData['checksum_sha256'] : null);
        if ($actualEtag === null || !hash_equals($actualEtag, $expectedEtag)) {
            throw new \DomainException('Evidence ETag does not match uploaded object.');
        }
    }
}
