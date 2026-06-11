<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationClaimRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApprovedClaimSyncController extends AbstractController
{
    public function __construct(private readonly ?string $localsCoreSyncToken)
    {
    }

    #[Route('/api/v1/locals/approved-claims', name: 'api_core_locals_approved_claims', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $configuredToken = trim((string) $this->localsCoreSyncToken);
        if ($configuredToken === '') {
            return $this->json([
                'data' => [],
                'meta' => [],
                'errors' => ['LOCALS_CORE_SYNC_TOKEN is not configured in Admin/Core.'],
            ], 503);
        }

        $providedToken = trim((string) $request->headers->get('X-MiMonchis-Core-Sync-Token', ''));
        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return $this->json([
                'data' => [],
                'meta' => [],
                'errors' => ['Unauthorized sync request.'],
            ], 401);
        }

        $email = mb_strtolower(trim((string) $request->query->get('email', '')));
        $limit = max(1, min(200, (int) $request->query->get('limit', 100)));

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('claim')
            ->from(LocationClaimRequest::class, 'claim')
            ->where('claim.status = :status')
            ->andWhere('claim.canonicalLocationId IS NOT NULL')
            ->setParameter('status', LocationClaimRequest::STATUS_APPROVED)
            ->orderBy('claim.reviewedAt', 'ASC')
            ->addOrderBy('claim.id', 'ASC')
            ->setMaxResults($limit);

        if ($email !== '') {
            $queryBuilder
                ->andWhere('claim.email = :email')
                ->setParameter('email', $email);
        }

        $claims = $queryBuilder->getQuery()->getResult();
        $data = [];
        foreach ($claims as $claim) {
            if (!$claim instanceof LocationClaimRequest || $claim->getId() === null || $claim->getCanonicalLocationId() === null) {
                continue;
            }

            $data[] = [
                'claim_id' => $claim->getId(),
                'email' => $claim->getEmail(),
                'canonical_location_id' => $claim->getCanonicalLocationId(),
                'location_name' => $claim->getLocationName(),
                'approved_at' => $claim->getReviewedAt()?->format(DATE_ATOM),
            ];
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'limit' => $limit,
                'email_filtered' => $email !== '',
            ],
            'errors' => [],
        ]);
    }
}
