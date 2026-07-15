<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Domain\Claim\ClaimStateMachine;
use App\Domain\Claim\ClaimOtpService;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class RequestLocationClaimOtp
{
    private EntityManagerInterface $em;
    private ClaimStateMachine $stateMachine;
    private ClaimOtpService $otpService;
    private ClaimNotificationSenderInterface $notificationSender;

    public function __construct(
        EntityManagerInterface $em,
        ClaimStateMachine $stateMachine,
        ClaimOtpService $otpService,
        ClaimNotificationSenderInterface $notificationSender
    ) {
        $this->em = $em;
        $this->stateMachine = $stateMachine;
        $this->otpService = $otpService;
        $this->notificationSender = $notificationSender;
    }

    public function execute(LocationClaimRequest $claim): array
    {
        if (!$this->stateMachine->canTransitionTo($claim->getStatus(), ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION)) {
            // It could already be in pending_email_verification, which is allowed.
            if ($claim->getStatus() !== ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION) {
                throw new \DomainException('Cannot request OTP in current state.');
            }
        }

        // Generate OTP
        $ttlSeconds = ClaimOtpService::DEFAULT_EXPIRES_IN_SECONDS;
        $result = $this->otpService->generateOtp($claim, 'email_verification', $ttlSeconds);
        
        $otpCode = $result['code'];
        $otpEntity = $result['entity'];

        $this->em->persist($otpEntity);

        // Update claim status if it's draft
        if ($claim->getStatus() === ClaimStateMachine::STATE_DRAFT) {
            $claim->setStatus(ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION);
        }

        $this->em->flush();

        // Send OTP via email
        try {
            $this->notificationSender->sendEmailOtp($claim, $otpCode, (int) ceil($ttlSeconds / 60));
        } catch (\Throwable $e) {
            $this->em->remove($otpEntity);
            // Optionally, we could revert the status back to DRAFT here, but leaving it as PENDING_EMAIL_VERIFICATION is fine
            // since they can request another OTP.
            $this->em->flush();
            throw $e;
        }

        return [
            'status' => $claim->getStatus(),
            'expires_at' => $otpEntity->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'expires_in_seconds' => $ttlSeconds,
            'expires_in_minutes' => (int) ceil($ttlSeconds / 60),
            'retry_after_seconds' => 60,
        ];
    }
}
