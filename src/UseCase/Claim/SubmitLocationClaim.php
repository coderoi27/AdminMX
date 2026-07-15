<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimEvidence;
use App\Domain\Claim\ClaimAccessSessionManager;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class SubmitLocationClaim
{
    private EntityManagerInterface $em;
    private ClaimStateMachine $stateMachine;
    private ClaimAccessSessionManager $sessionManager;
    private ClaimNotificationSenderInterface $notificationSender;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        ClaimStateMachine $stateMachine,
        ClaimAccessSessionManager $sessionManager,
        ClaimNotificationSenderInterface $notificationSender,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->stateMachine = $stateMachine;
        $this->sessionManager = $sessionManager;
        $this->notificationSender = $notificationSender;
        $this->logger = $logger;
    }

    public function execute(LocationClaimRequest $claim): void
    {
        if ($claim->getStatus() === ClaimStateMachine::STATE_SUBMITTED) {
            return;
        }

        if (!$this->stateMachine->canTransitionTo($claim->getStatus(), ClaimStateMachine::STATE_SUBMITTED)) {
            throw new \DomainException('Cannot submit claim in current state.');
        }

        // Validation: Must have email verified
        if ($claim->getEmailVerifiedAt() === null) {
            throw new \DomainException('Email must be verified before submitting.');
        }

        // Validation: Must have legal acceptance
        if (empty($claim->getLegalAcceptanceReference())) {
            throw new \DomainException('Legal acceptance is required before submitting.');
        }

        // Validation: Must have at least one uploaded evidence
        $evidenceRepo = $this->em->getRepository(LocationClaimEvidence::class);
        $evidences = $evidenceRepo->findBy(['claim' => $claim, 'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED]]);
        
        if (count($evidences) === 0) {
            throw new \DomainException('At least one evidence file must be uploaded.');
        }

        $claim->setStatus(ClaimStateMachine::STATE_SUBMITTED);
        $this->em->flush();

        // Revoke active sessions
        $this->sessionManager->revokeSessionsForClaim($claim, 'submitted');
        $this->em->flush();

        try {
            $this->notificationSender->sendSubmissionConfirmation($claim);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send claim submission confirmation email.', [
                'claim_uuid' => $claim->getClaimUuid(),
                'exception_class' => $e::class,
            ]);
        }
    }
}
