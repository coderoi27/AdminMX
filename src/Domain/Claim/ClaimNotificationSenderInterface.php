<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimRequest;

interface ClaimNotificationSenderInterface
{
    /**
     * Sends an OTP code to the claimant's email.
     */
    public function sendEmailOtp(LocationClaimRequest $claim, string $otpCode, int $ttlMinutes = 10): void;

    /**
     * Sends the resume link to the claimant's email.
     */
    public function sendResumeLink(LocationClaimRequest $claim, string $resumeToken, int $ttlHours = 24): void;

    /**
     * Sends a confirmation email after successful submission.
     */
    public function sendSubmissionConfirmation(LocationClaimRequest $claim): void;
}
