<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\LocationCategory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogMediaControllerTest extends WebTestCase
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
            $metadataFactory->getMetadataFor(CatalogMediaAsset::class),
        ]);

        $admin = (new AdminUser())
            ->setEmail('catalog-media-admin@example.test')
            ->setFullName('Catalog Media Admin')
            ->setPasswordHash('not-used')
            ->setRoleKey('operator');

        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin);
    }

    public function testIndexRequiresAdminLogin(): void
    {
        static::ensureKernelShutdown();
        $anonymousClient = static::createClient();
        $anonymousClient->request('GET', '/catalog-media');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testIndexRendersAndMenuContainsCatalogMediaLink(): void
    {
        $this->asset('Cover activo');

        $this->client->request('GET', '/catalog-media');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Biblioteca de medios');
        self::assertSelectorTextContains('body', 'Cover activo');
        self::assertSelectorTextContains('body', 'Subir archivos');
        self::assertSelectorExists('[data-controller="catalog-media-upload"]');
        self::assertStringContainsString('/catalog-media', (string) $this->client->getResponse()->getContent());
    }

    public function testNewRenders(): void
    {
        $this->client->request('GET', '/catalog-media/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Agregar medio');
    }

    public function testCreateValidAsset(): void
    {
        $crawler = $this->client->request('GET', '/catalog-media/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/catalog-media/new', $this->payload(['_token' => $token]));

        self::assertResponseRedirects();
        self::assertSame(1, $this->entityManager->getRepository(CatalogMediaAsset::class)->count([]));
    }

    public function testUploadPrepareRequiresAdminLogin(): void
    {
        static::ensureKernelShutdown();
        $anonymousClient = static::createClient();
        $anonymousClient->request('POST', '/catalog-media/uploads/prepare', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testUploadPrepareRequiresCsrf(): void
    {
        $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->preparePayload(), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUploadPrepareAcceptsValidImageAndCreatesUploadingAsset(): void
    {
        $token = $this->uploadToken();

        $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], json_encode($this->preparePayload([
            'original_filename' => 'rodrigo@example.com menu.jpg',
            'mime_type' => 'image/webp; codecs=lossless',
            'media_type' => CatalogMediaType::IMAGE,
        ]), JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('upload_url', $json);
        self::assertArrayHasKey('upload_headers', $json);
        self::assertArrayHasKey('asset_uuid', $json);
        self::assertArrayNotHasKey('bucket', $json);
        self::assertArrayNotHasKey('object_key', $json);

        $asset = $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $json['asset_uuid']]);
        self::assertInstanceOf(CatalogMediaAsset::class, $asset);
        self::assertSame(CatalogMediaStatus::UPLOADING, $asset->getStatus());
        self::assertSame(CatalogMediaModerationStatus::APPROVED, $asset->getModerationStatus());
        self::assertSame('image/webp', $asset->getMimeType());
        self::assertStringStartsWith('catalog-media/general/images/', $asset->getObjectKey());
        self::assertStringNotContainsString('rodrigo', $asset->getObjectKey());
        self::assertStringNotContainsString('@', $asset->getObjectKey());
        self::assertMatchesRegularExpression('#/[0-9a-f-]{36}/original\.webp$#', $asset->getObjectKey());
    }

    public function testUploadPrepareAcceptsValidVideo(): void
    {
        $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode($this->preparePayload([
            'mime_type' => 'video/mp4;codecs=h264',
            'media_type' => CatalogMediaType::VIDEO,
            'file_size' => 4096,
        ]), JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        $asset = $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $json['asset_uuid']]);
        self::assertInstanceOf(CatalogMediaAsset::class, $asset);
        self::assertSame('video/mp4', $asset->getMimeType());
        self::assertStringStartsWith('catalog-media/general/videos/', $asset->getObjectKey());
    }

    public function testUploadPrepareRejectsHtmlAndUnsafeSvg(): void
    {
        foreach (['text/html', 'image/svg+xml'] as $mimeType) {
            $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
            ], json_encode($this->preparePayload(['mime_type' => $mimeType]), JSON_THROW_ON_ERROR));

            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString('Tipo de archivo no permitido.', (string) $this->client->getResponse()->getContent());
        }
    }

    public function testUploadPrepareSanitizesCategorySlugForFutureContext(): void
    {
        $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode($this->preparePayload([
            'category_slug' => 'taquerias',
            'usage_slot' => 'category_cover',
        ]), JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        $asset = $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $json['asset_uuid']]);
        self::assertInstanceOf(CatalogMediaAsset::class, $asset);
        self::assertStringStartsWith('categories/taquerias/cover/', $asset->getObjectKey());
    }

    public function testUploadPrepareRejectsUnsafeCategorySlug(): void
    {
        $this->client->request('POST', '/catalog-media/uploads/prepare', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode($this->preparePayload([
            'category_slug' => '../claim',
            'usage_slot' => 'category_cover',
        ]), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertContains('El slug de categoría no es seguro.', $json['errors']);
    }

    public function testUploadCompleteRequiresEtagAndDoesNotActivate(): void
    {
        $asset = $this->asset('Upload pendiente', rightsVerified: false, moderationStatus: CatalogMediaModerationStatus::PENDING, status: CatalogMediaStatus::UPLOADING);
        $asset->setChecksum(null);
        $this->entityManager->flush();

        $this->client->request('POST', sprintf('/catalog-media/uploads/%s/complete', $asset->getUuid()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode([
            'etag' => 'abc123',
            'uploaded_size' => 1200,
            'uploaded_mime_type' => 'image/jpeg',
            'client_finished_at' => '2026-07-10T00:00:00Z',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $asset->getUuid()]);
        self::assertInstanceOf(CatalogMediaAsset::class, $updated);
        self::assertSame('abc123', $updated->getChecksum());
        self::assertSame(CatalogMediaStatus::UPLOADING, $updated->getStatus());
        self::assertFalse($updated->isRightsVerified());
    }

    public function testUploadCompleteIsIdempotentWithSameEtagAndRejectsDifferentEtag(): void
    {
        $asset = $this->asset('Idempotente', status: CatalogMediaStatus::UPLOADING);
        $asset->setChecksum('etag-ok');
        $this->entityManager->flush();

        $this->client->request('POST', sprintf('/catalog-media/uploads/%s/complete', $asset->getUuid()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode([
            'etag' => 'etag-ok',
            'uploaded_size' => 1200,
            'uploaded_mime_type' => 'image/jpeg',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        $this->client->request('POST', sprintf('/catalog-media/uploads/%s/complete', $asset->getUuid()), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $this->uploadToken(),
        ], json_encode([
            'etag' => 'etag-other',
            'uploaded_size' => 1200,
            'uploaded_mime_type' => 'image/jpeg',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(409);
    }

    public function testNewFormDoesNotExposeManualObjectKeyOrPublicUrlInputs(): void
    {
        $this->client->request('GET', '/catalog-media/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="object_key"]');
        self::assertSelectorNotExists('input[name="public_url"]');
    }

    public function testCreateWithoutTitleFails(): void
    {
        $crawler = $this->client->request('GET', '/catalog-media/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/catalog-media/new', $this->payload([
            '_token' => $token,
            'title' => '',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'El título es obligatorio.');
        self::assertSame(0, $this->entityManager->getRepository(CatalogMediaAsset::class)->count([]));
    }

    public function testCreateWithoutRightsDoesNotActivate(): void
    {
        $crawler = $this->client->request('GET', '/catalog-media/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/catalog-media/new', $this->payload([
            '_token' => $token,
            'rights_verified' => '0',
        ]));

        self::assertResponseRedirects();
        $asset = $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['title' => 'Foto de tacos']);
        self::assertInstanceOf(CatalogMediaAsset::class, $asset);
        self::assertSame(CatalogMediaStatus::UPLOADING, $asset->getStatus());
        self::assertSame(CatalogMediaModerationStatus::APPROVED, $asset->getModerationStatus());
    }

    public function testFormDoesNotShowModerationSelector(): void
    {
        $this->client->request('GET', '/catalog-media/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="moderation_status"]');
        self::assertSelectorNotExists('select[name="status"]');
        self::assertSelectorTextContains('body', 'Moderación: aprobada por carga administrativa');
        self::assertSelectorTextContains('body', 'Tengo evidencia de que Mi Monchis puede utilizar este archivo.');
    }

    public function testEditUpdatesMetadata(): void
    {
        $asset = $this->asset('Antes');
        $crawler = $this->client->request('GET', sprintf('/catalog-media/%d/edit', $asset->getId()));
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', sprintf('/catalog-media/%d/edit', $asset->getId()), $this->payload([
            '_token' => $token,
            'title' => 'Después',
            'license_name' => 'Licencia propia',
        ]));

        self::assertResponseRedirects(sprintf('/catalog-media/%d', $asset->getId()));
        $this->entityManager->clear();
        $updated = $this->entityManager->find(CatalogMediaAsset::class, $asset->getId());
        self::assertSame('Después', $updated?->getTitle());
        self::assertSame('Licencia propia', $updated?->getLicenseName());
        self::assertSame(CatalogMediaStatus::ACTIVE, $updated?->getStatus());
    }

    public function testStockWithoutLicenseSavesButDoesNotActivate(): void
    {
        $asset = $this->asset('Stock sin licencia', status: CatalogMediaStatus::UPLOADING);
        $crawler = $this->client->request('GET', sprintf('/catalog-media/%d/edit', $asset->getId()));
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', sprintf('/catalog-media/%d/edit', $asset->getId()), $this->payload([
            '_token' => $token,
            'source_type' => CatalogMediaSourceType::STOCK,
            'source_url' => 'https://example.test/source',
            'license_name' => '',
        ]));

        self::assertResponseRedirects(sprintf('/catalog-media/%d', $asset->getId()));
        $this->entityManager->clear();
        $updated = $this->entityManager->find(CatalogMediaAsset::class, $asset->getId());
        self::assertSame(CatalogMediaStatus::UPLOADING, $updated?->getStatus());
    }

    public function testArchiveChangesStatus(): void
    {
        $asset = $this->asset('Archivable');
        $crawler = $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));
        $token = $crawler->filter(sprintf('form[action="/catalog-media/%d/archive"] input[name="_token"]', $asset->getId()))->attr('value');

        $this->client->request('POST', sprintf('/catalog-media/%d/archive', $asset->getId()), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('/catalog-media/%d', $asset->getId()));
        $this->entityManager->clear();
        self::assertSame(CatalogMediaStatus::ARCHIVED, $this->entityManager->find(CatalogMediaAsset::class, $asset->getId())?->getStatus());
    }

    public function testIncompleteAssetDoesNotShowActivateButton(): void
    {
        $asset = $this->asset('Pendiente', rightsVerified: false, moderationStatus: CatalogMediaModerationStatus::PENDING, status: CatalogMediaStatus::UPLOADING);
        $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(sprintf('form[action="/catalog-media/%d/activate"]', $asset->getId()));
        self::assertSelectorTextContains('body', 'Completa requisitos para activar');
    }

    public function testArchivedShowsReactivateWhenValid(): void
    {
        $asset = $this->asset('Activable', status: CatalogMediaStatus::ARCHIVED);
        $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Reactivar');
    }

    public function testReactivateValidArchivedAsset(): void
    {
        $asset = $this->asset('Activable', status: CatalogMediaStatus::ARCHIVED);
        $crawler = $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));
        $token = $crawler->filter(sprintf('form[action="/catalog-media/%d/activate"] input[name="_token"]', $asset->getId()))->attr('value');
        $this->client->request('POST', sprintf('/catalog-media/%d/activate', $asset->getId()), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('/catalog-media/%d', $asset->getId()));
        $this->entityManager->clear();
        self::assertSame(CatalogMediaStatus::ACTIVE, $this->entityManager->find(CatalogMediaAsset::class, $asset->getId())?->getStatus());
    }

    public function testActivateActiveAssetIsIdempotent(): void
    {
        $asset = $this->asset('Ya activo', status: CatalogMediaStatus::ARCHIVED);
        $crawler = $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));
        $token = $crawler->filter(sprintf('form[action="/catalog-media/%d/activate"] input[name="_token"]', $asset->getId()))->attr('value');

        $this->client->request('POST', sprintf('/catalog-media/%d/activate', $asset->getId()), ['_token' => $token]);
        $this->client->request('POST', sprintf('/catalog-media/%d/activate', $asset->getId()), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('/catalog-media/%d', $asset->getId()));
        $this->entityManager->clear();
        self::assertSame(CatalogMediaStatus::ACTIVE, $this->entityManager->find(CatalogMediaAsset::class, $asset->getId())?->getStatus());
    }

    public function testShowDoesNotExposeSignedUrlOrRawObjectKey(): void
    {
        $asset = $this->asset('Firmado', publicUrl: 'https://assets.example.test/object.jpg?X-Amz-Signature=secret-value');

        $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('X-Amz-Signature', $content);
        self::assertStringNotContainsString('secret-value', $content);
        self::assertStringNotContainsString('catalog-media/originals/2026/07/test/original.jpg', $content);
        self::assertStringContainsString('catalog-media/...inal.jpg', $content);
    }

    public function testImagePreviewRendersPublicImage(): void
    {
        $asset = $this->asset('Imagen');

        $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img[src="https://assets.example.test/catalog-media/originals/2026/07/test/original.jpg"]');
    }

    public function testVideoPreviewRendersControls(): void
    {
        $asset = $this->asset('Video', mediaType: CatalogMediaType::VIDEO, mimeType: 'video/mp4', publicUrl: 'https://assets.example.test/catalog-media/originals/2026/07/test/original.mp4');

        $this->client->request('GET', sprintf('/catalog-media/%d', $asset->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('video[controls]');
    }

    public function testLocationCategoryLegacyFieldsRemainAvailable(): void
    {
        $contents = file_get_contents(__DIR__.'/../../../src/Entity/Core/LocationCategory.php');
        self::assertIsString($contents);
        self::assertStringContainsString('private ?string $iconAssetUrl', $contents);
        self::assertStringContainsString('private ?string $defaultPhotoUrl', $contents);
        self::assertStringContainsString('private ?string $coverPhotoUrl', $contents);
    }

    /**
     * @param array<string, string|int> $overrides
     * @return array<string, string|int>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Foto de tacos',
            'alt_text' => 'Mesa con tacos',
            'description' => 'Asset de prueba',
            'original_filename' => 'tacos.jpg',
            'media_type' => CatalogMediaType::IMAGE,
            'mime_type' => 'image/jpeg',
            'bytes' => 1200,
            'width' => 1200,
            'height' => 900,
            'duration_seconds' => '',
            'storage_provider' => 'local_catalog_media',
            'object_key' => 'catalog-media/originals/2026/07/test/original.jpg',
            'public_url' => 'https://assets.example.test/catalog-media/originals/2026/07/test/original.jpg',
            'source_type' => CatalogMediaSourceType::OWN,
            'source_url' => 'https://example.test/source',
            'author' => 'Equipo Mi Monchis',
            'license_name' => 'Propia',
            'attribution' => 'Mi Monchis',
            'rights_verified' => '1',
            'ai_generated' => '0',
            'ai_tool' => '',
            'status' => CatalogMediaStatus::ACTIVE,
            'moderation_status' => CatalogMediaModerationStatus::APPROVED,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function preparePayload(array $overrides = []): array
    {
        return array_replace([
            'original_filename' => 'menu.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'media_type' => CatalogMediaType::IMAGE,
            'title' => 'Menu upload',
            'alt_text' => 'Menu del local',
            'source_type' => CatalogMediaSourceType::OWN,
        ], $overrides);
    }

    private function uploadToken(): string
    {
        $crawler = $this->client->request('GET', '/catalog-media');

        return $crawler->filter('[data-catalog-media-upload-csrf-token-value]')->attr('data-catalog-media-upload-csrf-token-value');
    }

    private function asset(
        string $title,
        string $mediaType = CatalogMediaType::IMAGE,
        string $mimeType = 'image/jpeg',
        string $publicUrl = 'https://assets.example.test/catalog-media/originals/2026/07/test/original.jpg',
        bool $rightsVerified = true,
        string $moderationStatus = CatalogMediaModerationStatus::APPROVED,
        string $status = CatalogMediaStatus::ACTIVE,
    ): CatalogMediaAsset {
        $asset = (new CatalogMediaAsset())
            ->setTitle($title)
            ->setAltText('Alt '.$title)
            ->setOriginalFilename('asset.jpg')
            ->setMediaType($mediaType)
            ->setMimeType($mimeType)
            ->setBytes(1200)
            ->setDimensions(1200, 900)
            ->setStorageProvider('local_catalog_media')
            ->setObjectKey('catalog-media/originals/2026/07/test/original.jpg')
            ->setPublicUrl($publicUrl)
            ->setChecksum('etag-'.$title)
            ->setSourceType(CatalogMediaSourceType::OWN)
            ->setRightsVerified($rightsVerified)
            ->setModerationStatus($moderationStatus);

        if ($status === CatalogMediaStatus::ACTIVE && !$rightsVerified) {
            $asset->setStatus(CatalogMediaStatus::UPLOADING);
        } else {
            $asset->setStatus($status);
        }

        $this->entityManager->persist($asset);
        $this->entityManager->flush();

        return $asset;
    }
}
