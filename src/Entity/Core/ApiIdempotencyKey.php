<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_idempotency_keys')]
class ApiIdempotencyKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $keyHash = '';

    #[ORM\Column(length: 64)]
    private string $operation = '';

    #[ORM\Column(length: 255)]
    private string $requestFingerprint = '';

    #[ORM\Column(nullable: true)]
    private ?int $responseStatus = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $responsePayloadJson = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): self
    {
        $this->keyHash = $keyHash;
        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    public function getRequestFingerprint(): string
    {
        return $this->requestFingerprint;
    }

    public function setRequestFingerprint(string $requestFingerprint): self
    {
        $this->requestFingerprint = $requestFingerprint;
        return $this;
    }

    public function getResponseStatus(): ?int
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(?int $responseStatus): self
    {
        $this->responseStatus = $responseStatus;
        return $this;
    }

    public function getResponsePayloadJson(): ?array
    {
        return $this->responsePayloadJson;
    }

    public function setResponsePayloadJson(?array $responsePayloadJson): self
    {
        $this->responsePayloadJson = $responsePayloadJson;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
