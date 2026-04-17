<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\OwnerInvitation;
use App\Entity\Core\OwnerLead;
use App\Service\Core\InvitationTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class OwnerInvitationCollectionController extends AbstractController
{
    #[Route('/api/v1/owner-invitations', name: 'api_core_owner_invitations_collection', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        InvitationTokenFactory $tokenFactory,
    ): JsonResponse {
        if ($request->isMethod('GET')) {
            $invitations = $entityManager->getRepository(OwnerInvitation::class)->findBy([], ['id' => 'DESC']);

            $data = array_map(static fn (OwnerInvitation $invitation): array => [
                'invitation_id' => $invitation->getId(),
                'email' => $invitation->getEmail(),
                'invitation_type' => $invitation->getInvitationType(),
                'status' => $invitation->getStatus(),
                'expires_at' => $invitation->getExpiresAt()->format(DATE_ATOM),
                'message_subject' => $invitation->getMessageSubject(),
                'created_at' => $invitation->getCreatedAt()->format(DATE_ATOM),
            ], $invitations);

            return $this->json(['data' => $data, 'meta' => [], 'errors' => []]);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || empty($payload['email'])) {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Field "email" is required.']], 422);
        }

        $plainToken = $tokenFactory->createPlainToken();
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', (int) ($payload['expires_in_days'] ?? 7)));

        $invitation = (new OwnerInvitation())
            ->setEmail((string) $payload['email'])
            ->setTokenHash($tokenFactory->hash($plainToken))
            ->setInviteCode($payload['invite_code'] ?? null)
            ->setInvitationType((string) ($payload['invitation_type'] ?? 'pre_register'))
            ->setMessageSubject((string) ($payload['message_subject'] ?? 'Tu acceso anticipado a Mi Monchis MX'))
            ->setMessageBody((string) ($payload['message_body'] ?? ''))
            ->setStatus('sent')
            ->setExpiresAt($expiresAt);

        if (!empty($payload['owner_lead_id'])) {
            $lead = $entityManager->getRepository(OwnerLead::class)->find((int) $payload['owner_lead_id']);
            if ($lead instanceof OwnerLead) {
                $invitation->setOwnerLead($lead);
            }
        }

        $entityManager->persist($invitation);
        $entityManager->flush();

        return $this->json([
            'data' => [
                'invitation_id' => $invitation->getId(),
                'email' => $invitation->getEmail(),
                'status' => $invitation->getStatus(),
                'expires_at' => $invitation->getExpiresAt()->format(DATE_ATOM),
                'invitation_url' => sprintf('/locals/register?token=%s', $plainToken),
            ],
            'meta' => [],
            'errors' => [],
        ], 201);
    }
}
