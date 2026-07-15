<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\ClaimStateMachine;
use App\Entity\Admin\AdminUser;
use App\Entity\Core\EventLog;
use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/claims', name: 'admin_claims_')]
final class ClaimRequestController extends AbstractController
{
    private const REVIEW_ACTIONS = [
        LocationClaimRequest::STATUS_UNDER_REVIEW,
        LocationClaimRequest::STATUS_NEEDS_INFO,
        LocationClaimRequest::STATUS_APPROVED,
        LocationClaimRequest::STATUS_REJECTED,
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $statuses = [
            ClaimStateMachine::STATE_DRAFT,
            ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION,
            ClaimStateMachine::STATE_PENDING_EVIDENCE,
            ClaimStateMachine::STATE_SUBMITTED,
            ClaimStateMachine::STATE_UNDER_REVIEW,
            ClaimStateMachine::STATE_NEEDS_INFO,
            ClaimStateMachine::STATE_APPROVED,
            ClaimStateMachine::STATE_REJECTED,
            ClaimStateMachine::STATE_CONVERTED,
            ClaimStateMachine::STATE_EXPIRED,
            ClaimStateMachine::STATE_CANCELLED,
            ClaimStateMachine::STATE_REVOKED_BEFORE_CONVERSION,
            ClaimStateMachine::STATE_CLOSED,
        ];
        $status = trim($request->query->getString('status'));
        $source = trim($request->query->getString('source'));
        $dateFrom = $this->parseDate($request->query->getString('date_from'));
        $dateTo = $this->parseDate($request->query->getString('date_to'));

        $queryBuilder = $entityManager->getRepository(LocationClaimRequest::class)
            ->createQueryBuilder('claim')
            ->orderBy('claim.createdAt', 'DESC')
            ->setMaxResults(100);

        if (in_array($status, $statuses, true)) {
            $queryBuilder->andWhere('claim.status = :status')->setParameter('status', $status);
        } else {
            $status = '';
        }
        if ($source !== '') {
            $queryBuilder->andWhere('claim.sourceType = :source')->setParameter('source', $source);
        }
        if ($dateFrom !== null) {
            $queryBuilder->andWhere('claim.createdAt >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo !== null) {
            $queryBuilder->andWhere('claim.createdAt < :dateTo')->setParameter('dateTo', $dateTo->modify('+1 day'));
        }

        $sourceRows = $entityManager->createQueryBuilder()
            ->select('DISTINCT claimSource.sourceType AS sourceType')
            ->from(LocationClaimRequest::class, 'claimSource')
            ->orderBy('claimSource.sourceType', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->render('admin/claims/index.html.twig', [
            'claims' => $queryBuilder->getQuery()->getResult(),
            'statuses' => $statuses,
            'sources' => array_column($sourceRows, 'sourceType'),
            'filters' => [
                'status' => $status,
                'source' => $source,
                'date_from' => $dateFrom?->format('Y-m-d') ?? '',
                'date_to' => $dateTo?->format('Y-m-d') ?? '',
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(LocationClaimRequest $claim, EntityManagerInterface $entityManager): Response
    {
        $evidences = $entityManager->getRepository(LocationClaimEvidence::class)->findBy(
            ['claim' => $claim],
            ['createdAt' => 'DESC'],
        );
        $auditEvents = $entityManager->getRepository(EventLog::class)->findBy(
            ['entityType' => 'location_claim_request', 'entityId' => $claim->getId(), 'sourceApp' => 'admin'],
            ['occurredAt' => 'DESC'],
        );

        return $this->render('admin/claims/show.html.twig', [
            'claim' => $claim,
            'evidences' => $evidences,
            'audit_events' => $auditEvents,
            'review_actions' => self::REVIEW_ACTIONS,
        ]);
    }

    #[Route('/{id<\d+>}/notes', name: 'notes', methods: ['POST'])]
    public function notes(LocationClaimRequest $claim, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('claim_notes_%d', $claim->getId()), $request->request->getString('_token'))) {
            $this->addFlash('error', 'No se pudo validar la actualización.');

            return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
        }

        $claim
            ->setReviewChecklistJson($this->reviewChecklistFromRequest($request))
            ->setReviewNotes($this->emptyToNull($request->request->getString('review_notes')));
        $this->recordAudit($entityManager, $claim, 'admin_claim_notes_updated', [
            'status' => $claim->getStatus(),
            'checklist' => $claim->getReviewChecklistJson(),
            'has_internal_note' => $claim->getReviewNotes() !== null,
        ]);
        $entityManager->flush();

        $this->addFlash('success', 'Notas internas guardadas.');

        return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
    }

    #[Route('/{id<\d+>}/status', name: 'status', methods: ['POST'])]
    public function status(
        LocationClaimRequest $claim,
        Request $request,
        EntityManagerInterface $entityManager,
        ClaimStateMachine $stateMachine,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid(sprintf('claim_status_%d', $claim->getId()), $request->request->getString('_token'))) {
            $this->addFlash('error', 'No se pudo validar la transición.');

            return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
        }

        $targetStatus = $request->request->getString('status');
        if (!in_array($targetStatus, self::REVIEW_ACTIONS, true)
            || !$stateMachine->canTransitionTo($claim->getStatus(), $targetStatus)) {
            $this->addFlash('error', sprintf('La transición %s -> %s no está permitida.', $claim->getStatus(), $targetStatus));

            return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
        }

        $notes = $this->emptyToNull($request->request->getString('review_notes'));
        $checklist = $this->reviewChecklistFromRequest($request);
        $evidenceCount = $entityManager->getRepository(LocationClaimEvidence::class)->count([
            'claim' => $claim,
            'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED],
        ]);

        if ($targetStatus === LocationClaimRequest::STATUS_APPROVED
            && ($evidenceCount === 0 || !$this->reviewChecklistIsComplete($checklist))) {
            $this->addFlash('error', 'Para aprobar se requiere evidencia privada disponible y el checklist completo.');

            return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
        }
        if (in_array($targetStatus, [LocationClaimRequest::STATUS_NEEDS_INFO, LocationClaimRequest::STATUS_REJECTED], true)
            && $notes === null) {
            $this->addFlash('error', 'Captura una nota interna que justifique esta transición.');

            return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
        }

        $previousStatus = $claim->getStatus();
        $claim
            ->setReviewChecklistJson($checklist)
            ->setReviewNotes($notes)
            ->setStatus($targetStatus)
            ->setReviewedAt(new \DateTimeImmutable());

        $this->recordAudit($entityManager, $claim, 'admin_claim_status_changed', [
            'from_status' => $previousStatus,
            'to_status' => $targetStatus,
            'checklist' => $checklist,
            'has_internal_note' => $notes !== null,
            'evidence_count' => $evidenceCount,
            'materialized' => false,
        ]);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Claim actualizado a %s. No se materializó ningún local.', $targetStatus));

        return $this->redirectToRoute('admin_claims_show', ['id' => $claim->getId()]);
    }

    #[Route('/{claimId<\d+>}/evidence/{evidenceId<\d+>}', name: 'evidence', methods: ['GET'])]
    public function evidence(
        int $claimId,
        int $evidenceId,
        EntityManagerInterface $entityManager,
        ClaimEvidenceStorageInterface $storage,
        HttpClientInterface $httpClient,
    ): Response {
        $evidence = $entityManager->find(LocationClaimEvidence::class, $evidenceId);
        if (!$evidence instanceof LocationClaimEvidence
            || $evidence->getClaim()->getId() !== $claimId
            || !in_array($evidence->getStatus(), [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED], true)) {
            throw new NotFoundHttpException('La evidencia no está disponible.');
        }

        try {
            $signedUrl = $storage->createReadUrl($evidence->getObjectKey(), 120);
            $upstream = $httpClient->request('GET', $signedUrl);
            if ($upstream->getStatusCode() !== Response::HTTP_OK) {
                throw new \RuntimeException('El storage privado rechazó la lectura.');
            }
        } catch (\Throwable) {
            $this->addFlash('error', 'No fue posible abrir la evidencia privada. Intenta de nuevo.');

            return $this->redirectToRoute('admin_claims_show', ['id' => $claimId]);
        }

        $this->recordAudit($entityManager, $evidence->getClaim(), 'admin_claim_evidence_viewed', [
            'evidence_id' => $evidence->getId(),
            'evidence_type' => $evidence->getEvidenceType(),
        ]);
        $entityManager->flush();

        $response = new StreamedResponse(static function () use ($httpClient, $upstream): void {
            foreach ($httpClient->stream($upstream) as $chunk) {
                if (!$chunk->isTimeout()) {
                    echo $chunk->getContent();
                }
            }
        });
        $response->headers->set('Content-Type', $evidence->getMimeType() ?: 'application/octet-stream');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $evidence->getOriginalFilename() ?: sprintf('evidence-%d', $evidence->getId()),
        ));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /** @return array{contact_verified:bool, ownership_evidence:bool, location_match:bool} */
    private function reviewChecklistFromRequest(Request $request): array
    {
        return [
            'contact_verified' => $request->request->getBoolean('check_contact_verified'),
            'ownership_evidence' => $request->request->getBoolean('check_ownership_evidence'),
            'location_match' => $request->request->getBoolean('check_location_match'),
        ];
    }

    /** @param array{contact_verified:bool, ownership_evidence:bool, location_match:bool} $checklist */
    private function reviewChecklistIsComplete(array $checklist): bool
    {
        return $checklist['contact_verified'] && $checklist['ownership_evidence'] && $checklist['location_match'];
    }

    private function emptyToNull(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $metadata */
    private function recordAudit(
        EntityManagerInterface $entityManager,
        LocationClaimRequest $claim,
        string $eventName,
        array $metadata,
    ): void {
        $admin = $this->getUser();
        $event = (new EventLog())
            ->setEventName($eventName)
            ->setActorType('admin_user')
            ->setActorId($admin instanceof AdminUser ? $admin->getId() : null)
            ->setEntityType('location_claim_request')
            ->setEntityId($claim->getId())
            ->setSourceApp('admin')
            ->setMetadataJson($metadata);
        $entityManager->persist($event);
    }
}
