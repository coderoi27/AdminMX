<?php

declare(strict_types=1);

namespace App\Tests\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\CatalogMediaCategoryAssignment;
use App\Entity\Core\CatalogMediaUsagePool;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\LocationMediaItem;
use App\Entity\Core\MerchantLocation;
use App\Service\CatalogMedia\CatalogMediaFeedResolver;
use App\Service\CatalogMedia\CatalogMediaResolver;
use PHPUnit\Framework\TestCase;

final class CatalogMediaFeedResolverTest extends TestCase
{
    private CatalogMediaFeedResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CatalogMediaFeedResolver(new CatalogMediaResolver());
    }

    public function testSameIdentityProducesStableCover(): void
    {
        $location = $this->location('canonical-a', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, $this->category());
        $assignments = [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 6),
        ];

        $left = $this->resolver->resolve($location, $assignments, false);
        $right = $this->resolver->resolve($location, $assignments, false);

        self::assertSame($left['cover'], $right['cover']);
        self::assertSame('category_pool', $left['cover']['source']);
    }

    public function testPoolVersionIsExposedWithoutInternalStorageFields(): void
    {
        $location = $this->location('canonical-a', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, $this->category());
        $assignments = [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 2, poolVersion: 3),
        ];

        $resolved = $this->resolver->resolve($location, $assignments, false);
        $json = json_encode($resolved, JSON_THROW_ON_ERROR);

        self::assertSame(3, $resolved['pool_version']);
        self::assertStringNotContainsString('object_key', $json);
        self::assertStringNotContainsString('bucket', $json);
        self::assertStringNotContainsString('catalog-media/general/images', $json);
        self::assertStringContainsString('asset_uuid', $json);
    }

    public function testCanonicalOwnerCoverWinsOverCategory(): void
    {
        $location = $this->location('canonical-owner', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, $this->category());
        $location->addMediaItem($this->locationMedia('https://owner.example.test/cover.jpg', true));

        $resolved = $this->resolver->resolve($location, [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 3),
        ], false);

        self::assertSame('https://owner.example.test/cover.jpg', $resolved['cover']['url']);
        self::assertSame('owner_media', $resolved['cover']['source']);
        self::assertFalse($resolved['fallback_used']);
    }

    public function testCanonicalWithoutOwnerMediaUsesCategoryPool(): void
    {
        $location = $this->location('canonical-new', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, $this->category());

        $resolved = $this->resolver->resolve($location, [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 3),
        ], false);

        self::assertStringStartsWith('https://assets.mimonchis.mx/', (string) $resolved['cover']['url']);
        self::assertSame('category_pool', $resolved['cover']['source']);
        self::assertTrue($resolved['fallback_used']);
    }

    public function testOwnerGalleryPartialCompletesWithCategoryWithoutDuplicates(): void
    {
        $location = $this->location('canonical-gallery', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, $this->category());
        $location->addMediaItem($this->locationMedia('https://owner.example.test/gallery-1.jpg', true));
        $location->addMediaItem($this->locationMedia('https://owner.example.test/gallery-2.jpg'));

        $resolved = $this->resolver->resolve($location, [
            CatalogMediaUsageSlot::LOCATION_GALLERY => $this->assignments(CatalogMediaUsageSlot::LOCATION_GALLERY, 8),
        ], false);

        self::assertLessThanOrEqual(5, count($resolved['gallery']));
        self::assertSame(array_column($resolved['gallery'], 'url'), array_values(array_unique(array_column($resolved['gallery'], 'url'))));
        self::assertSame('owner_media', $resolved['gallery'][0]['source']);
        self::assertContains('category_pool', array_column($resolved['gallery'], 'source'));
    }

    public function testPlacePhotosOffUsesCategoryAndPhotosOnUsesExistingGoogleMedia(): void
    {
        $category = $this->category();
        $location = $this->location('google-a', MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES, $category, 'ChIJ-test');
        $location->addMediaItem($this->locationMedia('https://googleusercontent.example.test/photo.jpg', true));
        $assignments = [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 2),
        ];

        $off = $this->resolver->resolve($location, $assignments, false);
        $on = $this->resolver->resolve($location, $assignments, true);

        self::assertSame('category_pool', $off['cover']['source']);
        self::assertStringStartsWith('https://assets.mimonchis.mx/', (string) $off['cover']['url']);
        self::assertSame('google_places', $on['cover']['source']);
        self::assertSame('https://googleusercontent.example.test/photo.jpg', $on['cover']['url']);
    }

    public function testFakeSeedUsesCategoryPoolWhenSeedHasNoSpecificMedia(): void
    {
        $location = $this->location('demo-a', MerchantLocation::SOURCE_TYPE_FAKE_SEED, $this->category(), 'seed-001');

        $resolved = $this->resolver->resolve($location, [
            CatalogMediaUsageSlot::LOCATION_COVER => $this->assignments(CatalogMediaUsageSlot::LOCATION_COVER, 2),
        ], false);

        self::assertSame('fake_seed:seed-001', $this->resolver->locationIdentity($location));
        self::assertSame('category_pool', $resolved['cover']['source']);
    }

    public function testStoriesResolveImageAndVideoWithoutDuplicates(): void
    {
        $location = $this->location('stories-a', MerchantLocation::SOURCE_TYPE_FAKE_SEED, $this->category());
        $resolved = $this->resolver->resolve($location, [
            CatalogMediaUsageSlot::STORY_IMAGE => $this->assignments(CatalogMediaUsageSlot::STORY_IMAGE, 3),
            CatalogMediaUsageSlot::STORY_VIDEO => $this->assignments(CatalogMediaUsageSlot::STORY_VIDEO, 3, CatalogMediaType::VIDEO, 'video/mp4', 'mp4'),
        ], false);

        self::assertNotEmpty($resolved['stories']);
        self::assertSame(array_column($resolved['stories'], 'url'), array_values(array_unique(array_column($resolved['stories'], 'url'))));
        self::assertContains(CatalogMediaType::IMAGE, array_column($resolved['stories'], 'media_type'));
        self::assertContains(CatalogMediaType::VIDEO, array_column($resolved['stories'], 'media_type'));
    }

    public function testPlaceholderIsFinalFallbackWhenNoMediaExists(): void
    {
        $resolved = $this->resolver->resolve(
            $this->location('empty-a', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED, null),
            [],
            false,
        );

        self::assertNull($resolved['cover']['url']);
        self::assertSame('placeholder', $resolved['cover']['source']);
        self::assertTrue($resolved['fallback_used']);
    }

    /**
     * @return list<CatalogMediaCategoryAssignment>
     */
    private function assignments(
        string $slot,
        int $count,
        string $mediaType = CatalogMediaType::IMAGE,
        string $mimeType = 'image/webp',
        string $extension = 'webp',
        int $poolVersion = 1,
    ): array {
        $category = $this->category();
        $pool = (new CatalogMediaUsagePool())
            ->setName('Pool '.$slot)
            ->setSlug('pool-'.$slot.'-'.$poolVersion)
            ->setUsageSlot($slot)
            ->setPoolVersion($poolVersion);
        $assignments = [];

        for ($i = 1; $i <= $count; $i += 1) {
            $assignments[] = (new CatalogMediaCategoryAssignment())
                ->setCategory($category)
                ->setPool($pool)
                ->setAsset($this->asset($slot.' '.$i, $mediaType, $mimeType, $extension))
                ->setUsageSlot($slot)
                ->setPriority($i)
                ->setWeight(1);
        }

        return $assignments;
    }

    private function asset(string $title, string $mediaType, string $mimeType, string $extension): CatalogMediaAsset
    {
        $uuid = sprintf('00000000-0000-4000-8000-%012d', abs(crc32($title)));
        $objectKey = sprintf('categories/taquerias/%s/original.%s', $uuid, $extension);

        return (new CatalogMediaAsset($uuid))
            ->setTitle($title)
            ->setAltText('Alt '.$title)
            ->setOriginalFilename('original.'.$extension)
            ->setStorageProvider('cloudflare_r2_catalog_media')
            ->setObjectKey($objectKey)
            ->setPublicUrl('https://assets.mimonchis.mx/'.$objectKey)
            ->setMimeType($mimeType)
            ->setMediaType($mediaType)
            ->setBytes(2048)
            ->setChecksum('etag-'.$uuid)
            ->setSourceType(CatalogMediaSourceType::OWN)
            ->setRightsVerified(true)
            ->setModerationStatus(CatalogMediaModerationStatus::APPROVED)
            ->setStatus(CatalogMediaStatus::ACTIVE);
    }

    private function location(string $slug, string $sourceType, ?LocationCategory $category, ?string $externalSourceKey = null): MerchantLocation
    {
        return (new MerchantLocation())
            ->setName('Local '.$slug)
            ->setSlug($slug)
            ->setSourceType($sourceType)
            ->setExternalSourceKey($externalSourceKey)
            ->setPrimaryCategory($category);
    }

    private function category(): LocationCategory
    {
        return (new LocationCategory())
            ->setName('Taquerias')
            ->setSlug('taquerias');
    }

    private function locationMedia(string $url, bool $primary = false): LocationMediaItem
    {
        return (new LocationMediaItem())
            ->setMediaType(LocationMediaItem::TYPE_PHOTO)
            ->setUrl($url)
            ->setTitle('Foto propia')
            ->setAltText('Foto propia')
            ->setIsPrimary($primary)
            ->setIsActive(true)
            ->setModerationStatus(LocationMediaItem::MODERATION_APPROVED);
    }
}
