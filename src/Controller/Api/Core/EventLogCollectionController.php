<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\EventLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EventLogCollectionController extends AbstractController
{
    #[Route('/api/v1/event-logs', name: 'api_core_event_logs_collection', methods: ['POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Invalid JSON body.']], 400);
        }

        foreach (['event_name', 'actor_type', 'entity_type', 'source_app'] as $field) {
            if (empty($payload[$field])) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => [sprintf('Field "%s" is required.', $field)]], 422);
            }
        }

        $log = (new EventLog())
            ->setEventName((string) $payload['event_name'])
            ->setActorType((string) $payload['actor_type'])
            ->setActorId(isset($payload['actor_id']) ? (int) $payload['actor_id'] : null)
            ->setEntityType((string) $payload['entity_type'])
            ->setEntityId(isset($payload['entity_id']) ? (int) $payload['entity_id'] : null)
            ->setSourceApp((string) $payload['source_app'])
            ->setMetadataJson(isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null)
            ->setOccurredAt(isset($payload['occurred_at']) ? new \DateTimeImmutable((string) $payload['occurred_at']) : new \DateTimeImmutable());

        $entityManager->persist($log);
        $entityManager->flush();

        return $this->json([
            'data' => [
                'event_log_id' => $log->getId(),
            ],
            'meta' => [],
            'errors' => [],
        ], 201);
    }
}
