<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'place_category_rules')]
#[ORM\HasLifecycleCallbacks]
class PlaceCategoryRule
{
    public const RULE_TYPE_GOOGLE_TYPE = 'google_type';
    public const RULE_TYPE_NAME_KEYWORD = 'name_keyword';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocationCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LocationCategory $category = null;

    #[ORM\Column(length: 32)]
    private string $ruleType = self::RULE_TYPE_GOOGLE_TYPE;

    #[ORM\Column(length: 160)]
    private string $matchValue = '';

    #[ORM\Column]
    private int $priority = 100;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public static function ruleTypes(): array
    {
        return [
            self::RULE_TYPE_GOOGLE_TYPE,
            self::RULE_TYPE_NAME_KEYWORD,
        ];
    }

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

    public function getCategory(): ?LocationCategory
    {
        return $this->category;
    }

    public function setCategory(LocationCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function setRuleType(string $ruleType): self
    {
        if (!in_array($ruleType, self::ruleTypes(), true)) {
            $ruleType = self::RULE_TYPE_GOOGLE_TYPE;
        }

        $this->ruleType = $ruleType;

        return $this;
    }

    public function getMatchValue(): string
    {
        return $this->matchValue;
    }

    public function setMatchValue(string $matchValue): self
    {
        $this->matchValue = trim(mb_strtolower($matchValue));

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
