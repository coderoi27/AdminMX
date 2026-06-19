<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\SystemPlugin;
use App\Form\PublicBrandingType;
use App\Service\BrandingUploader;
use App\Service\PublicBrandingConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/system/public-branding', name: 'admin_public_branding_')]
final class PublicBrandingController extends AbstractController
{
    /** @var array<string, array{group: ?string, key: string}> */
    private const FILE_FIELDS = [
        'logo_horizontal' => ['group' => null, 'key' => 'logo_horizontal_url'],
        'logo_square' => ['group' => null, 'key' => 'logo_square_url'],
        'favicon' => ['group' => null, 'key' => 'favicon_url'],
        'default_og_image' => ['group' => 'social_share', 'key' => 'default_og_image'],
        'twitter_image' => ['group' => 'social_share', 'key' => 'twitter_image'],
        'facebook_image' => ['group' => 'social_share', 'key' => 'facebook_image'],
        'threads_image' => ['group' => 'social_share', 'key' => 'threads_image'],
        'google_image' => ['group' => 'social_share', 'key' => 'google_image'],
    ];

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        BrandingUploader $uploader,
        PublicBrandingConfig $brandingConfig,
    ): Response {
        $plugin = $this->findOrCreatePlugin($entityManager);
        $currentConfig = $brandingConfig->normalize($plugin->getConfigJson() ?? []);
        $form = $this->createForm(PublicBrandingType::class, $this->formData($currentConfig));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $newConfig = $this->mergeTextConfig($currentConfig, $data);
            $newAssetUrls = [];
            $replacedAssetUrls = [];

            try {
                foreach (self::FILE_FIELDS as $fieldName => $mapping) {
                    $file = $form->get($fieldName)->getData();
                    if (!$file instanceof UploadedFile) {
                        continue;
                    }

                    $asset = $uploader->upload($file, $fieldName);
                    $newAssetUrls[] = $asset['url'];
                    $oldUrl = $this->assetUrl($newConfig, $mapping['group'], $mapping['key']);
                    if ($oldUrl !== null && $oldUrl !== $asset['url']) {
                        $replacedAssetUrls[] = $oldUrl;
                    }

                    if ($mapping['group'] !== null) {
                        $newConfig[$mapping['group']][$mapping['key']] = $asset['url'];
                    } else {
                        $newConfig[$mapping['key']] = $asset['url'];
                    }
                    $newConfig['asset_manifest'][$fieldName] = $asset;
                }

                $plugin
                    ->setConfigJson($brandingConfig->normalize($newConfig))
                    ->setIsEnabled(true)
                    ->setStatus(SystemPlugin::STATUS_ACTIVE);

                $entityManager->persist($plugin);
                $entityManager->flush();
            } catch (\Throwable $exception) {
                foreach ($newAssetUrls as $newAssetUrl) {
                    $uploader->deleteByPublicUrl($newAssetUrl);
                }

                $this->addFlash('error', 'No se pudo guardar el branding. Los archivos nuevos fueron descartados de forma segura.');

                return $this->renderPage($form->createView(), $currentConfig, $brandingConfig);
            }

            foreach (array_unique($replacedAssetUrls) as $replacedAssetUrl) {
                $uploader->deleteByPublicUrl($replacedAssetUrl);
            }

            $this->addFlash('success', 'Branding público y Social Share actualizados.');

            return $this->redirectToRoute('admin_public_branding_index');
        }

        return $this->renderPage($form->createView(), $currentConfig, $brandingConfig);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function formData(array $config): array
    {
        $social = $config['social_share'];

        return [
            'app_name' => $config['app_name'],
            'theme_color' => $config['theme_color'],
            'default_meta_title' => $config['default_meta_title'],
            'default_meta_description' => $config['default_meta_description'],
            'default_og_title' => $social['default_og_title'],
            'default_og_description' => $social['default_og_description'],
            'twitter_card_type' => $social['twitter_card_type'],
            'twitter_title' => $social['twitter_title'],
            'twitter_description' => $social['twitter_description'],
            'facebook_title' => $social['facebook_title'],
            'facebook_description' => $social['facebook_description'],
            'threads_title' => $social['threads_title'],
            'threads_description' => $social['threads_description'],
            'google_title' => $social['google_title'],
            'google_description' => $social['google_description'],
        ];
    }

    /**
     * @param array<string, mixed> $currentConfig
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function mergeTextConfig(array $currentConfig, array $data): array
    {
        $config = $currentConfig;
        $config['app_name'] = trim((string) $data['app_name']);
        $config['theme_color'] = strtolower((string) $data['theme_color']);
        $config['default_meta_title'] = trim((string) $data['default_meta_title']);
        $config['default_meta_description'] = trim((string) $data['default_meta_description']);

        foreach ([
            'default_og_title',
            'default_og_description',
            'twitter_card_type',
            'twitter_title',
            'twitter_description',
            'facebook_title',
            'facebook_description',
            'threads_title',
            'threads_description',
            'google_title',
            'google_description',
        ] as $key) {
            $config['social_share'][$key] = trim((string) ($data[$key] ?? ''));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assetUrl(array $config, ?string $group, string $key): ?string
    {
        $value = $group !== null ? ($config[$group][$key] ?? null) : ($config[$key] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function renderPage(FormView $formView, array $config, PublicBrandingConfig $brandingConfig): Response
    {
        return $this->render('admin/public_branding/index.html.twig', [
            'form' => $formView,
            'current_config' => $config,
            'previews' => $brandingConfig->resolvedPreviews($config),
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
