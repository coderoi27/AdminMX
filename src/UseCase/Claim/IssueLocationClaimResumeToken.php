<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Domain\Claim\ClaimResumeTokenService;
use App\Domain\Claim\ClaimNotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IssueLocationClaimResumeToken
{
    private EntityManagerInterface $em;
    private ClaimResumeTokenService $resumeTokenService;
    private ClaimNotificationSenderInterface $notificationSender;

    public function __construct(
        EntityManagerInterface $em,
        ClaimResumeTokenService $resumeTokenService,
        ClaimNotificationSenderInterface $notificationSender,
        private int $claimResumeTokenTtl
    ) {
        $this->em = $em;
        $this->resumeTokenService = $resumeTokenService;
        $this->notificationSender = $notificationSender;
    }

    public function execute(string $email): void
    {
        // Find latest active claim for this email
        // To prevent timing attacks, we should always do comparable work.
        // We'll search and if found, process. If not, do dummy work or just return.
        $repository = $this->em->getRepository(LocationClaimRequest::class);
        $claims = $repository->findBy(['email' => $email], ['createdAt' => 'DESC']);

        $activeClaim = null;
        foreach ($claims as $claim) {
            if (!in_array($claim->getStatus(), ['rejected', 'converted', 'closed', 'cancelled'], true)) {
                $activeClaim = $claim;
                break;
            }
        }

        if ($activeClaim) {
            $tokenData = $this->resumeTokenService->generateToken($this->claimResumeTokenTtl);
            
            $reflection = new \ReflectionClass($activeClaim);
            $propResumeHash = $reflection->getProperty('resumeTokenHash');
            $propResumeHash->setValue($activeClaim, $tokenData['hash']);
            
            $propResumeExpires = $reflection->getProperty('resumeTokenExpiresAt');
            $propResumeExpires->setValue($activeClaim, $tokenData['expires_at']);

            $this->em->flush();

            $this->notificationSender->sendResumeLink($activeClaim, $tokenData['token'], $this->ttlHours());
        }
        
        // No Exception is thrown to prevent enumeration. Always return void/success.
    }

    private function ttlHours(): int
    {
        return (int) ceil($this->claimResumeTokenTtl / 3600);
    }
}
