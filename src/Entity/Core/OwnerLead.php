<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'owner_leads')]
#[ORM\HasLifecycleCallbacks]
class OwnerLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $ownerName;

    #[ORM\Column(length: 160)]
    private string $businessName;

    #[ORM\Column(length: 120)]
    private string $city;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsappE164 = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $businessType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 32)]
    private string $status = 'new';

    #[ORM\Column(length: 32)]
    private string $sourceChannel = 'organic_form';

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): self
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    public function getBusinessName(): string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): self
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function setWhatsappE164(?string $whatsappE164): self
    {
        $this->whatsappE164 = $whatsappE164;

        return $this;
    }

    public function setBusinessType(?string $businessType): self
    {
        $this->businessType = $businessType;

        return $this;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSourceChannel(): string
    {
        return $this->sourceChannel;
    }

    public function setSourceChannel(string $sourceChannel): self
    {
        $this->sourceChannel = $sourceChannel;

        return $this;
    }

    public function getBusinessType(): ?string
    {
        return $this->businessType;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }



    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
