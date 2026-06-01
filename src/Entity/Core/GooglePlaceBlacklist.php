<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Repository\Core\GooglePlaceBlacklistRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GooglePlaceBlacklistRepository::class)]
#[ORM\Table(
    name: 'google_place_blacklist',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_google_place_blacklist_rule', columns: ['match_type', 'external_source_key']),
    ]
)]
class GooglePlaceBlacklist
{
    public const MATCH_TYPE_PLACE_ID = 'place_id';
    public const MATCH_TYPE_NAME_KEYWORD = 'name_keyword';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $matchType = self::MATCH_TYPE_PLACE_ID;

    #[ORM\Column(length: 255)]
    private ?string $externalSourceKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public static function matchTypes(): array
    {
        return [
            self::MATCH_TYPE_PLACE_ID,
            self::MATCH_TYPE_NAME_KEYWORD,
        ];
    }

    public function getMatchType(): string
    {
        return $this->matchType;
    }

    public function setMatchType(string $matchType): static
    {
        if (!in_array($matchType, self::matchTypes(), true)) {
            $matchType = self::MATCH_TYPE_PLACE_ID;
        }

        $this->matchType = $matchType;

        return $this;
    }

    public function getExternalSourceKey(): ?string
    {
        return $this->externalSourceKey;
    }

    public function setExternalSourceKey(string $externalSourceKey): static
    {
        $this->externalSourceKey = trim(mb_strtolower($externalSourceKey));

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
