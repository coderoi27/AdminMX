<?php

declare(strict_types=1);

namespace App\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\CatalogMediaCategoryAssignment;
use App\Entity\Core\CatalogMediaUsagePool;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\LocationMediaItem;
use App\Entity\Core\MerchantLocation;

final class CatalogMediaFeedResolver
{
    public const SOURCE_OWNER_MEDIA = 'owner_media';
    public const SOURCE_ADMIN_SPECIFIC = 'admin_specific';
    public const SOURCE_GOOGLE_PLACES = 'google_places';
    public const SOURCE_CATEGORY_POOL = 'category_pool';
    public const SOURCE_PARENT_CATEGORY_POOL = 'parent_category_pool';
    public const SOURCE_GLOBAL_POOL = 'global_pool';
    public const SOURCE_PLACEHOLDER = 'placeholder';

    private const MAX_GALLERY_ITEMS = 5;
    private const MAX_STORY_ITEMS = 5;
    private const PUBLIC_ASSET_BASE_URL = 'https://assets.mimonchis.mx/';

    public function __construct(private readonly CatalogMediaResolver $resolver)
    {
    }

    /**
     * @param array<string, list<CatalogMediaCategoryAssignment>> $assignmentsBySlot
     * @return array{
     *     cover: array{url:?string,media_type:string,source:string,asset_uuid:?string,alt:?string},
     *     gallery: list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string,sort_index:int}>,
     *     stories: list<array{url:string,media_type:string,source:string,asset_uuid:?string,poster_url:?string,alt:?string,sort_index:int}>,
     *     map_card: array{url:?string,media_type:string,source:string,asset_uuid:?string,alt:?string},
     *     fallback_used: bool,
     *     pool_version: int|null
     * }
     */
    public function resolve(MerchantLocation $location, array $assignmentsBySlot, bool $googlePlacesPhotosAllowed): array
    {
        $identity = $this->locationIdentity($location);
        $category = $location->getPrimaryCategory();
        $categoryIdentity = $category instanceof LocationCategory ? $category->getSlug() : 'uncategorized';
        $sourceType = $location->getSourceType();
        $isGooglePlace = $sourceType === MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES;
        $isFakeSeed = $sourceType === MerchantLocation::SOURCE_TYPE_FAKE_SEED;
        $isCanonical = !$isGooglePlace && !$isFakeSeed;
        $poolVersions = [];

        $cover = null;
        if ($isCanonical || $isFakeSeed || ($isGooglePlace && $googlePlacesPhotosAllowed)) {
            $cover = $this->primaryLocationMedia($location, $isGooglePlace ? self::SOURCE_GOOGLE_PLACES : ($isFakeSeed ? self::SOURCE_ADMIN_SPECIFIC : self::SOURCE_OWNER_MEDIA));
        }

        if ($cover === null) {
            $cover = $this->firstResolvedAssignment(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::LOCATION_COVER,
                $location,
                $identity,
                $categoryIdentity,
                $poolVersions,
            );
        }

        if ($cover === null) {
            $cover = $this->firstResolvedAssignment(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::CATEGORY_COVER,
                $location,
                $identity,
                $categoryIdentity,
                $poolVersions,
            ) ?? $this->legacyCategoryImage($category, 'cover');
        }

        if ($cover === null) {
            $cover = $this->firstResolvedAssignment(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::CATEGORY_DEFAULT,
                $location,
                $identity,
                $categoryIdentity,
                $poolVersions,
            ) ?? $this->legacyCategoryImage($category, 'default');
        }

        $cover ??= $this->emptyImage(self::SOURCE_PLACEHOLDER);

        $gallery = [];
        if ($isCanonical || $isFakeSeed || ($isGooglePlace && $googlePlacesPhotosAllowed)) {
            foreach ($this->locationGalleryMedia($location, $isGooglePlace ? self::SOURCE_GOOGLE_PLACES : ($isFakeSeed ? self::SOURCE_ADMIN_SPECIFIC : self::SOURCE_OWNER_MEDIA)) as $item) {
                $gallery[] = $item;
            }
        }

        if (count($gallery) < self::MAX_GALLERY_ITEMS) {
            $gallery = $this->mergeWithoutDuplicateUrls($gallery, $this->resolvedAssignments(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::LOCATION_GALLERY,
                $location,
                $identity,
                $categoryIdentity,
                self::MAX_GALLERY_ITEMS,
                $poolVersions,
            ), self::MAX_GALLERY_ITEMS);
        }
        $gallery = $this->removeCoverFromGalleryWhenPossible($gallery, $cover['url']);
        $gallery = $this->withSortIndex($gallery);

        $stories = [];
        if (!$isCanonical || $this->locationStoryMedia($location) === []) {
            $stories = $this->mergeWithoutDuplicateUrls($stories, $this->resolvedAssignments(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::STORY_IMAGE,
                $location,
                $identity,
                $categoryIdentity,
                self::MAX_STORY_ITEMS,
                $poolVersions,
            ), self::MAX_STORY_ITEMS);
            $stories = $this->mergeWithoutDuplicateUrls($stories, $this->resolvedAssignments(
                $assignmentsBySlot,
                CatalogMediaUsageSlot::STORY_VIDEO,
                $location,
                $identity,
                $categoryIdentity,
                self::MAX_STORY_ITEMS,
                $poolVersions,
            ), self::MAX_STORY_ITEMS);
        }
        $stories = $this->withSortIndex(array_map(
            static fn (array $story): array => $story + ['poster_url' => null],
            $stories,
        ));

        $mapCard = $this->firstResolvedAssignment(
            $assignmentsBySlot,
            CatalogMediaUsageSlot::MAP_CARD,
            $location,
            $identity,
            $categoryIdentity,
            $poolVersions,
        ) ?? [
            'url' => $cover['url'],
            'media_type' => CatalogMediaType::IMAGE,
            'source' => $cover['source'],
            'asset_uuid' => $cover['asset_uuid'],
            'alt' => $cover['alt'],
        ];

        return [
            'cover' => $cover,
            'gallery' => $gallery,
            'stories' => $stories,
            'map_card' => $mapCard,
            'fallback_used' => !in_array($cover['source'], [self::SOURCE_OWNER_MEDIA, self::SOURCE_GOOGLE_PLACES, self::SOURCE_ADMIN_SPECIFIC], true),
            'pool_version' => $poolVersions !== [] ? max($poolVersions) : null,
        ];
    }

    public function locationIdentity(MerchantLocation $location): string
    {
        $sourceType = $location->getSourceType();
        $externalSourceKey = $location->getExternalSourceKey();
        if ($sourceType === MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES && $externalSourceKey !== null) {
            return 'google_places:'.$externalSourceKey;
        }

        if ($sourceType === MerchantLocation::SOURCE_TYPE_FAKE_SEED) {
            return 'fake_seed:'.($externalSourceKey ?? $location->getSlug());
        }

        return 'canonical:'.$location->getSlug();
    }

    /**
     * @param array<string, list<CatalogMediaCategoryAssignment>> $assignmentsBySlot
     * @param list<int> $poolVersions
     * @return array{url:?string,media_type:string,source:string,asset_uuid:?string,alt:?string}|null
     */
    private function firstResolvedAssignment(
        array $assignmentsBySlot,
        string $slot,
        MerchantLocation $location,
        string $identity,
        string $categoryIdentity,
        array &$poolVersions,
    ): ?array {
        $resolved = $this->resolvedAssignments($assignmentsBySlot, $slot, $location, $identity, $categoryIdentity, 1, $poolVersions);

        return $resolved[0] ?? null;
    }

    /**
     * @param array<string, list<CatalogMediaCategoryAssignment>> $assignmentsBySlot
     * @param list<int> $poolVersions
     * @return list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}>
     */
    private function resolvedAssignments(
        array $assignmentsBySlot,
        string $slot,
        MerchantLocation $location,
        string $identity,
        string $categoryIdentity,
        int $count,
        array &$poolVersions,
    ): array {
        $candidates = $this->assignmentCandidates($assignmentsBySlot[$slot] ?? [], $slot, $poolVersions);
        if ($candidates === []) {
            return [];
        }

        $poolVersion = max(array_column($candidates, 'pool_version') ?: [1]);
        $resolved = $this->resolver->resolve($candidates, new CatalogMediaResolutionRequest(
            $location->getSourceType(),
            $identity,
            $categoryIdentity,
            $slot,
            (int) $poolVersion,
            $count,
        ));

        return array_map(static fn (array $item): array => [
            'url' => (string) $item['url'],
            'media_type' => (string) $item['media_type'],
            'source' => self::SOURCE_CATEGORY_POOL,
            'asset_uuid' => (string) $item['id'],
            'alt' => $item['alt'] ?? null,
        ], $resolved);
    }

    /**
     * @param list<CatalogMediaCategoryAssignment> $assignments
     * @param list<int> $poolVersions
     * @return list<array{id:string,url:string,media_type:string,priority:int,weight:int,active:bool,pool_version:int,alt:?string}>
     */
    private function assignmentCandidates(array $assignments, string $slot, array &$poolVersions): array
    {
        $candidates = [];
        foreach ($assignments as $assignment) {
            $asset = $assignment->getAsset();
            if (!$assignment->isActive() || !$asset instanceof CatalogMediaAsset || !$this->assetIsEligible($asset, $slot)) {
                continue;
            }

            $poolVersion = $assignment->getPool() instanceof CatalogMediaUsagePool ? $assignment->getPool()->getPoolVersion() : 1;
            $poolVersions[] = $poolVersion;
            $candidates[] = [
                'id' => $asset->getUuid(),
                'url' => (string) $asset->getPublicUrl(),
                'media_type' => $asset->getMediaType(),
                'priority' => max(0, 1000 - $assignment->getPriority()),
                'weight' => $assignment->getWeight(),
                'active' => true,
                'pool_version' => $poolVersion,
                'alt' => $asset->getAltText() !== '' ? $asset->getAltText() : $asset->getTitle(),
            ];
        }

        return $candidates;
    }

    private function assetIsEligible(CatalogMediaAsset $asset, string $slot): bool
    {
        $publicUrl = $asset->getPublicUrl();

        return $asset->isActive()
            && $asset->isRightsVerified()
            && $asset->getChecksum() !== null
            && $publicUrl !== null
            && str_starts_with($publicUrl, self::PUBLIC_ASSET_BASE_URL)
            && CatalogMediaUsageSlot::acceptsMediaType($slot, $asset->getMediaType());
    }

    /**
     * @return array{url:string,media_type:string,source:string,asset_uuid:null,alt:?string}|null
     */
    private function primaryLocationMedia(MerchantLocation $location, string $source): ?array
    {
        $media = $location->getPrimaryMediaItem();
        if (!$this->locationMediaIsEligible($media)) {
            return null;
        }

        return [
            'url' => $media->getUrl(),
            'media_type' => CatalogMediaType::IMAGE,
            'source' => $source,
            'asset_uuid' => null,
            'alt' => $media->getAltText() ?? $media->getTitle(),
        ];
    }

    /**
     * @return list<array{url:string,media_type:string,source:string,asset_uuid:null,alt:?string}>
     */
    private function locationGalleryMedia(MerchantLocation $location, string $source): array
    {
        $items = [];
        foreach ($location->getMediaItems() as $mediaItem) {
            if (!$this->locationMediaIsEligible($mediaItem)) {
                continue;
            }

            $items[] = [
                'url' => $mediaItem->getUrl(),
                'media_type' => CatalogMediaType::IMAGE,
                'source' => $source,
                'asset_uuid' => null,
                'alt' => $mediaItem->getAltText() ?? $mediaItem->getTitle(),
            ];
        }

        return array_slice($items, 0, self::MAX_GALLERY_ITEMS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function locationStoryMedia(MerchantLocation $location): array
    {
        return [];
    }

    private function locationMediaIsEligible(mixed $mediaItem): bool
    {
        return $mediaItem instanceof LocationMediaItem
            && $mediaItem->isActive()
            && $mediaItem->getModerationStatus() === LocationMediaItem::MODERATION_APPROVED
            && $mediaItem->getMediaType() === LocationMediaItem::TYPE_PHOTO
            && $this->publicUrlIsSafe($mediaItem->getUrl());
    }

    /**
     * @return array{url:string,media_type:string,source:string,asset_uuid:null,alt:?string}|null
     */
    private function legacyCategoryImage(?LocationCategory $category, string $kind): ?array
    {
        if (!$category instanceof LocationCategory) {
            return null;
        }

        $url = $kind === 'cover' ? $category->getCoverPhotoUrl() : $category->getDefaultPhotoUrl();
        if ($url === null || !$this->publicUrlIsSafe($url)) {
            return null;
        }

        return [
            'url' => $url,
            'media_type' => CatalogMediaType::IMAGE,
            'source' => self::SOURCE_CATEGORY_POOL,
            'asset_uuid' => null,
            'alt' => $category->getName(),
        ];
    }

    /**
     * @return array{url:null,media_type:string,source:string,asset_uuid:null,alt:null}
     */
    private function emptyImage(string $source): array
    {
        return [
            'url' => null,
            'media_type' => CatalogMediaType::IMAGE,
            'source' => $source,
            'asset_uuid' => null,
            'alt' => null,
        ];
    }

    private function publicUrlIsSafe(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        return preg_match('#^https?://#i', $url) === 1 || str_starts_with($url, '/');
    }

    /**
     * @param list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}> $base
     * @param list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}> $additional
     * @return list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}>
     */
    private function mergeWithoutDuplicateUrls(array $base, array $additional, int $limit): array
    {
        $seen = [];
        foreach ($base as $item) {
            $seen[$item['url']] = true;
        }

        foreach ($additional as $item) {
            if (isset($seen[$item['url']])) {
                continue;
            }

            $base[] = $item;
            $seen[$item['url']] = true;
            if (count($base) >= $limit) {
                break;
            }
        }

        return $base;
    }

    /**
     * @param list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}> $gallery
     * @return list<array{url:string,media_type:string,source:string,asset_uuid:?string,alt:?string}>
     */
    private function removeCoverFromGalleryWhenPossible(array $gallery, ?string $coverUrl): array
    {
        if ($coverUrl === null || count($gallery) < 2) {
            return $gallery;
        }

        return array_values(array_filter($gallery, static fn (array $item): bool => $item['url'] !== $coverUrl));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withSortIndex(array $items): array
    {
        foreach ($items as $index => &$item) {
            $item['sort_index'] = $index;
        }

        return $items;
    }
}
