<?php

declare(strict_types=1);

namespace App\Tests\UseCase\Claim;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\CompleteLocationClaimEvidenceUpload;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class CompleteLocationClaimEvidenceUploadTest extends TestCase
{
    public function testCompleteRequiresEtag(): void
    {
        $claim = $this->claim();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');
        $storage = $this->createMock(ClaimEvidenceStorageInterface::class);
        $storage->expects($this->never())->method('inspectObject');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ETag is required');

        (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, '');
    }

    public function testCompleteRejectsMismatchedEtag(): void
    {
        $claim = $this->claim();
        $evidence = $this->evidence($claim);
        $entityManager = $this->entityManager($claim, $evidence);
        $storage = $this->storage([
            'size_bytes' => 2048,
            'mime_type' => 'video/webm',
            'checksum_sha256' => 'actual-etag',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ETag');

        (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, '"other-etag"');
    }

    public function testCompleteRejectsMismatchedSize(): void
    {
        $claim = $this->claim();
        $evidence = $this->evidence($claim);
        $entityManager = $this->entityManager($claim, $evidence);
        $storage = $this->storage([
            'size_bytes' => 999,
            'mime_type' => 'video/webm',
            'checksum_sha256' => 'actual-etag',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('size');

        (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, 'actual-etag');
    }

    public function testCompleteMarksEvidenceUploadedAfterHeadObjectValidation(): void
    {
        $claim = $this->claim();
        $evidence = $this->evidence($claim);
        $entityManager = $this->entityManager($claim, $evidence);
        $entityManager->expects($this->once())->method('flush');
        $storage = $this->storage([
            'size_bytes' => 2048,
            'mime_type' => 'video/webm',
            'checksum_sha256' => 'actual-etag',
        ]);

        $result = (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, '"actual-etag"');

        self::assertSame($evidence, $result);
        self::assertSame(LocationClaimEvidence::STATUS_UPLOADED, $evidence->getStatus());
        self::assertNotNull($evidence->getUploadedAt());
        self::assertSame('actual-etag', $evidence->getChecksumSha256());
    }

    public function testCompleteIsIdempotentWithSameEtag(): void
    {
        $claim = $this->claim();
        $evidence = $this->evidence($claim)
            ->setStatus(LocationClaimEvidence::STATUS_UPLOADED)
            ->setChecksumSha256('actual-etag');
        $entityManager = $this->entityManager($claim, $evidence);
        $entityManager->expects($this->never())->method('flush');
        $storage = $this->createMock(ClaimEvidenceStorageInterface::class);
        $storage->expects($this->never())->method('inspectObject');

        $result = (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, '"actual-etag"');

        self::assertSame($evidence, $result);
        self::assertSame(LocationClaimEvidence::STATUS_UPLOADED, $evidence->getStatus());
    }

    public function testCompleteRejectsIdempotentRetryWithDifferentEtag(): void
    {
        $claim = $this->claim();
        $evidence = $this->evidence($claim)
            ->setStatus(LocationClaimEvidence::STATUS_UPLOADED)
            ->setChecksumSha256('actual-etag');
        $entityManager = $this->entityManager($claim, $evidence);
        $storage = $this->createMock(ClaimEvidenceStorageInterface::class);
        $storage->expects($this->never())->method('inspectObject');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ETag');

        (new CompleteLocationClaimEvidenceUpload($entityManager, $storage))->execute($claim, 10, 'other-etag');
    }

    private function claim(): LocationClaimRequest
    {
        return (new LocationClaimRequest())->setClaimUuid('claim-test-uuid');
    }

    private function evidence(LocationClaimRequest $claim): LocationClaimEvidence
    {
        return (new LocationClaimEvidence())
            ->setClaim($claim)
            ->setObjectKey('claims/claim-test-uuid/evidence/original.webm')
            ->setMimeType('video/webm')
            ->setSizeBytes(2048)
            ->setStatus(LocationClaimEvidence::STATUS_PENDING_UPLOAD);
    }

    private function entityManager(LocationClaimRequest $claim, LocationClaimEvidence $evidence): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 10, 'claim' => $claim])
            ->willReturn($evidence);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(LocationClaimEvidence::class)
            ->willReturn($repository);

        return $entityManager;
    }

    /**
     * @param array<string, mixed> $inspectionData
     */
    private function storage(array $inspectionData): ClaimEvidenceStorageInterface
    {
        $storage = $this->createMock(ClaimEvidenceStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('inspectObject')
            ->with('claims/claim-test-uuid/evidence/original.webm')
            ->willReturn($inspectionData);

        return $storage;
    }
}
