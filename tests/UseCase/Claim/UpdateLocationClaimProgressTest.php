<?php

declare(strict_types=1);

namespace App\Tests\UseCase\Claim;

use App\Domain\Claim\ClaimStateMachine;
use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\UpdateLocationClaimProgress;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class UpdateLocationClaimProgressTest extends TestCase
{
    public function testLegalAcceptanceRequiresCompletedEvidence(): void
    {
        $claim = (new LocationClaimRequest())
            ->setClaimUuid('claim-test-uuid')
            ->setStatus(ClaimStateMachine::STATE_PENDING_EVIDENCE)
            ->setLocationName('Tacos Test')
            ->setEmail('owner@example.com')
            ->markEmailVerified(new \DateTimeImmutable('2026-07-12T10:00:00+00:00'));

        $evidenceRepository = $this->createMock(EntityRepository::class);
        $evidenceRepository
            ->expects(self::once())
            ->method('count')
            ->with(['claim' => $claim, 'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED]])
            ->willReturn(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(LocationClaimEvidence::class)
            ->willReturn($evidenceRepository);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('claim_evidence_required');

        (new UpdateLocationClaimProgress($entityManager, new ClaimStateMachine()))->execute($claim, [
            'last_completed_step' => 'legal',
            'legal_acceptance_reference' => 'claim_terms:v1',
        ]);
    }

    public function testLegalAcceptanceStoresServerSideTimestampMetadata(): void
    {
        $claim = (new LocationClaimRequest())
            ->setClaimUuid('claim-test-uuid')
            ->setStatus(ClaimStateMachine::STATE_PENDING_EVIDENCE)
            ->setLocationName('Tacos Test')
            ->setEmail('owner@example.com')
            ->markEmailVerified(new \DateTimeImmutable('2026-07-12T10:00:00+00:00'));

        $evidenceRepository = $this->createMock(EntityRepository::class);
        $evidenceRepository->method('count')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($evidenceRepository);
        $entityManager->expects(self::once())->method('persist')->with($claim);
        $entityManager->expects(self::once())->method('flush');

        (new UpdateLocationClaimProgress($entityManager, new ClaimStateMachine()))->execute($claim, [
            'last_completed_step' => 'legal',
            'legal_acceptance_reference' => 'claim_terms:v1',
        ]);

        self::assertSame('claim_terms:v1', $claim->getLegalAcceptanceReference());
        self::assertSame('claim_terms:v1', $claim->getPrefillPayloadJson()['legal_acceptance']['reference']);
        self::assertArrayHasKey('accepted_at', $claim->getPrefillPayloadJson()['legal_acceptance']);
    }
}
