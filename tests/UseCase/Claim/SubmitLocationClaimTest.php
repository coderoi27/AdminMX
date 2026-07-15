<?php

declare(strict_types=1);

namespace App\Tests\UseCase\Claim;

use App\Domain\Claim\ClaimAccessSessionManager;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use App\Domain\Claim\ClaimStateMachine;
use App\Entity\Core\LocationClaimAccessSession;
use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\SubmitLocationClaim;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SubmitLocationClaimTest extends TestCase
{
    public function testSuccessfulSubmitSendsSubmissionConfirmationOnceAfterFlush(): void
    {
        $claim = $this->readyClaim();
        $flushCount = 0;

        $entityManager = $this->entityManagerWithEvidence($claim);
        $entityManager
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$flushCount): void {
                ++$flushCount;
            });

        $notificationSender = $this->createMock(ClaimNotificationSenderInterface::class);
        $notificationSender
            ->expects($this->once())
            ->method('sendSubmissionConfirmation')
            ->with($this->callback(static function (LocationClaimRequest $sentClaim) use ($claim, &$flushCount): bool {
                TestCase::assertSame($claim, $sentClaim);
                TestCase::assertSame('owner@example.com', $sentClaim->getEmail());
                TestCase::assertSame(2, $flushCount);

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->useCase($entityManager, $notificationSender, $logger)->execute($claim);

        self::assertSame(ClaimStateMachine::STATE_SUBMITTED, $claim->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $claim->getSubmittedAt());
    }

    public function testRepeatedSubmittedClaimDoesNotSendDuplicateConfirmation(): void
    {
        $claim = $this->readyClaim();
        $claim->setStatus(ClaimStateMachine::STATE_SUBMITTED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');
        $entityManager->expects($this->never())->method('getRepository');

        $notificationSender = $this->createMock(ClaimNotificationSenderInterface::class);
        $notificationSender->expects($this->never())->method('sendSubmissionConfirmation');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->useCase($entityManager, $notificationSender, $logger)->execute($claim);

        self::assertSame(ClaimStateMachine::STATE_SUBMITTED, $claim->getStatus());
    }

    public function testNotificationFailureDoesNotRevertSubmittedStatusOrExposeSensitiveContext(): void
    {
        $claim = $this->readyClaim();

        $entityManager = $this->entityManagerWithEvidence($claim);
        $entityManager->expects($this->exactly(2))->method('flush');

        $notificationSender = $this->createMock(ClaimNotificationSenderInterface::class);
        $notificationSender
            ->expects($this->once())
            ->method('sendSubmissionConfirmation')
            ->with($claim)
            ->willThrowException(new \RuntimeException('smtp_password_or_token_leaked'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send claim submission confirmation email.',
                $this->callback(static function (array $context): bool {
                    TestCase::assertSame('claim-test-uuid', $context['claim_uuid'] ?? null);
                    TestCase::assertSame(\RuntimeException::class, $context['exception_class'] ?? null);
                    TestCase::assertArrayNotHasKey('email', $context);
                    TestCase::assertArrayNotHasKey('token', $context);
                    TestCase::assertArrayNotHasKey('otp', $context);
                    TestCase::assertArrayNotHasKey('error', $context);

                    return true;
                })
            );

        $this->useCase($entityManager, $notificationSender, $logger)->execute($claim);

        self::assertSame(ClaimStateMachine::STATE_SUBMITTED, $claim->getStatus());
    }

    public function testSubmitRequiresUploadedEvidence(): void
    {
        $claim = $this->readyClaim();

        $evidenceRepository = $this->createMock(EntityRepository::class);
        $evidenceRepository
            ->method('findBy')
            ->with(['claim' => $claim, 'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED]])
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(LocationClaimEvidence::class)
            ->willReturn($evidenceRepository);

        $notificationSender = $this->createMock(ClaimNotificationSenderInterface::class);
        $notificationSender->expects($this->never())->method('sendSubmissionConfirmation');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('At least one evidence file must be uploaded.');

        $this->useCase($entityManager, $notificationSender, $this->createMock(LoggerInterface::class))->execute($claim);
    }

    public function testSubmittedEmailTemplatesDoNotExposeInternalUuidOrStorage(): void
    {
        $html = file_get_contents(dirname(__DIR__, 3) . '/templates/emails/claim/submitted.html.twig');
        $text = file_get_contents(dirname(__DIR__, 3) . '/templates/emails/claim/submitted.txt.twig');
        self::assertIsString($html);
        self::assertIsString($text);

        foreach ([$html, $text] as $template) {
            self::assertStringContainsString('public_reference', $template);
            self::assertStringNotContainsString('claim_uuid', $template);
            self::assertStringNotContainsString('object_key', $template);
            self::assertStringNotContainsString('bucket', $template);
            self::assertStringContainsString('no garantiza aprobación', $template);
        }
    }

    private function readyClaim(): LocationClaimRequest
    {
        return (new LocationClaimRequest())
            ->setClaimUuid('claim-test-uuid')
            ->setLocationName('Tacos Test')
            ->setEmail('OWNER@example.com')
            ->setStatus(ClaimStateMachine::STATE_PENDING_EVIDENCE)
            ->setLegalAcceptanceReference('terms:v1|privacy:v1')
            ->markEmailVerified(new \DateTimeImmutable('2026-06-24T10:00:00+00:00'));
    }

    private function useCase(
        EntityManagerInterface $entityManager,
        ClaimNotificationSenderInterface $notificationSender,
        LoggerInterface $logger
    ): SubmitLocationClaim {
        return new SubmitLocationClaim(
            $entityManager,
            new ClaimStateMachine(),
            new ClaimAccessSessionManager($entityManager, 3600),
            $notificationSender,
            $logger
        );
    }

    private function entityManagerWithEvidence(LocationClaimRequest $claim): EntityManagerInterface
    {
        $evidenceRepository = $this->createMock(EntityRepository::class);
        $evidenceRepository
            ->method('findBy')
            ->with(['claim' => $claim, 'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED]])
            ->willReturn([new LocationClaimEvidence()]);

        $sessionRepository = $this->createMock(EntityRepository::class);
        $sessionRepository
            ->method('findBy')
            ->with(['claim' => $claim, 'revokedAt' => null])
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->willReturnCallback(static function (string $className) use ($evidenceRepository, $sessionRepository): EntityRepository {
                return match ($className) {
                    LocationClaimEvidence::class => $evidenceRepository,
                    LocationClaimAccessSession::class => $sessionRepository,
                    default => throw new \LogicException(sprintf('Unexpected repository "%s".', $className)),
                };
            });

        return $entityManager;
    }
}
