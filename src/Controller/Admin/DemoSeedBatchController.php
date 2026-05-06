<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\DemoSeedBatch;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\SystemPlugin;
use App\Service\Admin\DemoSeedLocationGenerator;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/demo-batches', name: 'admin_demo_batches_')]
final class DemoSeedBatchController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $storageReady = true;
        $plugin = null;
        $batches = [];
        $batchStats = [];
        $demoLocationsCount = 0;

        try {
            $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy([
                'pluginKey' => SystemPlugin::DEMO_SEED_LOCATIONS,
            ]);
            $batches = $entityManager->getRepository(DemoSeedBatch::class)->findBy([], ['id' => 'DESC'], 18);
            foreach (DemoSeedBatch::statuses() as $status) {
                $batchStats[$status] = count(array_filter(
                    $batches,
                    static fn (DemoSeedBatch $batch): bool => $batch->getStatus() === $status
                ));
            }
            $demoLocationsCount = $entityManager->getRepository(MerchantLocation::class)->count([
                'sourceType' => MerchantLocation::SOURCE_TYPE_FAKE_SEED,
            ]);
        } catch (Exception) {
            $storageReady = false;
            foreach (DemoSeedBatch::statuses() as $status) {
                $batchStats[$status] = 0;
            }
        }

        return $this->render('admin/demo_batches/index.html.twig', [
            'storage_ready' => $storageReady,
            'plugin' => $plugin,
            'batches' => $batches,
            'batch_stats' => $batchStats,
            'demo_locations_count' => $demoLocationsCount,
            'active_categories' => $storageReady
                ? $entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC'])
                : [],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $batch = new DemoSeedBatch();
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->hydrateBatchFromRequest($request, $batch, $categories);

            if ($errors === []) {
                $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy([
                    'pluginKey' => SystemPlugin::DEMO_SEED_LOCATIONS,
                ]);

                if ($plugin === null) {
                    $plugin = (new SystemPlugin())
                        ->setPluginKey(SystemPlugin::DEMO_SEED_LOCATIONS)
                        ->setName('Demo Seed Locations')
                        ->setStatus(SystemPlugin::STATUS_DISABLED)
                        ->setConfigJson([
                            'notes' => 'Base operativa inicial para tandas demo integradas al circuito canónico.',
                        ]);
                    $entityManager->persist($plugin);
                }

                $batch->setPlugin($plugin);
                $entityManager->persist($batch);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Tanda "%s" creada correctamente.', $batch->getName()));

                return $this->redirectToRoute('admin_demo_batches_index');
            }
        }

        return $this->render('admin/demo_batches/form.html.twig', [
            'page_title' => 'Nueva tanda demo',
            'batch' => $batch,
            'categories' => $categories,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/seed', name: 'seed', methods: ['POST'])]
    public function seed(
        DemoSeedBatch $batch,
        Request $request,
        EntityManagerInterface $entityManager,
        DemoSeedLocationGenerator $generator
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(sprintf('seed_demo_batch_%d', $batch->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para sembrar la tanda demo.');

            return $this->redirectToRoute('admin_demo_batches_index');
        }

        try {
            $result = $generator->generate($batch, $entityManager);

            $this->addFlash(
                'success',
                sprintf(
                    'Tanda "%s" sembrada correctamente. Se generaron %d locales demo con categorías: %s.',
                    $batch->getName(),
                    $result['generated'],
                    implode(', ', $result['categories'])
                )
            );
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_demo_batches_index');
    }

    #[Route('/plugin/toggle', name: 'toggle_plugin', methods: ['POST'])]
    public function togglePlugin(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('toggle_demo_seed_plugin', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para cambiar el estado del plugin demo.');

            return $this->redirectToRoute('admin_demo_batches_index');
        }

        try {
            $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy([
                'pluginKey' => SystemPlugin::DEMO_SEED_LOCATIONS,
            ]);

            if ($plugin === null) {
                $plugin = (new SystemPlugin())
                    ->setPluginKey(SystemPlugin::DEMO_SEED_LOCATIONS)
                    ->setName('Demo Seed Locations')
                    ->setConfigJson([
                        'notes' => 'Base operativa inicial para tandas demo integradas al circuito canónico.',
                    ]);
                $entityManager->persist($plugin);
            }

            $enablePlugin = !$plugin->isEnabled();
            $plugin
                ->setIsEnabled($enablePlugin)
                ->setStatus($enablePlugin ? SystemPlugin::STATUS_ACTIVE : SystemPlugin::STATUS_DISABLED);

            $entityManager->flush();

            $this->addFlash(
                'success',
                $enablePlugin
                    ? 'Plugin demo activado. Ya puede operar sobre tandas que persistan locales canónicos.'
                    : 'Plugin demo desactivado. Los datos se conservan, pero se bloquea la generación de nuevas tandas.'
            );
        } catch (Exception) {
            $this->addFlash('error', 'La base de tandas demo aún no está lista. Ejecuta las migraciones antes de operar el plugin.');
        }

        return $this->redirectToRoute('admin_demo_batches_index');
    }

    /**
     * @param list<LocationCategory> $categories
     *
     * @return list<string>
     */
    private function hydrateBatchFromRequest(Request $request, DemoSeedBatch $batch, array $categories): array
    {
        $errors = [];
        $availableCategorySlugs = array_map(static fn (LocationCategory $category): string => $category->getSlug(), $categories);

        $name = trim($request->request->getString('name', ''));
        if ($name === '') {
            $errors[] = 'Debes capturar un nombre para la tanda demo.';
        } else {
            $batch->setName($name);
        }

        $countryCode = strtoupper(trim($request->request->getString('country_code', 'MX')));
        if ($countryCode === '' || strlen($countryCode) !== 2) {
            $errors[] = 'El país debe capturarse como código ISO de 2 letras.';
        } else {
            $batch->setCountryCode($countryCode);
        }

        $batch
            ->setStatus(DemoSeedBatch::STATUS_DRAFT)
            ->setState($this->normalizeNullableField($request->request->getString('state', '')))
            ->setCity($this->normalizeNullableField($request->request->getString('city', '')))
            ->setRegionLabel($this->normalizeNullableField($request->request->getString('region_label', '')))
            ->setSourceAddress($this->normalizeNullableField($request->request->getString('source_address', '')))
            ->setCenterLatitude($this->normalizeNullableField($request->request->getString('center_latitude', '')))
            ->setCenterLongitude($this->normalizeNullableField($request->request->getString('center_longitude', '')))
            ->setRequestedLocationsCount(max(0, $request->request->getInt('requested_locations_count', 0)))
            ->setGeneratedLocationsCount(0)
            ->setRadiusMeters(max(100, $request->request->getInt('radius_meters', 1500)));

        if ($batch->getCenterLatitude() !== null && !is_numeric($batch->getCenterLatitude())) {
            $errors[] = 'La latitud centro debe ser numérica.';
        }

        if ($batch->getCenterLongitude() !== null && !is_numeric($batch->getCenterLongitude())) {
            $errors[] = 'La longitud centro debe ser numérica.';
        }

        $selectedCategorySlugs = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim(mb_strtolower((string) $value)),
            $request->request->all('category_slugs')
        )));

        $invalidCategorySlugs = array_diff($selectedCategorySlugs, $availableCategorySlugs);
        if ($invalidCategorySlugs !== []) {
            $errors[] = 'La tanda trae categorías que no existen o no están activas.';
        } else {
            $batch->setCategorySlugs($selectedCategorySlugs);
        }

        $expiresAtRaw = trim($request->request->getString('expires_at', ''));
        if ($expiresAtRaw !== '') {
            try {
                $batch->setExpiresAt(new \DateTimeImmutable($expiresAtRaw));
            } catch (\Throwable) {
                $errors[] = 'La fecha de expiración no es válida.';
            }
        } else {
            $batch->setExpiresAt(null);
        }

        return $errors;
    }

    private function normalizeNullableField(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
