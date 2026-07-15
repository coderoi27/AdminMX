<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final class CatalogMediaStatus
{
    public const UPLOADING = 'uploading';
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';
    public const FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::UPLOADING, self::ACTIVE, self::ARCHIVED, self::FAILED];
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media status "%s".', $value));
        }
    }
}
