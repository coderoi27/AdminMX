<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\SystemPlugin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/system-plugins', name: 'admin_system_plugins_')]
final class SystemPluginCrudController extends AbstractController
{
    #[Route('/{pluginKey}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(string $pluginKey, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('toggle_plugin_%s', $pluginKey), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido para esta operación.');

            return $this->redirectToRoute('admin_dashboard');
        }

        $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => $pluginKey]);

        if ($plugin === null) {
            // Auto-create the plugin if it doesn't exist
            $plugin = new SystemPlugin();
            $plugin->setPluginKey($pluginKey);
            $plugin->setName(ucwords(str_replace('_', ' ', $pluginKey)));
            $plugin->setStatus(SystemPlugin::STATUS_ACTIVE);
            $entityManager->persist($plugin);
        }

        $plugin->setIsEnabled(!$plugin->isEnabled());
        $entityManager->flush();

        $this->addFlash('success', sprintf('Plugin %s %s correctamente.', $plugin->getName(), $plugin->isEnabled() ? 'activado' : 'desactivado'));

        return $this->redirectToRoute('admin_dashboard');
    }
}
