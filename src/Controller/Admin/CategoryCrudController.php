<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\MerchantLocation;
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new LocationCategory();

        if ($request->isMethod('POST')) {
            $errors = $this->hydrateCategoryFromRequest($request, $entityManager, $category);

            if ($errors === []) {
                $entityManager->persist($category);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Categoría "%s" creada correctamente.', $category->getName()));

                return $this->redirectToRoute('admin_categories_index');
            }

            return $this->render('admin/categories/form.html.twig', [
                'page_title' => 'Nueva categoría',
                'category' => $category,
                'errors' => $errors,
                'is_edit' => false,
            ]);
        }

        return $this->render('admin/categories/form.html.twig', [
            'page_title' => 'Nueva categoría',
            'category' => $category,
            'errors' => [],
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(LocationCategory $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $errors = $this->hydrateCategoryFromRequest($request, $entityManager, $category);

            if ($errors === []) {
                $entityManager->flush();

                $this->addFlash('success', sprintf('Categoría "%s" actualizada correctamente.', $category->getName()));

                return $this->redirectToRoute('admin_categories_index');
            }

            return $this->render('admin/categories/form.html.twig', [
                'page_title' => 'Editar categoría',
                'category' => $category,
                'errors' => $errors,
                'is_edit' => true,
            ]);
        }

        return $this->render('admin/categories/form.html.twig', [
            'page_title' => 'Editar categoría',
            'category' => $category,
            'errors' => [],
            'is_edit' => true,
        ]);
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
    private function hydrateCategoryFromRequest(Request $request, EntityManagerInterface $entityManager, LocationCategory $category): array
    {
        $errors = [];

        $name = trim($request->request->getString('name', ''));
        $slug = trim(mb_strtolower($request->request->getString('slug', '')));
        $iconKey = trim($request->request->getString('icon_key', ''));
        $colorHex = strtoupper(trim($request->request->getString('color_hex', '')));
        $defaultPhotoUrl = trim($request->request->getString('default_photo_url', ''));
        $coverPhotoUrl = trim($request->request->getString('cover_photo_url', ''));
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
            ->setDefaultPhotoUrl($defaultPhotoUrl !== '' ? $defaultPhotoUrl : null)
            ->setCoverPhotoUrl($coverPhotoUrl !== '' ? $coverPhotoUrl : null)
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
}
