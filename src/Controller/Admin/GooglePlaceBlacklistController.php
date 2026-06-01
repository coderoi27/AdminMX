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
            'match_types' => GooglePlaceBlacklist::matchTypes(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $key = $request->request->get('external_source_key');
        $matchType = $request->request->getString('match_type');
        $reason = $request->request->get('reason');

        if (is_string($key) && trim($key) !== '') {
            $normalizedKey = trim(mb_strtolower($key));
            if (!in_array($matchType, GooglePlaceBlacklist::matchTypes(), true)) {
                $matchType = $this->looksLikeGooglePlaceId($normalizedKey)
                    ? GooglePlaceBlacklist::MATCH_TYPE_PLACE_ID
                    : GooglePlaceBlacklist::MATCH_TYPE_NAME_KEYWORD;
            }

            $existing = $entityManager->getRepository(GooglePlaceBlacklist::class)->findOneBy([
                'matchType' => $matchType,
                'externalSourceKey' => $normalizedKey,
            ]);
            if ($existing === null) {
                $item = new GooglePlaceBlacklist();
                $item->setMatchType($matchType);
                $item->setExternalSourceKey($normalizedKey);
                $item->setReason(is_string($reason) ? trim($reason) : null);
                
                $entityManager->persist($item);
                $entityManager->flush();
                
                $this->addFlash('success', 'Regla de bloqueo creada correctamente.');
            } else {
                $this->addFlash('warning', 'Esa regla ya existía.');
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

    private function looksLikeGooglePlaceId(string $value): bool
    {
        return str_starts_with($value, 'places/chij') || str_starts_with($value, 'chij');
    }
}
