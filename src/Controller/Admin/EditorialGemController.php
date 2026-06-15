<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\MerchantLocation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/joyitas', name: 'admin_joyitas_')]
final class EditorialGemController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $selectedStatus = $request->query->getString('gem_status', '');
        $gemStatuses = MerchantLocation::gemStatuses();

        if (!in_array($selectedStatus, $gemStatuses, true)) {
            $selectedStatus = '';
        }

        $queryBuilder = $entityManager->getRepository(MerchantLocation::class)->createQueryBuilder('location')
            ->leftJoin('location.merchant', 'merchant')->addSelect('merchant')
            ->leftJoin('location.primaryCategory', 'category')->addSelect('category')
            ->leftJoin('location.addresses', 'address')->addSelect('address')
            ->orderBy('location.id', 'DESC')
            ->setMaxResults(80);

        if ($selectedStatus !== '') {
            $queryBuilder->andWhere('location.gemStatus = :gemStatus')->setParameter('gemStatus', $selectedStatus);
        } else {
            $queryBuilder->andWhere('location.publicationState != :archived')
                ->setParameter('archived', MerchantLocation::PUBLICATION_STATE_ARCHIVED);
        }

        $locations = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/joyitas/index.html.twig', [
            'locations' => $locations,
            'gem_statuses' => $gemStatuses,
            'filters' => [
                'gem_status' => $selectedStatus,
            ],
            'stats' => $this->buildStats($entityManager, $gemStatuses),
        ]);
    }

    #[Route('/{id}/status', name: 'status', methods: ['POST'])]
    public function status(MerchantLocation $location, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('joyita_status_%d', $location->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud de curación Joyita.');

            return $this->redirectToRoute('admin_joyitas_index');
        }

        $targetStatus = $request->request->getString('gem_status', MerchantLocation::GEM_STATUS_PENDING);
        if (!in_array($targetStatus, MerchantLocation::gemStatuses(), true)) {
            $this->addFlash('error', 'El estado Joyita solicitado no es válido.');

            return $this->redirectToRoute('admin_joyitas_index');
        }

        $location->setGemReasonTags($this->normalizeTags($request->request->getString('gem_reason_tags', '')));

        try {
            $this->moveGemStatus($location, $targetStatus);
            $entityManager->flush();
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('admin_joyitas_index');
        }

        $this->addFlash('success', sprintf('Joyita "%s" actualizada a %s.', $location->getName(), $location->getGemStatus()));

        return $this->redirectToRoute('admin_joyitas_index', [
            'gem_status' => $request->query->getString('gem_status', ''),
        ]);
    }

    /**
     * @param list<string> $gemStatuses
     *
     * @return array<string, int>
     */
    private function buildStats(EntityManagerInterface $entityManager, array $gemStatuses): array
    {
        $stats = [];
        foreach ($gemStatuses as $status) {
            $stats[$status] = (int) $entityManager->getRepository(MerchantLocation::class)->createQueryBuilder('location')
                ->select('COUNT(location.id)')
                ->andWhere('location.gemStatus = :status')
                ->setParameter('status', $status)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $stats;
    }

    private function moveGemStatus(MerchantLocation $location, string $targetStatus): void
    {
        $currentStatus = $location->getGemStatus();
        if ($currentStatus === $targetStatus) {
            return;
        }

        if ($location->canTransitionGemStatusTo($targetStatus)) {
            $location->setGemStatus($targetStatus);

            return;
        }

        $bridgePaths = [
            MerchantLocation::GEM_STATUS_NONE.'>'.MerchantLocation::GEM_STATUS_APPROVED => [
                MerchantLocation::GEM_STATUS_PENDING,
                MerchantLocation::GEM_STATUS_APPROVED,
            ],
            MerchantLocation::GEM_STATUS_NONE.'>'.MerchantLocation::GEM_STATUS_REJECTED => [
                MerchantLocation::GEM_STATUS_PENDING,
                MerchantLocation::GEM_STATUS_REJECTED,
            ],
            MerchantLocation::GEM_STATUS_REJECTED.'>'.MerchantLocation::GEM_STATUS_APPROVED => [
                MerchantLocation::GEM_STATUS_PENDING,
                MerchantLocation::GEM_STATUS_APPROVED,
            ],
            MerchantLocation::GEM_STATUS_APPROVED.'>'.MerchantLocation::GEM_STATUS_PENDING => [
                MerchantLocation::GEM_STATUS_NONE,
                MerchantLocation::GEM_STATUS_PENDING,
            ],
            MerchantLocation::GEM_STATUS_APPROVED.'>'.MerchantLocation::GEM_STATUS_REJECTED => [
                MerchantLocation::GEM_STATUS_NONE,
                MerchantLocation::GEM_STATUS_PENDING,
                MerchantLocation::GEM_STATUS_REJECTED,
            ],
        ];

        $path = $bridgePaths[$currentStatus.'>'.$targetStatus] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException(sprintf('La transición Joyita %s -> %s no está permitida.', $currentStatus, $targetStatus));
        }

        foreach ($path as $nextStatus) {
            $location->setGemStatus($nextStatus);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(string $rawTags): array
    {
        $tags = [];
        foreach (explode(',', $rawTags) as $tag) {
            $normalizedTag = strtolower(trim($tag));
            if ($normalizedTag !== '' && !in_array($normalizedTag, $tags, true)) {
                $tags[] = mb_substr($normalizedTag, 0, 40);
            }
        }

        return $tags;
    }
}
