<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaObjectMetadata;
use App\Domain\CatalogMedia\CatalogMediaSourceType;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaStorageInterface;
use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUploadContext;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\CatalogMediaAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/catalog-media', name: 'admin_catalog_media_')]
final class CatalogMediaController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(int:CATALOG_MEDIA_MAX_IMAGE_BYTES)%')]
        private readonly int $catalogMediaMaxImageBytes = 5242880,
        #[Autowire('%env(int:CATALOG_MEDIA_MAX_VIDEO_BYTES)%')]
        private readonly int $catalogMediaMaxVideoBytes = 52428800,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $filters = [
            'status' => $this->validFilter($request->query->getString('status', ''), CatalogMediaStatus::values()),
            'media_type' => $this->validFilter($request->query->getString('media_type', ''), CatalogMediaType::values()),
            'source_type' => $this->validFilter($request->query->getString('source_type', ''), CatalogMediaSourceType::values()),
            'rights_verified' => $this->validFilter($request->query->getString('rights_verified', ''), ['yes', 'no']),
            'q' => trim($request->query->getString('q', '')),
        ];

        $queryBuilder = $entityManager->getRepository(CatalogMediaAsset::class)
            ->createQueryBuilder('asset')
            ->orderBy('asset.id', 'DESC')
            ->setMaxResults(80);

        if ($filters['status'] !== '') {
            $queryBuilder->andWhere('asset.status = :status')->setParameter('status', $filters['status']);
        }

        if ($filters['media_type'] !== '') {
            $queryBuilder->andWhere('asset.mediaType = :mediaType')->setParameter('mediaType', $filters['media_type']);
        }

        if ($filters['source_type'] !== '') {
            $queryBuilder->andWhere('asset.sourceType = :sourceType')->setParameter('sourceType', $filters['source_type']);
        }

        if ($filters['rights_verified'] !== '') {
            $queryBuilder->andWhere('asset.rightsVerified = :rightsVerified')
                ->setParameter('rightsVerified', $filters['rights_verified'] === 'yes');
        }

        if ($filters['q'] !== '') {
            $queryBuilder
                ->andWhere('LOWER(asset.title) LIKE :query OR LOWER(asset.originalFilename) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($filters['q']).'%');
        }

        $assets = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/catalog_media/index.html.twig', [
            'assets' => $assets,
            'preview_urls' => $this->previewUrls($assets),
            'filters' => $filters,
            'media_types' => CatalogMediaType::values(),
            'statuses' => CatalogMediaStatus::values(),
            'moderation_statuses' => CatalogMediaModerationStatus::values(),
            'source_types' => CatalogMediaSourceType::values(),
        ]);
    }

    #[Route('/uploads/prepare', name: 'upload_prepare', methods: ['POST'])]
    public function prepareUpload(Request $request, EntityManagerInterface $entityManager, CatalogMediaStorageInterface $storage): JsonResponse
    {
        if (!$this->isCatalogMediaUploadCsrfValid($request)) {
            return $this->json(['error' => 'No se pudo validar la solicitud de upload.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->jsonPayload($request);
        $originalFilename = trim((string) ($payload['original_filename'] ?? ''));
        $mimeType = $this->normalizeMimeType((string) ($payload['mime_type'] ?? ''));
        $fileSize = $this->positiveInt($payload['file_size'] ?? null);
        $mediaType = trim((string) ($payload['media_type'] ?? ''));
        $sourceType = trim((string) ($payload['source_type'] ?? CatalogMediaSourceType::UNKNOWN));
        $usageSlot = isset($payload['usage_slot']) && trim((string) $payload['usage_slot']) !== '' ? trim((string) $payload['usage_slot']) : null;
        $categorySlug = isset($payload['category_slug']) && trim((string) $payload['category_slug']) !== '' ? trim((string) $payload['category_slug']) : null;
        $title = trim((string) ($payload['title'] ?? ''));
        $altText = trim((string) ($payload['alt_text'] ?? ''));

        $errors = [];
        if ($originalFilename === '') {
            $errors[] = 'El filename original es obligatorio.';
        }
        if ($fileSize === null) {
            $errors[] = 'El tamaño del archivo es obligatorio y debe ser mayor a 0.';
        }
        if (!in_array($mimeType, $this->acceptedMimeTypes(), true)) {
            $errors[] = 'Tipo de archivo no permitido.';
        }
        if (!in_array($mediaType, CatalogMediaType::values(), true)) {
            $errors[] = 'Selecciona un tipo de medio válido.';
        }
        if (!in_array($sourceType, CatalogMediaSourceType::values(), true)) {
            $errors[] = 'Selecciona un origen válido.';
        }
        if ($usageSlot !== null && !in_array($usageSlot, CatalogMediaUsageSlot::values(), true)) {
            $errors[] = 'Selecciona un slot de uso válido.';
        }
        if ($usageSlot !== null && in_array($mediaType, CatalogMediaType::values(), true) && !CatalogMediaUsageSlot::acceptsMediaType($usageSlot, $mediaType)) {
            $errors[] = 'El tipo de medio no es compatible con el slot seleccionado.';
        }
        if ($categorySlug !== null && !$this->isSafeSlug($categorySlug)) {
            $errors[] = 'El slug de categoría no es seguro.';
        }

        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $asset = (new CatalogMediaAsset())
            ->setTitle($title !== '' ? $title : $originalFilename)
            ->setAltText($altText)
            ->setOriginalFilename($originalFilename)
            ->setMimeType($mimeType)
            ->setMediaType($mediaType)
            ->setBytes($fileSize ?? 0)
            ->setSourceType($sourceType)
            ->setStatus(CatalogMediaStatus::UPLOADING)
            ->setModerationStatus(CatalogMediaModerationStatus::APPROVED);

        $user = $this->getUser();
        if ($user instanceof AdminUser) {
            $asset->setCreatedBy($user);
        }

        $intent = $storage->createUploadIntent(
            $originalFilename,
            $mimeType,
            $fileSize ?? 0,
            new CatalogMediaUploadContext($mediaType, $usageSlot, $categorySlug, $asset->getUuid())
        );

        $asset
            ->setStorageProvider($intent->storageProvider)
            ->setLogicalBucket($intent->logicalBucket)
            ->setObjectKey($intent->objectKey)
            ->setPublicUrl($storage->publicUrlFor($intent->objectKey));

        $entityManager->persist($asset);
        $entityManager->flush();

        return $this->json([
            'upload_url' => $intent->uploadUrl,
            'upload_headers' => $intent->requiredHeaders,
            'asset_uuid' => $asset->getUuid(),
            'expires_in_seconds' => max(0, $intent->expiresAt->getTimestamp() - time()),
            'accepted_mime_types' => $this->acceptedMimeTypes(),
            'max_bytes' => [
                'image' => $this->maxBytesFor(CatalogMediaType::IMAGE),
                'video' => $this->maxBytesFor(CatalogMediaType::VIDEO),
            ],
        ]);
    }

    #[Route('/uploads/{assetUuid}/complete', name: 'upload_complete', methods: ['POST'], requirements: ['assetUuid' => '[0-9a-fA-F-]{36}'])]
    public function completeUpload(string $assetUuid, Request $request, EntityManagerInterface $entityManager, CatalogMediaStorageInterface $storage): JsonResponse
    {
        if (!$this->isCatalogMediaUploadCsrfValid($request)) {
            return $this->json(['error' => 'No se pudo validar la confirmación de upload.'], Response::HTTP_FORBIDDEN);
        }

        $asset = $entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $assetUuid]);
        if (!$asset instanceof CatalogMediaAsset) {
            return $this->json(['error' => 'No se encontró el asset de catálogo.'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->jsonPayload($request);
        $etag = $this->normalizeEtag((string) ($payload['etag'] ?? ''));
        if ($etag === '') {
            return $this->json(['error' => 'ETag requerido para confirmar el upload.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($asset->getChecksum() !== null && $asset->getChecksum() !== $etag) {
            return $this->json(['error' => 'El ETag no coincide con la confirmación previa.'], Response::HTTP_CONFLICT);
        }

        if ($asset->getStatus() !== CatalogMediaStatus::UPLOADING && $asset->getChecksum() === null) {
            return $this->json(['error' => 'El asset no está esperando confirmación de upload.'], Response::HTTP_CONFLICT);
        }

        $uploadedSize = $this->positiveInt($payload['uploaded_size'] ?? null) ?? $asset->getBytes();
        $uploadedMimeType = $this->normalizeMimeType((string) ($payload['uploaded_mime_type'] ?? $asset->getMimeType()));
        if ($uploadedMimeType !== $asset->getMimeType()) {
            return $this->json(['error' => 'El MIME subido no coincide con el asset preparado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $uploadedObject = $storage->confirmUploadedObject(
            $asset->getObjectKey(),
            new CatalogMediaObjectMetadata($asset->getObjectKey(), $uploadedMimeType, $uploadedSize, $etag)
        );

        if ($uploadedObject->metadata->checksum !== null && $uploadedObject->metadata->checksum !== $etag) {
            return $this->json(['error' => 'El ETag confirmado por storage no coincide.'], Response::HTTP_CONFLICT);
        }

        $asset
            ->setChecksum($etag)
            ->setBytes($uploadedObject->metadata->sizeBytes)
            ->setMimeType($uploadedObject->metadata->mimeType)
            ->setPublicUrl($uploadedObject->publicUrl)
            ->setStatus(CatalogMediaStatus::UPLOADING);
        $entityManager->flush();

        return $this->json([
            'asset_uuid' => $asset->getUuid(),
            'title' => $asset->getTitle(),
            'media_type' => $asset->getMediaType(),
            'mime_type' => $asset->getMimeType(),
            'status' => $asset->getStatus(),
            'public_url' => $this->safePublicUrl($asset->getPublicUrl()),
            'preview_url' => $this->safePublicUrl($asset->getPublicUrl()),
            'edit_url' => $this->generateUrl('admin_catalog_media_edit', ['id' => $asset->getId()]),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $asset = new CatalogMediaAsset();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_catalog_media_asset', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'No se pudo validar la solicitud para crear el medio.');

                return $this->redirectToRoute('admin_catalog_media_new');
            }

            $errors = $this->hydrateFromRequest($request, $asset);
            if ($errors === []) {
                $user = $this->getUser();
                if ($user instanceof AdminUser) {
                    $asset->setCreatedBy($user);
                }

                $entityManager->persist($asset);
                $entityManager->flush();
                $this->addFlash('success', 'Medio agregado a la biblioteca.');

                return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
            }

            return $this->render('admin/catalog_media/form.html.twig', $this->formViewData($asset, $errors, false), new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/catalog_media/form.html.twig', $this->formViewData($asset, [], false));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(CatalogMediaAsset $asset): Response
    {
        return $this->render('admin/catalog_media/show.html.twig', [
            'asset' => $asset,
            'masked_object_key' => $this->maskedObjectKey($asset->getObjectKey()),
            'safe_public_url' => $this->safePublicUrl($asset->getPublicUrl()),
            'activation_requirements' => $this->activationRequirements($asset),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(CatalogMediaAsset $asset, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(sprintf('edit_catalog_media_asset_%d', $asset->getId()), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'No se pudo validar la solicitud para editar el medio.');

                return $this->redirectToRoute('admin_catalog_media_edit', ['id' => $asset->getId()]);
            }

            $errors = $this->hydrateFromRequest($request, $asset);
            if ($errors === []) {
                $entityManager->flush();
                if ($asset->getStatus() === CatalogMediaStatus::ACTIVE) {
                    $this->addFlash('success', 'Cambios guardados. El archivo está activo.');
                } else {
                    $this->addFlash('info', 'Cambios guardados. Revisa los requisitos faltantes para activar el archivo.');
                }

                return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
            }

            return $this->render('admin/catalog_media/form.html.twig', $this->formViewData($asset, $errors, true), new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/catalog_media/form.html.twig', $this->formViewData($asset, [], true));
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(CatalogMediaAsset $asset, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('archive_catalog_media_asset_%d', $asset->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para archivar el medio.');

            return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
        }

        $asset->setStatus(CatalogMediaStatus::ARCHIVED);
        $entityManager->flush();
        $this->addFlash('success', 'Medio archivado. No se borró el objeto físico.');

        return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(CatalogMediaAsset $asset, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('activate_catalog_media_asset_%d', $asset->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para activar el medio.');

            return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
        }

        if ($asset->getStatus() === CatalogMediaStatus::ACTIVE) {
            $this->addFlash('info', 'El archivo ya estaba activo.');

            return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
        }

        $missingRequirements = $this->activationRequirements($asset);
        if ($missingRequirements !== []) {
            foreach ($missingRequirements as $requirement) {
                $this->addFlash('error', $requirement);
            }

            return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
        }

        $asset->setStatus(CatalogMediaStatus::ACTIVE);
        $entityManager->flush();
        $this->addFlash('success', 'Medio activado.');

        return $this->redirectToRoute('admin_catalog_media_show', ['id' => $asset->getId()]);
    }

    /**
     * @return list<string>
     */
    private function hydrateFromRequest(Request $request, CatalogMediaAsset $asset): array
    {
        $errors = [];
        $title = trim($request->request->getString('title', ''));
        $altText = trim($request->request->getString('alt_text', ''));
        $mediaType = $request->request->getString('media_type', CatalogMediaType::IMAGE);
        $mimeType = trim($request->request->getString('mime_type', ''));
        $bytes = $this->nullableInt($request->request->get('bytes'));
        $sourceType = $request->request->getString('source_type', CatalogMediaSourceType::UNKNOWN);
        $objectKey = trim($request->request->getString('object_key', $asset->getObjectKey()));
        $publicUrl = trim($request->request->getString('public_url', $asset->getPublicUrl() ?? ''));
        $sourceUrl = trim($request->request->getString('source_url', ''));
        $rightsVerified = $request->request->getBoolean('rights_verified', false);

        if ($title === '') {
            $errors[] = 'El título es obligatorio.';
        }
        $isEdit = $asset->getId() !== null;
        if ($objectKey === '' && $isEdit) {
            $errors[] = 'El object_key confirmado por storage es obligatorio.';
        }
        if ($mimeType === '') {
            $errors[] = 'El MIME es obligatorio.';
        }
        if ($bytes === null || $bytes < 0) {
            $errors[] = 'El tamaño en bytes debe ser mayor o igual a 0.';
        }
        if (!in_array($mediaType, CatalogMediaType::values(), true)) {
            $errors[] = 'Selecciona un tipo de medio válido.';
        }
        if (!in_array($sourceType, CatalogMediaSourceType::values(), true)) {
            $errors[] = 'Selecciona un origen válido.';
        }
        if ($publicUrl !== '' && !$this->isSafeHttpUrl($publicUrl)) {
            $errors[] = 'La URL pública debe ser http/https y no puede ser una URL firmada.';
        }
        if ($sourceUrl !== '' && !$this->isSafeHttpUrl($sourceUrl, false)) {
            $errors[] = 'La URL de origen debe ser http/https válida.';
        }

        $width = $this->nullableInt($request->request->get('width'));
        $height = $this->nullableInt($request->request->get('height'));
        $duration = $this->nullableInt($request->request->get('duration_seconds'));
        if (($width !== null && $width < 0) || ($height !== null && $height < 0)) {
            $errors[] = 'Ancho y alto deben ser mayores o iguales a 0.';
        }
        if ($duration !== null && $duration < 0) {
            $errors[] = 'La duración debe ser mayor o igual a 0.';
        }
        if ($errors !== []) {
            return $errors;
        }

        try {
            $asset
                ->setTitle($title)
                ->setAltText($altText)
                ->setDescription($request->request->getString('description', ''))
                ->setOriginalFilename($request->request->getString('original_filename', ''))
                ->setStorageProvider($request->request->getString('storage_provider', $asset->getStorageProvider() ?: 'local_catalog_media'))
                ->setPublicUrl($publicUrl !== '' ? $publicUrl : null)
                ->setMimeType($mimeType)
                ->setMediaType($mediaType)
                ->setDimensions($width, $height)
                ->setDurationSeconds($duration)
                ->setBytes($bytes ?? 0)
                ->setSourceType($sourceType)
                ->setSourceUrl($sourceUrl !== '' ? $sourceUrl : null)
                ->setAuthor($request->request->getString('author', ''))
                ->setLicenseName($request->request->getString('license_name', ''))
                ->setAttribution($request->request->getString('attribution', ''))
                ->setRightsVerified($rightsVerified)
                ->setAiGenerated($request->request->getBoolean('ai_generated', false))
                ->setAiTool($request->request->getString('ai_tool', ''))
                ->setModerationStatus(CatalogMediaModerationStatus::APPROVED);
            if ($objectKey !== '') {
                $asset->setObjectKey($objectKey);
            }
            $asset->setStatus($this->statusAfterMetadataSave($asset));
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function activationRequirements(CatalogMediaAsset $asset): array
    {
        $requirements = [];
        if (trim($asset->getTitle()) === '') {
            $requirements[] = 'Captura un título.';
        }
        if (!$asset->isRightsVerified()) {
            $requirements[] = 'Marca derechos verificados cuando tengas evidencia de uso.';
        }
        if ($asset->getModerationStatus() !== CatalogMediaModerationStatus::APPROVED) {
            $requirements[] = 'La moderación debe estar aprobada.';
        }
        if (in_array($asset->getMediaType(), [CatalogMediaType::IMAGE, CatalogMediaType::ICON], true) && trim($asset->getAltText()) === '') {
            $requirements[] = 'Captura alt text para imágenes e iconos.';
        }
        if ($asset->getChecksum() === null) {
            $requirements[] = 'Confirma storage: falta checksum/ETag.';
        }
        if ($asset->getObjectKey() === '') {
            $requirements[] = 'Confirma storage: falta object key.';
        }
        if ($asset->getPublicUrl() === null || !$this->isSafeHttpUrl($asset->getPublicUrl())) {
            $requirements[] = 'Confirma una URL pública válida sin firma.';
        }
        if (!in_array($asset->getMimeType(), $this->acceptedMimeTypes(), true)) {
            $requirements[] = 'Usa un MIME permitido.';
        }
        if ($asset->getSourceType() === CatalogMediaSourceType::STOCK) {
            if ($asset->getSourceUrl() === null) {
                $requirements[] = 'Para stock, registra source URL.';
            }
            if ($asset->getLicenseName() === null) {
                $requirements[] = 'Para stock, registra licencia.';
            }
        }
        if (($asset->getSourceType() === CatalogMediaSourceType::AI_GENERATED || $asset->isAiGenerated()) && $asset->getAiTool() === null) {
            $requirements[] = 'Para IA, registra la herramienta o modelo.';
        }

        return $requirements;
    }

    private function statusAfterMetadataSave(CatalogMediaAsset $asset): string
    {
        if ($this->activationRequirements($asset) === []) {
            return CatalogMediaStatus::ACTIVE;
        }

        if (in_array($asset->getStatus(), [CatalogMediaStatus::ARCHIVED, CatalogMediaStatus::FAILED], true)) {
            return $asset->getStatus();
        }

        return CatalogMediaStatus::UPLOADING;
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(CatalogMediaAsset $asset, array $errors, bool $isEdit): array
    {
        return [
            'asset' => $asset,
            'errors' => $errors,
            'is_edit' => $isEdit,
            'media_types' => CatalogMediaType::values(),
            'source_types' => CatalogMediaSourceType::values(),
            'activation_requirements' => $this->activationRequirements($asset),
        ];
    }

    private function validFilter(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (int) $value : -1;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : $request->request->all();
    }

    private function isCatalogMediaUploadCsrfValid(Request $request): bool
    {
        $token = (string) ($request->headers->get('X-CSRF-TOKEN') ?? '');
        if ($token === '') {
            $payload = $this->jsonPayload($request);
            $token = (string) ($payload['_token'] ?? '');
        }

        return $this->isCsrfTokenValid('catalog_media_upload', $token);
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return mb_strtolower(trim(strtok($mimeType, ';') ?: $mimeType));
    }

    private function normalizeEtag(string $etag): string
    {
        return trim($etag, " \t\n\r\0\x0B\"");
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    /**
     * @return list<string>
     */
    private function acceptedMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'];
    }

    private function maxBytesFor(string $mediaType): int
    {
        return $mediaType === CatalogMediaType::VIDEO ? $this->catalogMediaMaxVideoBytes : $this->catalogMediaMaxImageBytes;
    }

    private function isSafeSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug) === 1;
    }

    private function isSafeHttpUrl(string $url, bool $rejectSigned = true): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (!$rejectSigned) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && !preg_match('/(X-Amz-Signature|X-Amz-Credential|Signature=|Expires=|token=|signed)/i', $url);
    }

    private function maskedObjectKey(string $objectKey): string
    {
        if ($objectKey === '') {
            return 'Sin object key';
        }

        $prefix = strtok($objectKey, '/') ?: 'catalog-media';

        return sprintf('%s/...%s', $prefix, substr($objectKey, -8));
    }

    private function safePublicUrl(?string $publicUrl): ?string
    {
        if ($publicUrl === null || !$this->isSafeHttpUrl($publicUrl)) {
            return null;
        }

        return $publicUrl;
    }

    /**
     * @param list<CatalogMediaAsset> $assets
     * @return array<int, string>
     */
    private function previewUrls(array $assets): array
    {
        $urls = [];
        foreach ($assets as $asset) {
            $url = $this->safePublicUrl($asset->getPublicUrl());
            if ($asset->getId() !== null && $url !== null) {
                $urls[$asset->getId()] = $url;
            }
        }

        return $urls;
    }
}
