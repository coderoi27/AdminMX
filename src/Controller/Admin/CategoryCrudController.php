<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\EventLog;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\MerchantLocation;
use App\Service\CatalogMedia\CatalogMediaCategoryAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories', name: 'admin_categories_')]
final class CategoryCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(LocationCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $locationRepository = $entityManager->getRepository(MerchantLocation::class);

        $usageByCategoryId = [];
        foreach ($categories as $category) {
            $usageByCategoryId[(int) $category->getId()] = $locationRepository->count(['primaryCategory' => $category]);
        }

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
            'usage_by_category_id' => $usageByCategoryId,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CatalogMediaCategoryAssignmentService $catalogMediaAssignments): Response
    {
        $category = new LocationCategory();

        if ($request->isMethod('POST')) {
            $errors = $this->hydrateCategoryFromRequest($request, $entityManager, $category);

            if ($errors === []) {
                $entityManager->persist($category);
                $errors = $catalogMediaAssignments->applySelections(
                    $category,
                    $this->singularSelectionsFromRequest($request),
                    $this->poolSelectionsFromRequest($request),
                    $this->touchedSlotsFromRequest($request, 'catalog_media_singular_touched'),
                    $this->touchedSlotsFromRequest($request, 'catalog_media_pool_touched')
                );
            }

            if ($errors === []) {
                $this->recordAudit($entityManager, 'admin_category_created', $category, ['slug' => $category->getSlug()]);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Categoría "%s" creada correctamente.', $category->getName()));

                return $this->redirectToRoute('admin_categories_index');
            }

            return $this->render('admin/categories/form.html.twig', $this->formViewData($category, $errors, false, $catalogMediaAssignments), new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/categories/form.html.twig', $this->formViewData($category, [], false, $catalogMediaAssignments));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(LocationCategory $category, Request $request, EntityManagerInterface $entityManager, CatalogMediaCategoryAssignmentService $catalogMediaAssignments): Response
    {
        if ($request->isMethod('POST')) {
            $errors = $this->hydrateCategoryFromRequest($request, $entityManager, $category);

            if ($errors === []) {
                $errors = $catalogMediaAssignments->applySelections(
                    $category,
                    $this->singularSelectionsFromRequest($request),
                    $this->poolSelectionsFromRequest($request),
                    $this->touchedSlotsFromRequest($request, 'catalog_media_singular_touched'),
                    $this->touchedSlotsFromRequest($request, 'catalog_media_pool_touched')
                );
            }

            if ($errors === []) {
                $this->recordAudit($entityManager, 'admin_category_updated', $category, ['slug' => $category->getSlug()]);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Categoría "%s" actualizada correctamente.', $category->getName()));

                return $this->redirectToRoute('admin_categories_index');
            }

            return $this->render('admin/categories/form.html.twig', $this->formViewData($category, $errors, true, $catalogMediaAssignments), new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/categories/form.html.twig', $this->formViewData($category, [], true, $catalogMediaAssignments));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(LocationCategory $category, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('toggle_category_%d', $category->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para cambiar el estado de la categoría.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $category->setIsActive(!$category->isActive());
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Categoría "%s" %s.',
            $category->getName(),
            $category->isActive() ? 'activada correctamente' : 'desactivada correctamente'
        ));

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(LocationCategory $category, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('delete_category_%d', $category->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para eliminar la categoría.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('info', 'Categoría eliminada correctamente.');

        return $this->redirectToRoute('admin_categories_index');
    }

    /**
     * @return list<string>
     */
    private function hydrateCategoryFromRequest(
        Request $request,
        EntityManagerInterface $entityManager,
        LocationCategory $category
    ): array {
        $errors = [];

        $name = trim($request->request->getString('name', ''));
        $slug = trim(mb_strtolower($request->request->getString('slug', '')));
        $iconKey = trim($request->request->getString('icon_key', ''));
        $colorHex = strtoupper(trim($request->request->getString('color_hex', '')));
        $regionalStrategy = trim($request->request->getString('regional_strategy', ''));
        $featuredRegionScope = trim($request->request->getString('featured_region_scope', ''));
        $googlePlaceTypeMappings = trim($request->request->getString('google_place_type_mappings', ''));
        $sortOrder = $request->request->getInt('sort_order', 0);

        if ($name === '') {
            $errors[] = 'Debes capturar el nombre visible de la categoría.';
        } else {
            $category->setName($name);
        }

        if ($slug === '') {
            $errors[] = 'Debes capturar el slug de la categoría.';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors[] = 'El slug solo puede usar minúsculas, números y guiones.';
        } else {
            $existingCategory = $entityManager->getRepository(LocationCategory::class)->findOneBy(['slug' => $slug]);
            if ($existingCategory !== null && $existingCategory->getId() !== $category->getId()) {
                $errors[] = 'Ya existe otra categoría con ese slug.';
            } else {
                $category->setSlug($slug);
            }
        }

        if ($colorHex !== '' && !preg_match('/^#[0-9A-F]{6}$/', $colorHex)) {
            $errors[] = 'El color debe estar en formato hexadecimal de 6 caracteres, por ejemplo #F97316.';
        } else {
            $category->setColorHex($colorHex !== '' ? $colorHex : null);
        }

        $category
            ->setIconKey($iconKey !== '' ? $iconKey : null)
            ->setRegionalStrategy($regionalStrategy !== '' ? $regionalStrategy : null)
            ->setFeaturedRegionScope($featuredRegionScope !== '' ? $featuredRegionScope : null)
            ->setSortOrder($sortOrder)
            ->setIsActive($request->request->getBoolean('is_active', true));

        if ($googlePlaceTypeMappings !== '') {
            $mappings = array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', $googlePlaceTypeMappings)
            )));
            $category->setGooglePlaceTypeMappings($mappings !== [] ? $mappings : null);
        } else {
            $category->setGooglePlaceTypeMappings(null);
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(LocationCategory $category, array $errors, bool $isEdit, CatalogMediaCategoryAssignmentService $catalogMediaAssignments): array
    {
        $pickerAssets = $catalogMediaAssignments->pickerAssets();
        $activeSingularAssignments = $catalogMediaAssignments->activeSingularAssignments($category);
        $activePoolAssignments = $catalogMediaAssignments->activePoolAssignments($category);

        return [
            'page_title' => $isEdit ? 'Editar categoría' : 'Nueva categoría',
            'category' => $category,
            'errors' => $errors,
            'is_edit' => $isEdit,
            'singular_slots' => [
                CatalogMediaUsageSlot::CATEGORY_ICON => 'Icono',
                CatalogMediaUsageSlot::CATEGORY_DEFAULT => 'Fotografía default',
                CatalogMediaUsageSlot::CATEGORY_COVER => 'Cover',
            ],
            'pool_slots' => [
                CatalogMediaUsageSlot::LOCATION_COVER => 'Portadas para establecimientos',
                CatalogMediaUsageSlot::LOCATION_GALLERY => 'Galería',
                CatalogMediaUsageSlot::STORY_IMAGE => 'Stories imagen',
                CatalogMediaUsageSlot::STORY_VIDEO => 'Stories video',
            ],
            'active_singular_assignments' => $activeSingularAssignments,
            'active_pool_assignments' => $activePoolAssignments,
            'active_singular_asset_uuids' => $this->singularAssetUuids($activeSingularAssignments),
            'active_pool_asset_uuids' => $this->poolAssetUuids($activePoolAssignments),
            'catalog_media_assets' => $this->pickerAssetPayload($pickerAssets, $catalogMediaAssignments),
            'legacy_asset_warnings' => $catalogMediaAssignments->legacyWarnings($category),
        ];
    }

    /**
     * @param array<string, mixed> $assignments
     * @return array<string, string>
     */
    private function singularAssetUuids(array $assignments): array
    {
        $uuids = [];
        foreach ($assignments as $slot => $assignment) {
            $asset = $assignment->getAsset();
            if ($asset instanceof CatalogMediaAsset) {
                $uuids[$slot] = $asset->getUuid();
            }
        }

        return $uuids;
    }

    /**
     * @param array<string, list<mixed>> $assignmentsBySlot
     * @return array<string, string>
     */
    private function poolAssetUuids(array $assignmentsBySlot): array
    {
        $uuidsBySlot = [];
        foreach ($assignmentsBySlot as $slot => $assignments) {
            $uuids = [];
            foreach ($assignments as $assignment) {
                $asset = $assignment->getAsset();
                if ($asset instanceof CatalogMediaAsset) {
                    $uuids[] = $asset->getUuid();
                }
            }
            $uuidsBySlot[$slot] = implode(',', $uuids);
        }

        return $uuidsBySlot;
    }

    /**
     * @param list<CatalogMediaAsset> $assets
     * @return list<array<string, mixed>>
     */
    private function pickerAssetPayload(array $assets, CatalogMediaCategoryAssignmentService $catalogMediaAssignments): array
    {
        return array_map(static function (CatalogMediaAsset $asset) use ($catalogMediaAssignments): array {
            $issuesBySlot = [];
            foreach (CatalogMediaUsageSlot::values() as $slot) {
                $issuesBySlot[$slot] = $catalogMediaAssignments->selectionIssues($asset, $slot);
            }

            return [
                'uuid' => $asset->getUuid(),
                'title' => $asset->getTitle(),
                'media_type' => $asset->getMediaType(),
                'mime_type' => $asset->getMimeType(),
                'public_url' => $asset->getPublicUrl(),
                'status' => $asset->getStatus(),
                'moderation_status' => $asset->getModerationStatus(),
                'rights_verified' => $asset->isRightsVerified(),
                'issues_by_slot' => $issuesBySlot,
            ];
        }, $assets);
    }

    /**
     * @return array<string, string|null>
     */
    private function singularSelectionsFromRequest(Request $request): array
    {
        $selections = $request->request->all('catalog_media_singular');

        return is_array($selections) ? $selections : [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function poolSelectionsFromRequest(Request $request): array
    {
        $rawSelections = $request->request->all('catalog_media_pools');
        $selections = [];
        foreach (is_array($rawSelections) ? $rawSelections : [] as $slot => $value) {
            $selections[(string) $slot] = array_values(array_filter(array_map(
                static fn (string $uuid): string => trim($uuid),
                explode(',', (string) $value)
            )));
        }

        return $selections;
    }

    /**
     * @return list<string>
     */
    private function touchedSlotsFromRequest(Request $request, string $field): array
    {
        $rawTouched = $request->request->all($field);
        $slots = [];
        foreach (is_array($rawTouched) ? $rawTouched : [] as $slot => $value) {
            if ((string) $value === '1') {
                $slots[] = (string) $slot;
            }
        }

        return $slots;
    }

    /** @param array<string, mixed> $metadata */
    private function recordAudit(EntityManagerInterface $entityManager, string $eventName, LocationCategory $category, array $metadata): void
    {
        $admin = $this->getUser();
        $entityManager->persist((new EventLog())
            ->setEventName($eventName)
            ->setActorType('admin_user')
            ->setActorId($admin instanceof AdminUser ? $admin->getId() : null)
            ->setEntityType('location_category')
            ->setEntityId($category->getId())
            ->setSourceApp('admin')
            ->setMetadataJson($metadata));
    }
}
