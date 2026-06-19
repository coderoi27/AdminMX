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
        $result = $this->otpService->generateOtp($claim, 'email_verification');
        
        $otpCode = $result['code'];
        $otpEntity = $result['entity'];

        $this->em->persist($otpEntity);

        // Update claim status if it's draft
        if ($claim->getStatus() === ClaimStateMachine::STATE_DRAFT) {
            $reflection = new \ReflectionClass($claim);
            $prop = $reflection->getProperty('status');
            $prop->setValue($claim, ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION);
        }

        $this->em->flush();

        // Send OTP via email
        $this->notificationSender->sendEmailOtp($claim, $otpCode);

        return [
            'status' => $claim->getStatus(),
            'expires_at' => $otpEntity->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'retry_after_seconds' => 60,
        ];
    }
}
