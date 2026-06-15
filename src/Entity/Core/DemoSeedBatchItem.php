<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demo_seed_batch_items')]
class DemoSeedBatchItem
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PURGED = 'purged';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DemoSeedBatch::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DemoSeedBatch $demoSeedBatch;

    #[ORM\ManyToOne(targetEntity: MerchantLocation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantLocation $merchantLocation;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $purgedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemoSeedBatch(): DemoSeedBatch
    {
        return $this->demoSeedBatch;
    }

    public function setDemoSeedBatch(DemoSeedBatch $demoSeedBatch): self
    {
        $this->demoSeedBatch = $demoSeedBatch;

        return $this;
    }

    public function getMerchantLocation(): MerchantLocation
    {
        return $this->merchantLocation;
    }

    public function setMerchantLocation(MerchantLocation $merchantLocation): self
    {
        $this->merchantLocation = $merchantLocation;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported batch item status "%s".', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getExpiredAt(): ?\DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(?\DateTimeImmutable $expiredAt): self
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    public function getPurgedAt(): ?\DateTimeImmutable
    {
        return $this->purgedAt;
    }

    public function setPurgedAt(?\DateTimeImmutable $purgedAt): self
    {
        $this->purgedAt = $purgedAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_HIDDEN,
            self::STATUS_EXPIRED,
            self::STATUS_PURGED,
        ];
    }
}
