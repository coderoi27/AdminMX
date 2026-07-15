<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final class CatalogMediaSourceType
{
    public const OWN = 'own';
    public const STOCK = 'stock';
    public const AI_GENERATED = 'ai_generated';
    public const LICENSED = 'licensed';
    public const UNKNOWN = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::OWN, self::STOCK, self::AI_GENERATED, self::LICENSED, self::UNKNOWN];
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media source type "%s".', $value));
        }
    }
}
