<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\DemoSeedBatch;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\SystemPlugin;
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
        ]);
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
}
