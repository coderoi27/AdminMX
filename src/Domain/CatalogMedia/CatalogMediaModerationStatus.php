<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final class CatalogMediaModerationStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED];
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media moderation status "%s".', $value));
        }
    }
}
