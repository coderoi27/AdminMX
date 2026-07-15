<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'location_claim_otps')]
#[ORM\HasLifecycleCallbacks]
class LocationClaimOtp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocationClaimRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocationClaimRequest $claim;

    #[ORM\Column(length: 64)]
    private string $purpose = '';

    #[ORM\Column(length: 255)]
    private string $codeHash = '';

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private int $attemptCount = 0;

    #[ORM\Column]
    private int $maxAttempts = 3;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getClaim(): LocationClaimRequest { return $this->claim; }
    public function setClaim(LocationClaimRequest $claim): self { $this->claim = $claim; return $this; }
    public function getPurpose(): string { return $this->purpose; }
    public function setPurpose(string $purpose): self { $this->purpose = $purpose; return $this; }
    public function getCodeHash(): string { return $this->codeHash; }
    public function setCodeHash(string $codeHash): self { $this->codeHash = $codeHash; return $this; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }
    public function getAttemptCount(): int { return $this->attemptCount; }
    public function setAttemptCount(int $attemptCount): self { $this->attemptCount = $attemptCount; return $this; }
    public function getMaxAttempts(): int { return $this->maxAttempts; }
    public function setMaxAttempts(int $maxAttempts): self { $this->maxAttempts = $maxAttempts; return $this; }
    public function getConsumedAt(): ?\DateTimeImmutable { return $this->consumedAt; }
    public function setConsumedAt(?\DateTimeImmutable $consumedAt): self { $this->consumedAt = $consumedAt; return $this; }
    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
    public function setRequestedAt(\DateTimeImmutable $requestedAt): self { $this->requestedAt = $requestedAt; return $this; }
    public function getLastAttemptAt(): ?\DateTimeImmutable { return $this->lastAttemptAt; }
    public function setLastAttemptAt(?\DateTimeImmutable $lastAttemptAt): self { $this->lastAttemptAt = $lastAttemptAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
