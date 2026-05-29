<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\GooglePlaceBlacklist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blacklist', name: 'admin_blacklist_')]
final class GooglePlaceBlacklistController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $items = $entityManager->getRepository(GooglePlaceBlacklist::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/blacklist/index.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $key = $request->request->get('external_source_key');
        $reason = $request->request->get('reason');

        if (is_string($key) && trim($key) !== '') {
            $existing = $entityManager->getRepository(GooglePlaceBlacklist::class)->findOneBy(['externalSourceKey' => trim($key)]);
            if ($existing === null) {
                $item = new GooglePlaceBlacklist();
                $item->setExternalSourceKey(trim($key));
                $item->setReason(is_string($reason) ? trim($reason) : null);
                
                $entityManager->persist($item);
                $entityManager->flush();
                
                $this->addFlash('success', 'Lugar bloqueado correctamente.');
            } else {
                $this->addFlash('warning', 'Ese lugar ya estaba bloqueado.');
            }
        }

        return $this->redirectToRoute('admin_blacklist_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $entityManager): RedirectResponse
    {
        $item = $entityManager->getRepository(GooglePlaceBlacklist::class)->find($id);
        if ($item !== null) {
            $entityManager->remove($item);
            $entityManager->flush();
            $this->addFlash('success', 'El bloqueo ha sido eliminado.');
        }

        return $this->redirectToRoute('admin_blacklist_index');
    }
}
