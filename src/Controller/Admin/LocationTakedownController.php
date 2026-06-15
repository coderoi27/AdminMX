<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\LocationTakedownRequest;
use App\Entity\Core\MerchantLocation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/takedowns', name: 'admin_takedowns_')]
final class LocationTakedownController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            return $this->create($request, $entityManager);
        }

        $selectedStatus = $request->query->getString('status', '');
        $statuses = LocationTakedownRequest::statuses();
        if (!in_array($selectedStatus, $statuses, true)) {
            $selectedStatus = '';
        }

        $queryBuilder = $entityManager->getRepository(LocationTakedownRequest::class)->createQueryBuilder('request')
            ->leftJoin('request.location', 'location')->addSelect('location')
            ->leftJoin('location.merchant', 'merchant')->addSelect('merchant')
            ->orderBy('request.id', 'DESC')
            ->setMaxResults(80);

        if ($selectedStatus !== '') {
            $queryBuilder->andWhere('request.status = :status')->setParameter('status', $selectedStatus);
        }

        $locations = $entityManager->getRepository(MerchantLocation::class)->findBy(
            ['publicationState' => MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE],
            ['id' => 'DESC'],
            80
        );

        return $this->render('admin/takedowns/index.html.twig', [
            'requests' => $queryBuilder->getQuery()->getResult(),
            'locations' => $locations,
            'selected_location_id' => $request->query->getInt('location_id', 0),
            'statuses' => $statuses,
            'resolution_actions' => LocationTakedownRequest::resolutionActions(),
            'filters' => [
                'status' => $selectedStatus,
            ],
            'stats' => $this->buildStats($entityManager, $statuses),
        ]);
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(LocationTakedownRequest $takedownRequest, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('resolve_takedown_%d', $takedownRequest->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la resolución del takedown.');

            return $this->redirectToRoute('admin_takedowns_index');
        }

        $targetStatus = $request->request->getString('status', LocationTakedownRequest::STATUS_REVIEWING);
        $resolutionAction = $request->request->getString('resolution_action', LocationTakedownRequest::ACTION_NONE);

        try {
            $takedownRequest->setResolutionNotes($request->request->getString('resolution_notes', ''));
            $takedownRequest->setResolutionAction($resolutionAction);
            $this->moveStatus($takedownRequest, $targetStatus);

            if ($targetStatus === LocationTakedownRequest::STATUS_RESOLVED) {
                $takedownRequest->setResolvedAt(new \DateTimeImmutable());
                $this->applyResolutionAction($takedownRequest->getLocation(), $resolutionAction);
            }

            $entityManager->flush();
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('admin_takedowns_index');
        }

        $this->addFlash('success', sprintf('Takedown #%d actualizado a %s.', $takedownRequest->getId(), $takedownRequest->getStatus()));

        return $this->redirectToRoute('admin_takedowns_index', [
            'status' => $request->query->getString('status', ''),
        ]);
    }

    private function create(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_takedown', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud de takedown.');

            return $this->redirectToRoute('admin_takedowns_index');
        }

        $location = $entityManager->find(MerchantLocation::class, $request->request->getInt('location_id', 0));
        $reasonText = trim($request->request->getString('reason_text', ''));

        if (!$location instanceof MerchantLocation || $reasonText === '') {
            $this->addFlash('error', 'Debes seleccionar un local y capturar una razón operativa.');

            return $this->redirectToRoute('admin_takedowns_index');
        }

        $takedownRequest = (new LocationTakedownRequest())
            ->setLocation($location)
            ->setReasonCategory($request->request->getString('reason_category', 'other'))
            ->setReasonText($reasonText)
            ->setReportedByType($request->request->getString('reported_by_type', 'admin'))
            ->setReportedByEmail($request->request->getString('reported_by_email', ''));

        $entityManager->persist($takedownRequest);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Solicitud de retiro creada para "%s".', $location->getName()));

        return $this->redirectToRoute('admin_takedowns_index');
    }

    /**
     * @param list<string> $statuses
     *
     * @return array<string, int>
     */
    private function buildStats(EntityManagerInterface $entityManager, array $statuses): array
    {
        $stats = [];
        foreach ($statuses as $status) {
            $stats[$status] = (int) $entityManager->getRepository(LocationTakedownRequest::class)->createQueryBuilder('request')
                ->select('COUNT(request.id)')
                ->andWhere('request.status = :status')
                ->setParameter('status', $status)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $stats;
    }

    private function moveStatus(LocationTakedownRequest $request, string $targetStatus): void
    {
        if ($request->getStatus() === $targetStatus) {
            return;
        }

        if ($request->canTransitionTo($targetStatus)) {
            $request->setStatus($targetStatus);

            return;
        }

        if ($request->getStatus() === LocationTakedownRequest::STATUS_REJECTED && $targetStatus === LocationTakedownRequest::STATUS_RESOLVED) {
            $request->setStatus(LocationTakedownRequest::STATUS_REVIEWING);
            $request->setStatus(LocationTakedownRequest::STATUS_RESOLVED);

            return;
        }

        throw new \InvalidArgumentException(sprintf('La transición de takedown %s -> %s no está permitida.', $request->getStatus(), $targetStatus));
    }

    private function applyResolutionAction(MerchantLocation $location, string $resolutionAction): void
    {
        if ($resolutionAction === LocationTakedownRequest::ACTION_HIDE) {
            if ($location->canTransitionPublicationStateTo(MerchantLocation::PUBLICATION_STATE_HIDDEN)) {
                $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            }

            return;
        }

        if ($resolutionAction === LocationTakedownRequest::ACTION_SUSPEND) {
            $location->setStatus(MerchantLocation::STATUS_SUSPENDED);
            if ($location->canTransitionPublicationStateTo(MerchantLocation::PUBLICATION_STATE_HIDDEN)) {
                $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            }

            return;
        }

        if ($resolutionAction === LocationTakedownRequest::ACTION_ARCHIVE) {
            $location->setStatus(MerchantLocation::STATUS_INACTIVE);
            if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_PENDING_VISIBLE) {
                $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            }
            if ($location->canTransitionPublicationStateTo(MerchantLocation::PUBLICATION_STATE_ARCHIVED)) {
                $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_ARCHIVED);
            }
        }
    }
}
