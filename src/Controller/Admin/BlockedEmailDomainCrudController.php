<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\BlockedEmailDomain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blocked-email-domains', name: 'admin_blocked_email_domains_')]
final class BlockedEmailDomainCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $domain = mb_strtolower(trim($request->request->getString('domain', '')));
            if ($domain === '') {
                $this->addFlash('error', 'Debes capturar un dominio.');

                return $this->redirectToRoute('admin_blocked_email_domains_index');
            }

            $existing = $entityManager->getRepository(BlockedEmailDomain::class)->findOneBy(['domain' => $domain]);
            if ($existing instanceof BlockedEmailDomain) {
                $existing
                    ->setProviderName($this->normalizeNullableField($request->request->getString('provider_name', '')))
                    ->setReason($this->normalizeNullableField($request->request->getString('reason', '')))
                    ->setIsActive(true);
            } else {
                $blocked = (new BlockedEmailDomain())
                    ->setDomain($domain)
                    ->setProviderName($this->normalizeNullableField($request->request->getString('provider_name', '')))
                    ->setReason($this->normalizeNullableField($request->request->getString('reason', '')))
                    ->setIsActive(true);
                $entityManager->persist($blocked);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Dominio bloqueado actualizado correctamente.');

            return $this->redirectToRoute('admin_blocked_email_domains_index');
        }

        return $this->render('admin/blocked_email_domains/index.html.twig', [
            'domains' => $entityManager->getRepository(BlockedEmailDomain::class)->findBy([], ['domain' => 'ASC']),
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(BlockedEmailDomain $blockedEmailDomain, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('toggle_blocked_domain_%d', $blockedEmailDomain->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud del dominio bloqueado.');

            return $this->redirectToRoute('admin_blocked_email_domains_index');
        }

        $blockedEmailDomain->setIsActive(!$blockedEmailDomain->isActive());
        $entityManager->flush();

        $this->addFlash('success', 'Dominio actualizado correctamente.');

        return $this->redirectToRoute('admin_blocked_email_domains_index');
    }

    private function normalizeNullableField(string $value): ?string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
