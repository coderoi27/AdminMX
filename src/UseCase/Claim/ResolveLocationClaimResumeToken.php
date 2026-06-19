<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Domain\Claim\ClaimResumeTokenService;
use Doctrine\ORM\EntityManagerInterface;

final class ResolveLocationClaimResumeToken
{
    private EntityManagerInterface $em;
    private ClaimResumeTokenService $resumeTokenService;
    private \App\Domain\Claim\ClaimAccessSessionManager $sessionManager;

    public function __construct(
        EntityManagerInterface $em,
        ClaimResumeTokenService $resumeTokenService,
        \App\Domain\Claim\ClaimAccessSessionManager $sessionManager
    ) {
        $this->em = $em;
        $this->resumeTokenService = $resumeTokenService;
        $this->sessionManager = $sessionManager;
    }

    public function execute(string $token): ?array
    {
        $expectedHash = hash('sha256', $token);

        $repository = $this->em->getRepository(LocationClaimRequest::class);
        $claim = $repository->findOneBy(['resumeTokenHash' => $expectedHash]);

        if (!$claim) {
            return null;
        }

        if (!$this->resumeTokenService->isValid($claim, $token)) {
            return null;
        }

        // Consume resume token
        $reflection = new \ReflectionClass($claim);
        $propRevokedAt = $reflection->getProperty('resumeTokenRevokedAt');
        $propRevokedAt->setValue($claim, new \DateTimeImmutable());

        // Issue Access Token
        $scopes = ['claim:write', 'claim:evidence', 'claim:submit'];
        $accessTokenData = $this->sessionManager->issueToken($claim, $scopes);

        $this->em->flush();

        return [
            'claim' => $claim,
            'access_token' => $accessTokenData['token'],
            'expires_in' => $accessTokenData['expires_in'],
            'token_type' => 'Bearer',
        ];
    }
}
