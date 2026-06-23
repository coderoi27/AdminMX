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
}
