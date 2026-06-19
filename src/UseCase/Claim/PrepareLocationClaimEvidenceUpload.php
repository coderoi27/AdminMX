<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimEvidence;
use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;

final class PrepareLocationClaimEvidenceUpload
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

    public function execute(LocationClaimRequest $claim, string $evidenceType, string $mimeType, int $sizeBytes, string $originalFilename): array
    {
        if (!in_array($claim->getStatus(), [ClaimStateMachine::STATE_PENDING_EVIDENCE, ClaimStateMachine::STATE_NEEDS_INFO], true)) {
            throw new \DomainException('Cannot prepare upload in current state.');
        }

        // 1. Prepare upload in storage
        $uploadData = $this->storage->prepareUpload($claim->getClaimUuid() ?? (string)$claim->getId(), $evidenceType, $mimeType, $sizeBytes);

        // 2. Create pending evidence entity
        $evidence = new LocationClaimEvidence();
        
        $reflection = new \ReflectionClass($evidence);
        $reflection->getProperty('claim')->setValue($evidence, $claim);
        $reflection->getProperty('evidenceType')->setValue($evidence, $evidenceType);
        $reflection->getProperty('storageProvider')->setValue($evidence, $uploadData['storage_provider']);
        $reflection->getProperty('bucketName')->setValue($evidence, $uploadData['bucket_name']);
        $reflection->getProperty('objectKey')->setValue($evidence, $uploadData['object_key']);
        $reflection->getProperty('originalFilename')->setValue($evidence, $originalFilename);
        $reflection->getProperty('mimeType')->setValue($evidence, $mimeType);
        $reflection->getProperty('sizeBytes')->setValue($evidence, $sizeBytes);
        $reflection->getProperty('status')->setValue($evidence, LocationClaimEvidence::STATUS_PENDING_UPLOAD);

        $this->em->persist($evidence);
        $this->em->flush();

        return [
            'evidence_id' => $evidence->getId(),
            'upload_url' => $uploadData['upload_url'],
            'object_key' => $uploadData['object_key'],
            'required_headers' => [
                'Content-Type' => $mimeType,
            ],
        ];
    }
}
