<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimRequest;

final class ClaimStateMachine
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PENDING_EMAIL_VERIFICATION = LocationClaimRequest::STATUS_PENDING_EMAIL_VERIFICATION;
    public const STATE_PENDING_EVIDENCE = LocationClaimRequest::STATUS_PENDING_EVIDENCE;
    public const STATE_SUBMITTED = LocationClaimRequest::STATUS_SUBMITTED;
    public const STATE_UNDER_REVIEW = LocationClaimRequest::STATUS_UNDER_REVIEW;
    public const STATE_NEEDS_INFO = 'needs_info';
    public const STATE_APPROVED = LocationClaimRequest::STATUS_APPROVED;
    public const STATE_REJECTED = LocationClaimRequest::STATUS_REJECTED;
    public const STATE_CONVERTED = LocationClaimRequest::STATUS_CONVERTED;
    public const STATE_EXPIRED = 'expired';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_REVOKED_BEFORE_CONVERSION = 'revoked_before_conversion';
    public const STATE_CLOSED = 'closed';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATE_DRAFT => [
            self::STATE_PENDING_EMAIL_VERIFICATION,
            self::STATE_CANCELLED,
            self::STATE_EXPIRED,
        ],
        self::STATE_PENDING_EMAIL_VERIFICATION => [
            self::STATE_PENDING_EVIDENCE,
            self::STATE_CANCELLED,
            self::STATE_EXPIRED,
        ],
        self::STATE_PENDING_EVIDENCE => [
            self::STATE_SUBMITTED,
            self::STATE_CANCELLED,
            self::STATE_EXPIRED,
        ],
        self::STATE_SUBMITTED => [
            self::STATE_UNDER_REVIEW,
        ],
        self::STATE_UNDER_REVIEW => [
            self::STATE_NEEDS_INFO,
            self::STATE_APPROVED,
            self::STATE_REJECTED,
        ],
        self::STATE_NEEDS_INFO => [
            self::STATE_PENDING_EVIDENCE,
            self::STATE_SUBMITTED,
            self::STATE_REJECTED,
            self::STATE_EXPIRED,
        ],
        self::STATE_APPROVED => [
            self::STATE_CONVERTED,
            self::STATE_REVOKED_BEFORE_CONVERSION,
        ],
        self::STATE_CONVERTED => [
            self::STATE_CLOSED,
        ],
    ];

    public function canTransitionTo(string $currentStatus, string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        return in_array($newStatus, $allowed, true);
    }
}
