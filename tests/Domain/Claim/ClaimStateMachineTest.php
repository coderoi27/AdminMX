<?php

declare(strict_types=1);

namespace App\Tests\Domain\Claim;

use App\Domain\Claim\ClaimStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClaimStateMachineTest extends TestCase
{
    #[DataProvider('allowedAdminTransitions')]
    public function testAllowsOnlyExpectedAdminReviewTransitions(string $from, string $to): void
    {
        self::assertTrue((new ClaimStateMachine())->canTransitionTo($from, $to));
    }

    /** @return iterable<string, array{string, string}> */
    public static function allowedAdminTransitions(): iterable
    {
        yield 'request email verification' => [ClaimStateMachine::STATE_DRAFT, ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION];
        yield 'confirm email verification' => [ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION, ClaimStateMachine::STATE_PENDING_EVIDENCE];
        yield 'submit claim evidence' => [ClaimStateMachine::STATE_PENDING_EVIDENCE, ClaimStateMachine::STATE_SUBMITTED];
        yield 'start review' => [ClaimStateMachine::STATE_SUBMITTED, ClaimStateMachine::STATE_UNDER_REVIEW];
        yield 'request information' => [ClaimStateMachine::STATE_UNDER_REVIEW, ClaimStateMachine::STATE_NEEDS_INFO];
        yield 'approve review' => [ClaimStateMachine::STATE_UNDER_REVIEW, ClaimStateMachine::STATE_APPROVED];
        yield 'reject review' => [ClaimStateMachine::STATE_UNDER_REVIEW, ClaimStateMachine::STATE_REJECTED];
    }

    public function testApprovalDoesNotTransitionDirectlyToConverted(): void
    {
        self::assertFalse((new ClaimStateMachine())->canTransitionTo(
            ClaimStateMachine::STATE_UNDER_REVIEW,
            ClaimStateMachine::STATE_CONVERTED,
        ));
    }

    public function testApprovalAllowsConvertedOnlyAsExplicitTransition(): void
    {
        self::assertTrue((new ClaimStateMachine())->canTransitionTo(
            ClaimStateMachine::STATE_APPROVED,
            ClaimStateMachine::STATE_CONVERTED,
        ));
    }
}
