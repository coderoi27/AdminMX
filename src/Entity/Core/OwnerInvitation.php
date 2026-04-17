<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'owner_invitations')]
#[ORM\HasLifecycleCallbacks]
class OwnerInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OwnerLead::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?OwnerLead $ownerLead = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $tokenHash;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $inviteCode = null;

    #[ORM\Column(length: 32)]
    private string $invitationType = 'pre_register';

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

    public function setOwnerLead(?OwnerLead $ownerLead): self
    {
        $this->ownerLead = $ownerLead;

        return $this;
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

    public function setInvitationType(string $invitationType): self
    {
        $this->invitationType = $invitationType;

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

    public function getInvitationType(): string
    {
        return $this->invitationType;
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

    public function getMessageSubject(): string
    {
        return $this->messageSubject;
    }

    public function getMessageBody(): string
    {
        return $this->messageBody;
    }
}
