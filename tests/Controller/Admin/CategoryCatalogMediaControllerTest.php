<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\CatalogMediaCategoryAssignment;
use App\Entity\Core\CatalogMediaUsagePool;
use App\Entity\Core\EventLog;
use App\Entity\Core\LocationCategory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CategoryCatalogMediaControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $metadataFactory = $this->entityManager->getMetadataFactory();
        (new SchemaTool($this->entityManager))->createSchema([
            $metadataFactory->getMetadataFor(AdminUser::class),
            $metadataFactory->getMetadataFor(EventLog::class),
            $metadataFactory->getMetadataFor(LocationCategory::class),
            $metadataFactory->getMetadataFor(CatalogMediaAsset::class),
            $metadataFactory->getMetadataFor(CatalogMediaUsagePool::class),
            $metadataFactory->getMetadataFor(CatalogMediaCategoryAssignment::class),
        ]);

        $admin = (new AdminUser())
            ->setEmail('category-media-admin@example.test')
            ->setFullName('Category Media Admin')
            ->setPasswordHash('not-used')
            ->setRoleKey('operator');

        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin);
    }

    public function testCategoryFormShowsCatalogMediaPickerAndNoLocalUploadInputs(): void
    {
        $this->client->request('GET', '/categories/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="catalog-media-picker"]');
        self::assertSelectorTextContains('body', 'Biblioteca');
        self::assertSelectorTextContains('body', 'Elegir de Biblioteca');
        self::assertSelectorTextContains('body', 'Subir nuevo');
        self::assertSelectorTextContains('body', 'Los medios seleccionados se servirán desde assets.mimonchis.mx.');
        self::assertSelectorNotExists('input[name="default_photo_file"]');
        self::assertSelectorNotExists('input[name="cover_photo_file"]');
        self::assertSelectorNotExists('input[name="default_photo_url"]');
        self::assertSelectorNotExists('input[name="cover_photo_url"]');
    }

    public function testStimulusBootstrapRegistersCatalogMediaControllers(): void
    {
        $contents = file_get_contents(__DIR__.'/../../../assets/stimulus_bootstrap.js');

        self::assertIsString($contents);
        self::assertStringContainsString("app.register('catalog-media-picker'", $contents);
        self::assertStringContainsString("app.register('catalog-media-upload'", $contents);
    }

    public function testCategoryIconStoresAssignmentAndProjectsLegacyField(): void
    {
        $asset = $this->asset('Icono tacos', CatalogMediaType::IMAGE, 'categories/taquerias/icon');

        $this->client->request('POST', '/categories/new', $this->categoryPayload([
            'catalog_media_singular' => [CatalogMediaUsageSlot::CATEGORY_ICON => $asset->getUuid()],
            'catalog_media_singular_touched' => [CatalogMediaUsageSlot::CATEGORY_ICON => '1'],
        ]));

        self::assertResponseRedirects('/categories');
        $category = $this->entityManager->getRepository(LocationCategory::class)->findOneBy(['slug' => 'taquerias']);
        self::assertInstanceOf(LocationCategory::class, $category);
        self::assertSame($asset->getPublicUrl(), $category->getIconAssetUrl());
        self::assertStringStartsWith('https://assets.mimonchis.mx/', (string) $category->getIconAssetUrl());
        self::assertSame(1, $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)->count([
            'category' => $category,
            'usageSlot' => CatalogMediaUsageSlot::CATEGORY_ICON,
            'active' => true,
        ]));
    }

    public function testCategoryDefaultAndCoverProjectLegacyFields(): void
    {
        $default = $this->asset('Default tacos', CatalogMediaType::IMAGE, 'categories/taquerias/default');
        $cover = $this->asset('Cover tacos', CatalogMediaType::IMAGE, 'categories/taquerias/cover');

        $this->client->request('POST', '/categories/new', $this->categoryPayload([
            'catalog_media_singular' => [
                CatalogMediaUsageSlot::CATEGORY_DEFAULT => $default->getUuid(),
                CatalogMediaUsageSlot::CATEGORY_COVER => $cover->getUuid(),
            ],
            'catalog_media_singular_touched' => [
                CatalogMediaUsageSlot::CATEGORY_DEFAULT => '1',
                CatalogMediaUsageSlot::CATEGORY_COVER => '1',
            ],
        ]));

        self::assertResponseRedirects('/categories');
        $category = $this->entityManager->getRepository(LocationCategory::class)->findOneBy(['slug' => 'taquerias']);
        self::assertInstanceOf(LocationCategory::class, $category);
        self::assertSame($default->getPublicUrl(), $category->getDefaultPhotoUrl());
        self::assertSame($cover->getPublicUrl(), $category->getCoverPhotoUrl());
    }

    public function testReplacingSingularAssignmentDeactivatesPreviousAssignment(): void
    {
        $category = $this->category();
        $first = $this->asset('Cover viejo', CatalogMediaType::IMAGE, 'categories/taquerias/cover');
        $second = $this->asset('Cover nuevo', CatalogMediaType::IMAGE, 'categories/taquerias/cover');

        $this->postEdit($category, [
            'catalog_media_singular' => [CatalogMediaUsageSlot::CATEGORY_COVER => $first->getUuid()],
            'catalog_media_singular_touched' => [CatalogMediaUsageSlot::CATEGORY_COVER => '1'],
        ]);
        self::assertResponseRedirects('/categories');

        $this->postEdit($category, [
            'catalog_media_singular' => [CatalogMediaUsageSlot::CATEGORY_COVER => $second->getUuid()],
            'catalog_media_singular_touched' => [CatalogMediaUsageSlot::CATEGORY_COVER => '1'],
        ]);
        self::assertResponseRedirects('/categories');

        $this->entityManager->clear();
        $updated = $this->entityManager->find(LocationCategory::class, $category->getId());
        self::assertSame($second->getPublicUrl(), $updated?->getCoverPhotoUrl());
        self::assertSame(1, $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)->count([
            'category' => $updated,
            'usageSlot' => CatalogMediaUsageSlot::CATEGORY_COVER,
            'active' => true,
        ]));
        self::assertSame(1, $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)->count([
            'category' => $updated,
            'usageSlot' => CatalogMediaUsageSlot::CATEGORY_COVER,
            'active' => false,
        ]));
    }

    public function testPoolLocationGalleryAcceptsMultipleAssets(): void
    {
        $category = $this->category();
        $first = $this->asset('Galeria uno', CatalogMediaType::IMAGE, 'categories/taquerias/gallery');
        $second = $this->asset('Galeria dos', CatalogMediaType::IMAGE, 'categories/taquerias/gallery');

        $this->postEdit($category, [
            'catalog_media_pools' => [CatalogMediaUsageSlot::LOCATION_GALLERY => $first->getUuid().','.$second->getUuid()],
            'catalog_media_pool_touched' => [CatalogMediaUsageSlot::LOCATION_GALLERY => '1'],
        ]);

        self::assertResponseRedirects('/categories');
        self::assertSame(2, $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)->count([
            'category' => $category,
            'usageSlot' => CatalogMediaUsageSlot::LOCATION_GALLERY,
            'active' => true,
        ]));
        self::assertSame(1, $this->entityManager->getRepository(CatalogMediaUsagePool::class)->count([]));
    }

    public function testStoryImageRejectsVideoAndStoryVideoRejectsImage(): void
    {
        $category = $this->category();
        $video = $this->asset('Story video', CatalogMediaType::VIDEO, 'categories/taquerias/stories/videos', 'video/mp4', 'mp4');
        $image = $this->asset('Story image', CatalogMediaType::IMAGE, 'categories/taquerias/stories/images');

        $this->postEdit($category, [
            'catalog_media_pools' => [CatalogMediaUsageSlot::STORY_IMAGE => $video->getUuid()],
            'catalog_media_pool_touched' => [CatalogMediaUsageSlot::STORY_IMAGE => '1'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'el tipo de medio no es compatible con el slot');

        $this->postEdit($category, [
            'catalog_media_pools' => [CatalogMediaUsageSlot::STORY_VIDEO => $image->getUuid()],
            'catalog_media_pool_touched' => [CatalogMediaUsageSlot::STORY_VIDEO => '1'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'el tipo de medio no es compatible con el slot');
    }

    public function testProjectionRejectsLegacyOrNonAssetsHostUrls(): void
    {
        $category = $this->category();
        $asset = $this->asset('Legacy host', CatalogMediaType::IMAGE, 'catalog-media/general/images', publicUrl: 'https://admin.mimonchis.mx/media/categories/1/cover.jpg');

        $this->postEdit($category, [
            'catalog_media_singular' => [CatalogMediaUsageSlot::CATEGORY_COVER => $asset->getUuid()],
            'catalog_media_singular_touched' => [CatalogMediaUsageSlot::CATEGORY_COVER => '1'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'la URL pública debe usar assets.mimonchis.mx');
    }

    public function testLegacyFieldsRemainAndFeedStillReadsThem(): void
    {
        $categoryContents = file_get_contents(__DIR__.'/../../../src/Entity/Core/LocationCategory.php');
        $feedContents = file_get_contents(__DIR__.'/../../../src/Controller/Api/Core/LocationFeedController.php');

        self::assertIsString($categoryContents);
        self::assertIsString($feedContents);
        self::assertStringContainsString('private ?string $iconAssetUrl', $categoryContents);
        self::assertStringContainsString('private ?string $defaultPhotoUrl', $categoryContents);
        self::assertStringContainsString('private ?string $coverPhotoUrl', $categoryContents);
        self::assertStringContainsString('category_icon_asset_url', $feedContents);
        self::assertStringContainsString('getIconAssetUrl()', $feedContents);
        self::assertStringContainsString('getDefaultPhotoUrl()', $feedContents);
        self::assertStringContainsString('getCoverPhotoUrl()', $feedContents);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function categoryPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Taquerias',
            'slug' => 'taquerias',
            'icon_key' => 'taco',
            'color_hex' => '#F97316',
            'sort_order' => '10',
            'regional_strategy' => '',
            'featured_region_scope' => '',
            'google_place_type_mappings' => '',
            'is_active' => '1',
            'catalog_media_singular' => [],
            'catalog_media_singular_touched' => [],
            'catalog_media_pools' => [],
            'catalog_media_pool_touched' => [],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function postEdit(LocationCategory $category, array $overrides = []): void
    {
        $this->client->request('POST', sprintf('/categories/%d/edit', $category->getId()), $this->categoryPayload($overrides));
    }

    private function category(): LocationCategory
    {
        $category = (new LocationCategory())
            ->setName('Taquerias')
            ->setSlug('taquerias')
            ->setIconKey('taco')
            ->setColorHex('#F97316');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function asset(
        string $title,
        string $mediaType,
        string $prefix,
        string $mimeType = 'image/webp',
        string $extension = 'webp',
        ?string $publicUrl = null,
    ): CatalogMediaAsset {
        $uuid = sprintf('00000000-0000-4000-8000-%012d', random_int(1, 999999999999));
        $objectKey = sprintf('%s/%s/original.%s', $prefix, $uuid, $extension);
        $asset = (new CatalogMediaAsset($uuid))
            ->setTitle($title)
            ->setAltText('Alt '.$title)
            ->setOriginalFilename('original.'.$extension)
            ->setMediaType($mediaType)
            ->setMimeType($mimeType)
            ->setBytes(2048)
            ->setDimensions($mediaType === CatalogMediaType::IMAGE ? 1200 : null, $mediaType === CatalogMediaType::IMAGE ? 900 : null)
            ->setDurationSeconds($mediaType === CatalogMediaType::VIDEO ? 12 : null)
            ->setStorageProvider('cloudflare_r2_catalog_media')
            ->setObjectKey($objectKey)
            ->setPublicUrl($publicUrl ?? 'https://assets.mimonchis.mx/'.$objectKey)
            ->setSourceType(CatalogMediaSourceType::OWN)
            ->setChecksum('etag-'.$uuid)
            ->setRightsVerified(true)
            ->setModerationStatus(CatalogMediaModerationStatus::APPROVED)
            ->setStatus(CatalogMediaStatus::ACTIVE);

        $this->entityManager->persist($asset);
        $this->entityManager->flush();

        return $asset;
    }
}
