<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_claim_access_sessions')]
#[ORM\Index(columns: ['claim_id'], name: 'idx_access_session_claim')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_access_session_expires')]
#[ORM\Index(columns: ['revoked_at'], name: 'idx_access_session_revoked')]
class LocationClaimAccessSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocationClaimRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocationClaimRequest $claim;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $revocationReason = null;

    // Relación opcional con OTP si aplica
    #[ORM\ManyToOne(targetEntity: LocationClaimOtp::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocationClaimOtp $createdFromOtp = null;

    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClaim(): LocationClaimRequest
    {
        return $this->claim;
    }

    public function setClaim(LocationClaimRequest $claim): self
    {
        $this->claim = $claim;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): self
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getRevocationReason(): ?string
    {
        return $this->revocationReason;
    }

    public function setRevocationReason(?string $revocationReason): self
    {
        $this->revocationReason = $revocationReason;

        return $this;
    }

    public function getCreatedFromOtp(): ?LocationClaimOtp
    {
        return $this->createdFromOtp;
    }

    public function setCreatedFromOtp(?LocationClaimOtp $createdFromOtp): self
    {
        $this->createdFromOtp = $createdFromOtp;

        return $this;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function isValid(): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }

        if (new \DateTimeImmutable() > $this->expiresAt) {
            return false;
        }

        return true;
    }
}
