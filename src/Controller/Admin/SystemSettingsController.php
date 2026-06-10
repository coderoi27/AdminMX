<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\SystemPlugin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/system-settings', name: 'admin_system_settings_')]
final class SystemSettingsController extends AbstractController
{
    private const MAP_CONFIG_KEY = 'map_settings';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $mapPlugin = $this->findOrCreatePlugin($entityManager, self::MAP_CONFIG_KEY, 'Configuraciones del mapa');
        $placesPlugin = $this->findOrCreatePlugin($entityManager, SystemPlugin::GOOGLE_PLACES_PROXY, 'Google Places Proxy');

        return $this->render('admin/system_settings/index.html.twig', [
            'map_settings' => $this->mapSettings($mapPlugin),
            'places_settings' => $this->placesSettings($placesPlugin),
        ]);
    }

    #[Route('/map', name: 'map', methods: ['POST'])]
    public function updateMap(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('update_map_settings', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud de mapa.');

            return $this->redirectToRoute('admin_system_settings_index');
        }

        $plugin = $this->findOrCreatePlugin($entityManager, self::MAP_CONFIG_KEY, 'Configuraciones del mapa');
        $settings = $this->mapSettings($plugin);
        $settings['default_zoom'] = $this->boundedInt($request->request->getInt('default_zoom', 18), 10, 20);
        $settings['focused_zoom'] = $this->boundedInt($request->request->getInt('focused_zoom', 18), 10, 20);
        $settings['street_label_weight'] = $request->request->getString('street_label_weight', 'normal') === 'light' ? 'light' : 'normal';

        $plugin
            ->setIsEnabled(true)
            ->setStatus(SystemPlugin::STATUS_ACTIVE)
            ->setConfigJson($settings);

        $entityManager->persist($plugin);
        $entityManager->flush();

        $this->addFlash('success', 'Configuración del mapa actualizada.');

        return $this->redirectToRoute('admin_system_settings_index');
    }

    #[Route('/places', name: 'places', methods: ['POST'])]
    public function updatePlaces(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('update_places_settings', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud de Places.');

            return $this->redirectToRoute('admin_system_settings_index');
        }

        $plugin = $this->findOrCreatePlugin($entityManager, SystemPlugin::GOOGLE_PLACES_PROXY, 'Google Places Proxy');
        $settings = $this->placesSettings($plugin);
        $settings['nearby_radius_meters'] = $this->boundedInt($request->request->getInt('nearby_radius_meters', 1000), 100, 1000);
        $settings['nearby_max_results'] = $this->boundedInt($request->request->getInt('nearby_max_results', 20), 1, 20);
        $settings['text_search_page_size'] = $this->boundedInt($request->request->getInt('text_search_page_size', 10), 1, 20);
        $settings['text_search_mode'] = $this->textSearchMode($request->request->getString('text_search_mode', 'category_only'));
        $settings['max_total_places'] = $this->boundedInt($request->request->getInt('max_total_places', 60), 1, 120);
        $settings['cache_ttl_seconds'] = $this->boundedInt($request->request->getInt('cache_ttl_seconds', 300), 60, 900);
        $settings['empty_cache_ttl_seconds'] = $this->boundedInt($request->request->getInt('empty_cache_ttl_seconds', 60), 30, 300);
        $settings['include_photos'] = $request->request->has('include_photos');
        $settings['include_ratings'] = $request->request->has('include_ratings');
        $settings['include_opening_hours'] = $request->request->has('include_opening_hours');
        $settings['include_service_attributes'] = $request->request->has('include_service_attributes');

        $plugin->setConfigJson($settings);

        $entityManager->persist($plugin);
        $entityManager->flush();

        $this->addFlash('success', 'Configuración de Google Places actualizada.');

        return $this->redirectToRoute('admin_system_settings_index');
    }

    private function findOrCreatePlugin(EntityManagerInterface $entityManager, string $pluginKey, string $name): SystemPlugin
    {
        $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => $pluginKey]);
        if ($plugin instanceof SystemPlugin) {
            return $plugin;
        }

        return (new SystemPlugin())
            ->setPluginKey($pluginKey)
            ->setName($name)
            ->setIsEnabled(true)
            ->setStatus(SystemPlugin::STATUS_ACTIVE)
            ->setConfigJson([]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSettings(SystemPlugin $plugin): array
    {
        return array_replace([
            'default_zoom' => 18,
            'focused_zoom' => 18,
            'street_label_weight' => 'normal',
        ], $plugin->getConfigJson() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function placesSettings(SystemPlugin $plugin): array
    {
        return array_replace([
            'nearby_radius_meters' => 1000,
            'nearby_max_results' => 20,
            'text_search_page_size' => 10,
            'text_search_mode' => 'category_only',
            'max_total_places' => 60,
            'cache_ttl_seconds' => 300,
            'empty_cache_ttl_seconds' => 60,
            'include_photos' => true,
            'include_ratings' => true,
            'include_opening_hours' => true,
            'include_service_attributes' => true,
        ], $plugin->getConfigJson() ?? []);
    }

    private function boundedInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function textSearchMode(string $mode): string
    {
        return in_array($mode, ['off', 'category_only', 'always'], true) ? $mode : 'category_only';
    }
}
