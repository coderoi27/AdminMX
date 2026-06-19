<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimEvidence;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;

final class SubmitLocationClaim
{
    private EntityManagerInterface $em;
    private ClaimStateMachine $stateMachine;
    private \App\Domain\Claim\ClaimAccessSessionManager $sessionManager;

    public function __construct(
        EntityManagerInterface $em,
        ClaimStateMachine $stateMachine,
        \App\Domain\Claim\ClaimAccessSessionManager $sessionManager
    ) {
        $this->em = $em;
        $this->stateMachine = $stateMachine;
        $this->sessionManager = $sessionManager;
    }

    public function execute(LocationClaimRequest $claim): void
    {
        if (!$this->stateMachine->canTransitionTo($claim->getStatus(), ClaimStateMachine::STATE_SUBMITTED)) {
            throw new \DomainException('Cannot submit claim in current state.');
        }

        $reflection = new \ReflectionClass($claim);
        
        // Validation: Must have email verified
        $emailVerifiedAt = $reflection->getProperty('emailVerifiedAt')->getValue($claim);
        if ($emailVerifiedAt === null) {
            throw new \DomainException('Email must be verified before submitting.');
        }

        // Validation: Must have legal acceptance
        $legalAcceptance = $reflection->getProperty('legalAcceptanceReference')->getValue($claim);
        if (empty($legalAcceptance)) {
            throw new \DomainException('Legal acceptance is required before submitting.');
        }

        // Validation: Must have at least one uploaded evidence
        $evidenceRepo = $this->em->getRepository(LocationClaimEvidence::class);
        $evidences = $evidenceRepo->findBy(['claim' => $claim, 'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED]]);
        
        if (count($evidences) === 0) {
            throw new \DomainException('At least one evidence file must be uploaded.');
        }

        // Update status
        $reflection->getProperty('status')->setValue($claim, ClaimStateMachine::STATE_SUBMITTED);
        $reflection->getProperty('submittedAt')->setValue($claim, new \DateTimeImmutable());

        // Revoke active sessions
        $this->sessionManager->revokeSessionsForClaim($claim, 'submitted');

        $this->em->flush();
        
        // Event dispatching or notifications could happen here.
    }
}
