<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Entity\Core\LocationClaimRequest;
use Psr\Log\LoggerInterface;

final class InMemoryClaimNotificationSender implements ClaimNotificationSenderInterface
{
    private array $sentMessages = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function sendEmailOtp(LocationClaimRequest $claim, string $otpCode, int $ttlMinutes = 10): void
    {
        $this->sentMessages[] = [
            'type' => 'email_otp',
            'email' => $claim->getEmail(),
            'code' => $otpCode, // In a real system, never log the plaintext OTP. Here it is captured for testing.
            'ttl_minutes' => $ttlMinutes,
            'sent_at' => new \DateTimeImmutable(),
        ];
        
        $this->logger->info('Simulated sending email OTP to {email}', ['email' => $claim->getEmail()]);
    }

    public function sendResumeLink(LocationClaimRequest $claim, string $resumeToken, int $ttlHours = 24): void
    {
        $this->sentMessages[] = [
            'type' => 'resume_link',
            'email' => $claim->getEmail(),
            'token' => $resumeToken,
            'ttl_hours' => $ttlHours,
            'sent_at' => new \DateTimeImmutable(),
        ];

        $this->logger->info('Simulated sending resume link to {email}', ['email' => $claim->getEmail()]);
    }

    public function sendSubmissionConfirmation(LocationClaimRequest $claim): void
    {
        $this->sentMessages[] = [
            'type' => 'submission_confirmation',
            'email' => $claim->getEmail(),
            'sent_at' => new \DateTimeImmutable(),
        ];

        $this->logger->info('Simulated sending submission confirmation to {email}', ['email' => $claim->getEmail()]);
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }
}
