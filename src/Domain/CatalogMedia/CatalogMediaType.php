<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final class CatalogMediaType
{
    public const IMAGE = 'image';
    public const VIDEO = 'video';
    public const ICON = 'icon';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::IMAGE, self::VIDEO, self::ICON];
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media type "%s".', $value));
        }
    }
}
