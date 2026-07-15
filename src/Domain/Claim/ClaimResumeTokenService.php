<?php

declare(strict_types=1);

namespace App\Domain\Claim;

use App\Entity\Core\LocationClaimRequest;

final class ClaimResumeTokenService
{
    public const DEFAULT_EXPIRES_IN_SECONDS = 86400;

    /**
     * @return array{token: string, hash: string, expires_at: \DateTimeImmutable}
     */
    public function generateToken(int $expiresInSeconds = self::DEFAULT_EXPIRES_IN_SECONDS): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresInSeconds));

        return [
            'token' => $token,
            'hash' => $hash,
            'expires_at' => $expiresAt,
        ];
    }

    public function isValid(LocationClaimRequest $claim, string $token): bool
    {
        if ($claim->getResumeTokenHash() === null || $claim->getResumeTokenExpiresAt() === null) {
            return false;
        }

        if ($claim->getResumeTokenRevokedAt() !== null) {
            return false;
        }

        if (new \DateTimeImmutable() > $claim->getResumeTokenExpiresAt()) {
            return false;
        }

        $expectedHash = hash('sha256', $token);

        return hash_equals($claim->getResumeTokenHash(), $expectedHash);
    }
}
