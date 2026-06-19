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

    public function execute(LocationClaimRequest $claim, int $evidenceId): LocationClaimEvidence
    {
        $repository = $this->em->getRepository(LocationClaimEvidence::class);
        
        // This is safe because claim relationship is eager/lazy but verified here
        $evidence = $repository->findOneBy(['id' => $evidenceId, 'claim' => $claim]);

        if (!$evidence) {
            throw new \DomainException('Evidence not found for this claim.');
        }

        $reflection = new \ReflectionClass($evidence);
        $status = $reflection->getProperty('status')->getValue($evidence);

        if ($status !== LocationClaimEvidence::STATUS_PENDING_UPLOAD) {
            throw new \DomainException('Evidence is not in pending_upload state.');
        }

        // Verify via storage abstraction
        $objectKey = $reflection->getProperty('objectKey')->getValue($evidence);
        $inspectionData = $this->storage->inspectObject($objectKey);

        // Update entity
        $reflection->getProperty('status')->setValue($evidence, LocationClaimEvidence::STATUS_UPLOADED);
        $reflection->getProperty('uploadedAt')->setValue($evidence, new \DateTimeImmutable());
        
        if (isset($inspectionData['checksum_sha256'])) {
            $reflection->getProperty('checksumSha256')->setValue($evidence, $inspectionData['checksum_sha256']);
        }

        $this->em->flush();

        return $evidence;
    }
}
