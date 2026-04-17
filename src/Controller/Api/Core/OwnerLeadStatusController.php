<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\OwnerLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class OwnerLeadStatusController extends AbstractController
{
    #[Route('/api/v1/owner-leads/{leadId}/status', name: 'api_core_owner_leads_status', methods: ['PATCH'])]
    public function __invoke(int $leadId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $lead = $entityManager->getRepository(OwnerLead::class)->find($leadId);
        if (!$lead instanceof OwnerLead) {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Lead not found.']], 404);
        }

        $payload = json_decode($request->getContent(), true);
        $status = is_array($payload) ? ($payload['status'] ?? null) : null;
        if (!is_string($status) || $status === '') {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Field "status" is required.']], 422);
        }

        $lead->setStatus($status);
        $entityManager->flush();

        return $this->json([
            'data' => [
                'lead_id' => $lead->getId(),
                'status' => $lead->getStatus(),
            ],
            'meta' => [],
            'errors' => [],
        ]);
    }
}
