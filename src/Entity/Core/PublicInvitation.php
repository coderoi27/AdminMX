<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'public_invitations')]
#[ORM\HasLifecycleCallbacks]
class PublicInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $tokenHash;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $inviteCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $campaignName = null;

    #[ORM\Column(length: 32)]
    private string $campaignType = 'marketing';

    #[ORM\Column(length: 180)]
    private string $messageSubject;

    #[ORM\Column(type: 'text')]
    private string $messageBody;

    #[ORM\Column(length: 32)]
    private string $status = 'draft';

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function setInviteCode(?string $inviteCode): self
    {
        $this->inviteCode = $inviteCode;

        return $this;
    }

    public function setCampaignName(?string $campaignName): self
    {
        $this->campaignName = $campaignName;

        return $this;
    }

    public function setCampaignType(string $campaignType): self
    {
        $this->campaignType = $campaignType;

        return $this;
    }

    public function setMessageSubject(string $messageSubject): self
    {
        $this->messageSubject = $messageSubject;

        return $this;
    }

    public function setMessageBody(string $messageBody): self
    {
        $this->messageBody = $messageBody;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCampaignName(): ?string
    {
        return $this->campaignName;
    }

    public function getCampaignType(): string
    {
        return $this->campaignType;
    }

    public function getInviteCode(): ?string
    {
        return $this->inviteCode;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getMessageSubject(): string
    {
        return $this->messageSubject;
    }

    public function getMessageBody(): string
    {
        return $this->messageBody;
    }
}
