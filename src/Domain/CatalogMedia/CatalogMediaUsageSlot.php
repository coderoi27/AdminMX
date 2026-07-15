<?php

declare(strict_types=1);

namespace App\Domain\CatalogMedia;

final class CatalogMediaUsageSlot
{
    public const CATEGORY_DEFAULT = 'category_default';
    public const CATEGORY_COVER = 'category_cover';
    public const LOCATION_COVER = 'location_cover';
    public const LOCATION_GALLERY = 'location_gallery';
    public const STORY_IMAGE = 'story_image';
    public const STORY_VIDEO = 'story_video';
    public const MAP_CARD = 'map_card';
    public const PLACEHOLDER = 'placeholder';
    public const SOURCE_BADGE = 'source_badge';
    public const CATEGORY_ICON = 'category_icon';

    private const IMAGE_SLOTS = [
        self::CATEGORY_DEFAULT,
        self::CATEGORY_COVER,
        self::LOCATION_COVER,
        self::LOCATION_GALLERY,
        self::STORY_IMAGE,
        self::MAP_CARD,
        self::PLACEHOLDER,
    ];

    private const ICON_COMPATIBLE_SLOTS = [
        self::SOURCE_BADGE,
        self::CATEGORY_ICON,
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::CATEGORY_DEFAULT,
            self::CATEGORY_COVER,
            self::LOCATION_COVER,
            self::LOCATION_GALLERY,
            self::STORY_IMAGE,
            self::STORY_VIDEO,
            self::MAP_CARD,
            self::PLACEHOLDER,
            self::SOURCE_BADGE,
            self::CATEGORY_ICON,
        ];
    }

    public static function assertValid(string $value): void
    {
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported catalog media usage slot "%s".', $value));
        }
    }

    public static function acceptsMediaType(string $usageSlot, string $mediaType): bool
    {
        self::assertValid($usageSlot);
        CatalogMediaType::assertValid($mediaType);

        if ($usageSlot === self::STORY_VIDEO) {
            return $mediaType === CatalogMediaType::VIDEO;
        }

        if (in_array($usageSlot, self::IMAGE_SLOTS, true)) {
            return $mediaType === CatalogMediaType::IMAGE;
        }

        if (in_array($usageSlot, self::ICON_COMPATIBLE_SLOTS, true)) {
            return in_array($mediaType, [CatalogMediaType::ICON, CatalogMediaType::IMAGE], true);
        }

        return false;
    }

    public static function assertAcceptsMediaType(string $usageSlot, string $mediaType): void
    {
        if (!self::acceptsMediaType($usageSlot, $mediaType)) {
            throw new \InvalidArgumentException(sprintf(
                'Catalog media type "%s" is not compatible with usage slot "%s".',
                $mediaType,
                $usageSlot
            ));
        }
    }
}
