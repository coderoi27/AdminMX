<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\OwnerLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class OwnerLeadCollectionController extends AbstractController
{
    #[Route('/api/v1/owner-leads', name: 'api_core_owner_leads_collection', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $leads = $entityManager->getRepository(OwnerLead::class)->findBy([], ['id' => 'DESC']);

            $data = array_map(static fn (OwnerLead $lead): array => [
                'lead_id' => $lead->getId(),
                'owner_name' => $lead->getOwnerName(),
                'business_name' => $lead->getBusinessName(),
                'city' => $lead->getCity(),
                'email' => $lead->getEmail(),
                'business_type' => $lead->getBusinessType(),
                'status' => $lead->getStatus(),
                'source_channel' => $lead->getSourceChannel(),
                'message' => $lead->getMessage(),
                'created_at' => $lead->getCreatedAt()->format(DATE_ATOM),
            ], $leads);

            return $this->json(['data' => $data, 'meta' => [], 'errors' => []]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Invalid JSON body.']], 400);
        }

        foreach (['owner_name', 'business_name', 'city', 'email'] as $field) {
            if (empty($payload[$field])) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => [sprintf('Field "%s" is required.', $field)]], 422);
            }
        }

        $lead = (new OwnerLead())
            ->setOwnerName((string) $payload['owner_name'])
            ->setBusinessName((string) $payload['business_name'])
            ->setCity((string) $payload['city'])
            ->setEmail((string) $payload['email'])
            ->setWhatsappE164($payload['whatsapp_e164'] ?? null)
            ->setBusinessType($payload['business_type'] ?? null)
            ->setMessage($payload['message'] ?? null)
            ->setSourceChannel((string) ($payload['source_channel'] ?? 'organic_form'));

        $entityManager->persist($lead);
        $entityManager->flush();

        return $this->json([
            'data' => [
                'lead_id' => $lead->getId(),
                'status' => $lead->getStatus(),
            ],
            'meta' => [],
            'errors' => [],
        ], 201);
    }
}
