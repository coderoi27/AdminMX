<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimOtp;
use App\Domain\Claim\ClaimStateMachine;
use App\Domain\Claim\ClaimOtpService;
use App\Domain\Claim\ClaimResumeTokenService;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Domain\Claim\ClaimAccessSessionManager;
use App\Domain\Claim\Exception\ClaimOtpException;
use Doctrine\ORM\EntityManagerInterface;

final class ConfirmLocationClaimOtp
{
    private EntityManagerInterface $em;
    private ClaimStateMachine $stateMachine;
    private ClaimOtpService $otpService;
    private ClaimResumeTokenService $resumeTokenService;
    private ClaimNotificationSenderInterface $notificationSender;
    private ClaimAccessSessionManager $sessionManager;

    public function __construct(
        EntityManagerInterface $em,
        ClaimStateMachine $stateMachine,
        ClaimOtpService $otpService,
        ClaimResumeTokenService $resumeTokenService,
        ClaimNotificationSenderInterface $notificationSender,
        ClaimAccessSessionManager $sessionManager,
        private int $claimResumeTokenTtl
    ) {
        $this->em = $em;
        $this->stateMachine = $stateMachine;
        $this->otpService = $otpService;
        $this->resumeTokenService = $resumeTokenService;
        $this->notificationSender = $notificationSender;
        $this->sessionManager = $sessionManager;
    }

    public function execute(LocationClaimRequest $claim, string $code): array
    {
        if ($claim->getStatus() !== ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION) {
            throw new \DomainException('Cannot confirm OTP in current state.');
        }

        $otpRepository = $this->em->getRepository(LocationClaimOtp::class);
        $otp = $otpRepository->findOneBy(
            ['claim' => $claim, 'purpose' => 'email_verification'],
            ['id' => 'DESC']
        );

        if (!$otp) {
            throw new \DomainException('No OTP found.');
        }

        $otpResult = $this->otpService->verifyOtpResult($otp, $code);
        if ($otpResult !== ClaimOtpService::VERIFY_VALID) {
            $this->em->flush(); // Save attempt count changes
            throw match ($otpResult) {
                ClaimOtpService::VERIFY_EXPIRED => ClaimOtpException::expired(),
                ClaimOtpService::VERIFY_ATTEMPTS_EXHAUSTED => ClaimOtpException::attemptsExhausted(),
                default => ClaimOtpException::invalid(),
            };
        }

        $claim->markEmailVerified();

        if ($this->stateMachine->canTransitionTo($claim->getStatus(), ClaimStateMachine::STATE_PENDING_EVIDENCE)) {
            $claim->setStatus(ClaimStateMachine::STATE_PENDING_EVIDENCE);
        }

        // Issue Resume Token & Send Email
        $resumeTokenData = $this->resumeTokenService->generateToken($this->claimResumeTokenTtl);
        $claim
            ->setResumeTokenHash($resumeTokenData['hash'])
            ->setResumeTokenExpiresAt($resumeTokenData['expires_at']);

        // Issue Access Token
        $scopes = ['claim:write', 'claim:evidence', 'claim:submit'];
        $accessTokenData = $this->sessionManager->issueToken($claim, $scopes, $otp);

        // Transactional commit (Doctrine flushes all managed entities and insertions together)
        $this->em->flush();

        $this->notificationSender->sendResumeLink($claim, $resumeTokenData['token'], $this->ttlHours());

        return [
            'claim_uuid' => $claim->getClaimUuid(),
            'email_verified' => true,
            'access_token' => $accessTokenData['token'],
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenData['expires_in'],
        ];
    }

    private function ttlHours(): int
    {
        return (int) ceil($this->claimResumeTokenTtl / 3600);
    }
}
