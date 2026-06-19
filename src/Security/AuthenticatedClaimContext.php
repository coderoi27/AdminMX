<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class AuthenticatedClaimContext implements UserInterface
{
    private string $claimUuid;
    private int $claimId;
    private bool $emailVerified;
    private array $scopes;
    private int $sessionId;
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        string $claimUuid,
        int $claimId,
        bool $emailVerified,
        array $scopes,
        int $sessionId,
        \DateTimeImmutable $expiresAt
    ) {
        $this->claimUuid = $claimUuid;
        $this->claimId = $claimId;
        $this->emailVerified = $emailVerified;
        $this->scopes = $scopes;
        $this->sessionId = $sessionId;
        $this->expiresAt = $expiresAt;
    }

    public function getClaimUuid(): string
    {
        return $this->claimUuid;
    }

    public function getClaimId(): int
    {
        return $this->claimId;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRoles(): array
    {
        return ['ROLE_CLAIM_SESSION'];
    }

    public function eraseCredentials(): void
    {
        // No sensitive credentials stored here
    }

    public function getUserIdentifier(): string
    {
        // Must return a non-sensitive identifier
        return 'claim_session_' . $this->sessionId;
    }
}
