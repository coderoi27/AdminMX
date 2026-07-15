<?php
declare(strict_types=1);

namespace App\Tests\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use PHPUnit\Framework\TestCase;

class LocationClaimRequestEmailVerificationTest extends TestCase
{
    public function testInitialEmailVerificationIsNull(): void
    {
        $claim = new LocationClaimRequest();
        $this->assertNull($claim->getEmailVerifiedAt());
    }

    public function testMarkEmailVerifiedWithExplicitDate(): void
    {
        $fixed = new \DateTimeImmutable('2026-06-22T10:00:00+00:00');
        $claim = new LocationClaimRequest();
        $claim->markEmailVerified($fixed);
        $this->assertSame($fixed, $claim->getEmailVerifiedAt());
    }

    public function testMarkEmailVerifiedUsingNow(): void
    {
        $before = new \DateTimeImmutable();
        $claim = new LocationClaimRequest();
        $claim->markEmailVerified();
        $after = new \DateTimeImmutable();
        $emailVerified = $claim->getEmailVerifiedAt();
        $this->assertInstanceOf(\DateTimeImmutable::class, $emailVerified);
        $this->assertGreaterThanOrEqual($before, $emailVerified);
        $this->assertLessThanOrEqual($after, $emailVerified);
    }

    public function testClaimFlowAccessorsExposePersistedWorkflowFields(): void
    {
        $claim = new LocationClaimRequest();
        $expiresAt = new \DateTimeImmutable('2026-06-22T11:00:00+00:00');
        $revokedAt = new \DateTimeImmutable('2026-06-22T12:00:00+00:00');

        $claim
            ->setResumeTokenHash('resume-hash')
            ->setResumeTokenExpiresAt($expiresAt)
            ->setResumeTokenRevokedAt($revokedAt)
            ->setLastCompletedStep('legal')
            ->setLegalAcceptanceReference('terms:v1|privacy:v1');

        $this->assertSame('resume-hash', $claim->getResumeTokenHash());
        $this->assertSame($expiresAt, $claim->getResumeTokenExpiresAt());
        $this->assertSame($revokedAt, $claim->getResumeTokenRevokedAt());
        $this->assertSame('legal', $claim->getLastCompletedStep());
        $this->assertSame('terms:v1|privacy:v1', $claim->getLegalAcceptanceReference());
    }
}
