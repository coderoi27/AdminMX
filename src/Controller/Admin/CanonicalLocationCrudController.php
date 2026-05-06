<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\Merchant;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\PlaceAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/locations', name: 'admin_locations_')]
final class CanonicalLocationCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $selectedSourceType = $request->query->getString('source_type', '');
        $selectedPublicationState = $request->query->getString('publication_state', '');
        $selectedCategorySlug = trim($request->query->getString('category_slug', ''));

        $sourceTypes = MerchantLocation::sourceTypes();
        $publicationStates = MerchantLocation::publicationStates();
        $categoryRepository = $entityManager->getRepository(LocationCategory::class);
        $categories = $categoryRepository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        if (!in_array($selectedSourceType, $sourceTypes, true)) {
            $selectedSourceType = '';
        }

        if (!in_array($selectedPublicationState, $publicationStates, true)) {
            $selectedPublicationState = '';
        }

        $selectedCategory = null;
        if ($selectedCategorySlug !== '') {
            $selectedCategory = $categoryRepository->findOneBy(['slug' => $selectedCategorySlug]);
            if ($selectedCategory === null) {
                $selectedCategorySlug = '';
            }
        }

        $queryBuilder = $entityManager->getRepository(MerchantLocation::class)->createQueryBuilder('location')
            ->leftJoin('location.merchant', 'merchant')->addSelect('merchant')
            ->leftJoin('location.primaryCategory', 'category')->addSelect('category')
            ->leftJoin('location.addresses', 'address')->addSelect('address')
            ->orderBy('location.id', 'DESC')
            ->setMaxResults(30);

        if ($selectedSourceType !== '') {
            $queryBuilder->andWhere('location.sourceType = :sourceType')->setParameter('sourceType', $selectedSourceType);
        }
        if ($selectedPublicationState !== '') {
            $queryBuilder->andWhere('location.publicationState = :publicationState')->setParameter('publicationState', $selectedPublicationState);
        }
        if ($selectedCategory !== null) {
            $queryBuilder->andWhere('location.primaryCategory = :category')->setParameter('category', $selectedCategory);
        }

        $locations = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/locations/index.html.twig', [
            'locations' => $locations,
            'source_types' => $sourceTypes,
            'publication_states' => $publicationStates,
            'categories' => $categories,
            'filters' => [
                'source_type' => $selectedSourceType,
                'publication_state' => $selectedPublicationState,
                'category_slug' => $selectedCategorySlug,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $location = new MerchantLocation();
        $address = new PlaceAddress();
        $address->setIsPrimary(true);

        if ($request->isMethod('POST')) {
            $errors = $this->hydrateLocationFromRequest($request, $entityManager, $location, $address, true);

            if ($errors === []) {
                $location->addAddress($address);
                $entityManager->persist($location);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Local "%s" creado correctamente.', $location->getName()));

                return $this->redirectToRoute('admin_locations_index');
            }

            return $this->render('admin/locations/form.html.twig', [
                'page_title' => 'Nuevo local canónico',
                'location' => $location,
                'address' => $address,
                'errors' => $errors,
                'source_types' => MerchantLocation::sourceTypes(),
                'publication_states' => MerchantLocation::publicationStates(),
                'location_types' => ['fixed', 'mobile'],
                'status_types' => ['draft', 'pending_review', 'active', 'inactive', 'suspended'],
                'categories' => $entityManager->getRepository(LocationCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
                'is_edit' => false,
                'merchant_name' => $request->request->getString('merchant_name', ''),
            ]);
        }

        return $this->render('admin/locations/form.html.twig', [
            'page_title' => 'Nuevo local canónico',
            'location' => $location,
            'address' => $address,
            'errors' => [],
            'source_types' => MerchantLocation::sourceTypes(),
            'publication_states' => MerchantLocation::publicationStates(),
            'location_types' => ['fixed', 'mobile'],
            'status_types' => ['draft', 'pending_review', 'active', 'inactive', 'suspended'],
            'categories' => $entityManager->getRepository(LocationCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'is_edit' => false,
            'merchant_name' => '',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(MerchantLocation $location, Request $request, EntityManagerInterface $entityManager): Response
    {
        $address = $location->getPrimaryAddress() ?? (new PlaceAddress())->setIsPrimary(true);

        if ($request->isMethod('POST')) {
            $errors = $this->hydrateLocationFromRequest($request, $entityManager, $location, $address, false);

            if ($errors === []) {
                if ($location->getPrimaryAddress() === null) {
                    $location->addAddress($address);
                }

                $entityManager->flush();

                $this->addFlash('success', sprintf('Local "%s" actualizado correctamente.', $location->getName()));

                return $this->redirectToRoute('admin_locations_index');
            }

            return $this->render('admin/locations/form.html.twig', [
                'page_title' => 'Editar local canónico',
                'location' => $location,
                'address' => $address,
                'errors' => $errors,
                'source_types' => MerchantLocation::sourceTypes(),
                'publication_states' => MerchantLocation::publicationStates(),
                'location_types' => ['fixed', 'mobile'],
                'status_types' => ['draft', 'pending_review', 'active', 'inactive', 'suspended'],
                'categories' => $entityManager->getRepository(LocationCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
                'is_edit' => true,
                'merchant_name' => $location->getMerchant()->getName(),
            ]);
        }

        return $this->render('admin/locations/form.html.twig', [
            'page_title' => 'Editar local canónico',
            'location' => $location,
            'address' => $address,
            'errors' => [],
            'source_types' => MerchantLocation::sourceTypes(),
            'publication_states' => MerchantLocation::publicationStates(),
            'location_types' => ['fixed', 'mobile'],
            'status_types' => ['draft', 'pending_review', 'active', 'inactive', 'suspended'],
            'categories' => $entityManager->getRepository(LocationCategory::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'is_edit' => true,
            'merchant_name' => $location->getMerchant()->getName(),
        ]);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(MerchantLocation $location, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('archive_location_%d', $location->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para archivar el local.');

            return $this->redirectToRoute('admin_locations_index');
        }

        $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_ARCHIVED);
        $location->setStatus('inactive');
        $entityManager->flush();

        $this->addFlash('info', sprintf('Local "%s" archivado correctamente.', $location->getName()));

        return $this->redirectToRoute('admin_locations_index');
    }

    /**
     * @return list<string>
     */
    private function hydrateLocationFromRequest(
        Request $request,
        EntityManagerInterface $entityManager,
        MerchantLocation $location,
        PlaceAddress $address,
        bool $allowMerchantCreation
    ): array {
        $errors = [];

        if ($allowMerchantCreation) {
            $merchantName = trim($request->request->getString('merchant_name', ''));
            if ($merchantName === '') {
                $errors[] = 'Debes capturar el nombre del merchant o negocio.';
            } else {
                $location->setMerchant($this->resolveMerchant($entityManager, $merchantName));
            }
        }

        $locationName = trim($request->request->getString('location_name', ''));
        if ($locationName === '') {
            $errors[] = 'Debes capturar el nombre visible del local.';
        } else {
            $location->setName($locationName);
        }

        $categoryId = $request->request->getInt('primary_category_id', 0);
        if ($categoryId > 0) {
            $category = $entityManager->find(LocationCategory::class, $categoryId);
            if (!$category instanceof LocationCategory) {
                $errors[] = 'La categoría seleccionada no existe.';
            } else {
                $location->setPrimaryCategory($category);
            }
        } else {
            $location->setPrimaryCategory(null);
        }

        $sourceType = $request->request->getString('source_type', MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED);
        if (!in_array($sourceType, MerchantLocation::sourceTypes(), true)) {
            $errors[] = 'El origen del local no es válido.';
        } else {
            $location->setSourceType($sourceType);
        }

        $publicationState = $request->request->getString('publication_state', MerchantLocation::PUBLICATION_STATE_HIDDEN);
        if (!in_array($publicationState, MerchantLocation::publicationStates(), true)) {
            $errors[] = 'El estado de publicación no es válido.';
        } else {
            $location->setPublicationState($publicationState);
        }

        $locationType = $request->request->getString('location_type', 'fixed');
        if (!in_array($locationType, ['fixed', 'mobile'], true)) {
            $errors[] = 'El tipo de local no es válido.';
        } else {
            $location->setLocationType($locationType);
        }

        $status = $request->request->getString('status', 'draft');
        if (!in_array($status, ['draft', 'pending_review', 'active', 'inactive', 'suspended'], true)) {
            $errors[] = 'El estado operativo no es válido.';
        } else {
            $location->setStatus($status);
        }

        $location->setPhoneE164($this->normalizeNullableField($request->request->getString('phone_e164', '')));
        $location->setWhatsappE164($this->normalizeNullableField($request->request->getString('whatsapp_e164', '')));
        $location->setWhatsappEnabled($request->request->getBoolean('whatsapp_enabled', false));
        $location->setShortDescription($this->normalizeNullableField($request->request->getString('short_description', '')));
        $location->setIsClaimable($request->request->getBoolean('is_claimable', true));

        $countryCode = strtoupper(trim($request->request->getString('country_code', 'MX')));
        if ($countryCode === '' || strlen($countryCode) !== 2) {
            $errors[] = 'El país debe capturarse como código ISO de 2 letras.';
        } else {
            $address->setCountryCode($countryCode);
        }

        $latitude = trim($request->request->getString('latitude', ''));
        $longitude = trim($request->request->getString('longitude', ''));
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            $errors[] = 'Debes capturar coordenadas válidas para el local.';
        } else {
            $address->setLatitude($latitude);
            $address->setLongitude($longitude);
        }

        $address
            ->setLabel($this->normalizeNullableField($request->request->getString('address_label', '')))
            ->setState($this->normalizeNullableField($request->request->getString('state', '')))
            ->setCity($this->normalizeNullableField($request->request->getString('city', '')))
            ->setNeighborhood($this->normalizeNullableField($request->request->getString('neighborhood', '')))
            ->setStreet($this->normalizeNullableField($request->request->getString('street', '')))
            ->setExtNumber($this->normalizeNullableField($request->request->getString('ext_number', '')))
            ->setIntNumber($this->normalizeNullableField($request->request->getString('int_number', '')))
            ->setZipCode($this->normalizeNullableField($request->request->getString('zip_code', '')))
            ->setReference($this->normalizeNullableField($request->request->getString('reference', '')))
            ->setIsPrimary(true);

        if ($locationName !== '') {
            $location->setSlug($this->buildUniqueSlug(
                $entityManager,
                MerchantLocation::class,
                $locationName,
                $location->getId(),
            ));
        }

        return $errors;
    }

    private function resolveMerchant(EntityManagerInterface $entityManager, string $merchantName): Merchant
    {
        $merchantRepository = $entityManager->getRepository(Merchant::class);
        $candidateSlug = $this->slugify($merchantName);
        $existingMerchant = $merchantRepository->findOneBy(['slug' => $candidateSlug]);

        if ($existingMerchant instanceof Merchant) {
            return $existingMerchant;
        }

        $merchant = new Merchant();
        $merchant->setName($merchantName);
        $merchant->setSlug($this->buildUniqueSlug($entityManager, Merchant::class, $merchantName));
        $merchant->setStatus('active');
        $entityManager->persist($merchant);

        return $merchant;
    }

    private function buildUniqueSlug(
        EntityManagerInterface $entityManager,
        string $entityClass,
        string $value,
        ?int $ignoreId = null
    ): string {
        $baseSlug = $this->slugify($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'registro';
        $suffix = 2;
        $repository = $entityManager->getRepository($entityClass);

        while (true) {
            $existing = $repository->findOneBy(['slug' => $slug]);
            if ($existing === null) {
                return $slug;
            }

            if (method_exists($existing, 'getId') && $existing->getId() === $ignoreId) {
                return $slug;
            }

            $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'registro', $suffix);
            $suffix += 1;
        }
    }

    private function slugify(string $value): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }

    private function normalizeNullableField(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
