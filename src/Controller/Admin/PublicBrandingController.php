<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\SystemPlugin;
use App\Form\PublicBrandingType;
use App\Service\BrandingUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/system/public-branding', name: 'admin_public_branding_')]
final class PublicBrandingController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, BrandingUploader $uploader): Response
    {
        $plugin = $this->findOrCreatePlugin($entityManager);
        $currentConfig = $plugin->getConfigJson() ?? [];

        // Aseguramos que la estructura inicial exista
        $socialShare = $currentConfig['social_share'] ?? [];
        $formData = array_merge([
            'app_name' => $currentConfig['app_name'] ?? 'Mi Monchis',
            'theme_color' => $currentConfig['theme_color'] ?? '#ff7a00',
            'default_meta_title' => $currentConfig['default_meta_title'] ?? 'Mi Monchis MX',
            'default_meta_description' => $currentConfig['default_meta_description'] ?? '',
            'default_og_title' => $socialShare['default_og_title'] ?? '',
            'default_og_description' => $socialShare['default_og_description'] ?? '',
            'twitter_card_type' => $socialShare['twitter_card_type'] ?? 'summary_large_image',
            'twitter_title' => $socialShare['twitter_title'] ?? '',
            'twitter_description' => $socialShare['twitter_description'] ?? '',
            'facebook_title' => $socialShare['facebook_title'] ?? '',
            'facebook_description' => $socialShare['facebook_description'] ?? '',
            'threads_title' => $socialShare['threads_title'] ?? '',
            'threads_description' => $socialShare['threads_description'] ?? '',
            'google_title' => $socialShare['google_title'] ?? '',
            'google_description' => $socialShare['google_description'] ?? '',
        ]);

        $form = $this->createForm(PublicBrandingType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $newConfig = [
                'app_name' => $data['app_name'],
                'theme_color' => $data['theme_color'],
                'default_meta_title' => $data['default_meta_title'],
                'default_meta_description' => $data['default_meta_description'],
                'social_share' => [
                    'default_og_title' => $data['default_og_title'],
                    'default_og_description' => $data['default_og_description'],
                    'twitter_card_type' => $data['twitter_card_type'],
                    'twitter_title' => $data['twitter_title'],
                    'twitter_description' => $data['twitter_description'],
                    'facebook_title' => $data['facebook_title'],
                    'facebook_description' => $data['facebook_description'],
                    'threads_title' => $data['threads_title'],
                    'threads_description' => $data['threads_description'],
                    'google_title' => $data['google_title'],
                    'google_description' => $data['google_description'],
                ]
            ];

            // Retener las URLs existentes por defecto
            $newConfig['logo_horizontal_url'] = $currentConfig['logo_horizontal_url'] ?? null;
            $newConfig['logo_square_url'] = $currentConfig['logo_square_url'] ?? null;
            $newConfig['favicon_url'] = $currentConfig['favicon_url'] ?? null;
            
            $newConfig['social_share']['default_og_image'] = $socialShare['default_og_image'] ?? null;
            $newConfig['social_share']['twitter_image'] = $socialShare['twitter_image'] ?? null;
            $newConfig['social_share']['facebook_image'] = $socialShare['facebook_image'] ?? null;
            $newConfig['social_share']['threads_image'] = $socialShare['threads_image'] ?? null;
            $newConfig['social_share']['google_image'] = $socialShare['google_image'] ?? null;

            // Procesar imágenes subidas
            $fileFields = [
                'logo_horizontal' => ['group' => null, 'key' => 'logo_horizontal_url'],
                'logo_square' => ['group' => null, 'key' => 'logo_square_url'],
                'favicon' => ['group' => null, 'key' => 'favicon_url'],
                'default_og_image' => ['group' => 'social_share', 'key' => 'default_og_image'],
                'twitter_image' => ['group' => 'social_share', 'key' => 'twitter_image'],
                'facebook_image' => ['group' => 'social_share', 'key' => 'facebook_image'],
                'threads_image' => ['group' => 'social_share', 'key' => 'threads_image'],
                'google_image' => ['group' => 'social_share', 'key' => 'google_image'],
            ];

            foreach ($fileFields as $fieldName => $mapping) {
                $file = $form->get($fieldName)->getData();
                if ($file) {
                    $url = $uploader->upload($file, $fieldName . '-');
                    if ($mapping['group']) {
                        $newConfig[$mapping['group']][$mapping['key']] = $url;
                    } else {
                        $newConfig[$mapping['key']] = $url;
                    }
                }
            }

            $plugin->setConfigJson($newConfig)
                   ->setIsEnabled(true)
                   ->setStatus(SystemPlugin::STATUS_ACTIVE);
                   
            $entityManager->persist($plugin);
            $entityManager->flush();

            $this->addFlash('success', 'Configuración de Branding y Social Share actualizada.');
            return $this->redirectToRoute('admin_public_branding_index');
        }

        return $this->render('admin/public_branding/index.html.twig', [
            'form' => $form->createView(),
            'current_config' => $currentConfig,
        ]);
    }

    private function findOrCreatePlugin(EntityManagerInterface $entityManager): SystemPlugin
    {
        $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => SystemPlugin::PUBLIC_BRANDING]);
        if ($plugin instanceof SystemPlugin) {
            return $plugin;
        }

        return (new SystemPlugin())
            ->setPluginKey(SystemPlugin::PUBLIC_BRANDING)
            ->setName('Branding público')
            ->setIsEnabled(true)
            ->setStatus(SystemPlugin::STATUS_ACTIVE)
            ->setConfigJson([]);
    }
}
