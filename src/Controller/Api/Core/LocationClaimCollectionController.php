<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationClaimRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocationClaimCollectionController extends AbstractController
{
    #[Route('/api/v1/location-claims', name: 'api_core_location_claims_collection', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $claims = $entityManager->getRepository(LocationClaimRequest::class)->findBy([], ['id' => 'DESC'], 50);

            $data = array_map(static fn (LocationClaimRequest $claim): array => [
                'claim_id' => $claim->getId(),
                'source_type' => $claim->getSourceType(),
                'canonical_location_id' => $claim->getCanonicalLocationId(),
                'external_source_key' => $claim->getExternalSourceKey(),
                'location_name' => $claim->getLocationName(),
                'short_address' => $claim->getShortAddress(),
                'claimant_name' => $claim->getClaimantName(),
                'email' => $claim->getEmail(),
                'status' => $claim->getStatus(),
                'created_at' => $claim->getCreatedAt()->format(DATE_ATOM),
            ], $claims);

            return $this->json(['data' => $data, 'meta' => [], 'errors' => []]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Invalid JSON body.']], 400);
        }

        foreach (['source_type', 'location_name', 'claimant_name', 'email'] as $field) {
            if (empty($payload[$field])) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => [sprintf('Field "%s" is required.', $field)]], 422);
            }
        }

        $claim = (new LocationClaimRequest())
            ->setSourceType((string) $payload['source_type'])
            ->setCanonicalLocationId(isset($payload['canonical_location_id']) ? (int) $payload['canonical_location_id'] : null)
            ->setExternalSourceKey(isset($payload['external_source_key']) ? (string) $payload['external_source_key'] : null)
            ->setLocationName((string) $payload['location_name'])
            ->setShortAddress(isset($payload['short_address']) ? (string) $payload['short_address'] : null)
            ->setClaimantName((string) $payload['claimant_name'])
            ->setEmail((string) $payload['email'])
            ->setWhatsappE164(isset($payload['whatsapp_e164']) ? (string) $payload['whatsapp_e164'] : null)
            ->setMessage(isset($payload['message']) ? (string) $payload['message'] : null)
            ->setPrefillPayloadJson(isset($payload['prefill_payload']) && is_array($payload['prefill_payload']) ? $payload['prefill_payload'] : null);

        $entityManager->persist($claim);
        $entityManager->flush();

        return $this->json([
            'data' => [
                'claim_id' => $claim->getId(),
                'status' => $claim->getStatus(),
            ],
            'meta' => [],
            'errors' => [],
        ], 201);
    }
}
