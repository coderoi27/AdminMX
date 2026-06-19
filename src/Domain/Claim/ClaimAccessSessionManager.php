<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimAccessSession;
use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimOtp;
use Doctrine\ORM\EntityManagerInterface;

final class ClaimAccessSessionManager
{
    private EntityManagerInterface $em;
    private int $ttl;

    public function __construct(EntityManagerInterface $em, int $claimAccessTokenTtl)
    {
        $this->em = $em;
        
        if ($claimAccessTokenTtl <= 0) {
            throw new \InvalidArgumentException('Claim Access Token TTL must be a positive integer.');
        }
        
        $this->ttl = $claimAccessTokenTtl;
    }

    public function issueToken(LocationClaimRequest $claim, array $scopes, ?LocationClaimOtp $otp = null): array
    {
        // En Alpha solo se permite una sesión activa por claim, por lo que rotamos (revocamos) las anteriores.
        $this->revokeSessionsForClaim($claim, 'rotated');

        $tokenPlain = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenPlain);

        $session = new LocationClaimAccessSession();
        $session->setClaim($claim);
        $session->setTokenHash($tokenHash);
        $session->setScopes($scopes);
        $session->setCreatedFromOtp($otp);

        $now = new \DateTimeImmutable();
        $session->setIssuedAt($now);
        $session->setExpiresAt($now->modify(sprintf('+%d seconds', $this->ttl)));

        $this->em->persist($session);
        // Dejamos que el invocador haga flush si esto es parte de una transacción mayor
        
        return [
            'token' => $tokenPlain,
            'expires_in' => $this->ttl,
        ];
    }

    public function resolveToken(string $tokenPlain): ?LocationClaimAccessSession
    {
        $tokenHash = hash('sha256', $tokenPlain);

        $session = $this->em->getRepository(LocationClaimAccessSession::class)->findOneBy([
            'tokenHash' => $tokenHash
        ]);

        if (!$session || !$session->isValid()) {
            return null;
        }

        // En alpha actualizamos el lastUsedAt en cada operación protegida porque el volumen es bajo
        $session->setLastUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $session;
    }

    public function revokeSession(LocationClaimAccessSession $session, string $reason): void
    {
        if ($session->getRevokedAt() === null) {
            $session->setRevokedAt(new \DateTimeImmutable());
            $session->setRevocationReason($reason);
        }
    }

    public function revokeSessionsForClaim(LocationClaimRequest $claim, string $reason): void
    {
        $activeSessions = $this->em->getRepository(LocationClaimAccessSession::class)->findBy([
            'claim' => $claim,
            'revokedAt' => null
        ]);

        foreach ($activeSessions as $session) {
            $this->revokeSession($session, $reason);
        }
    }
}
