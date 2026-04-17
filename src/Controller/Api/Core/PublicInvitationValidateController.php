<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\PublicInvitation;
use App\Service\Core\InvitationTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PublicInvitationValidateController extends AbstractController
{
    #[Route('/api/v1/public-invitations/validate', name: 'api_core_public_invitations_validate', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        InvitationTokenFactory $tokenFactory,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $accessCode = trim((string) (($payload['access_code'] ?? $payload['token'] ?? '')));

        if ($accessCode === '') {
            return $this->json(['data' => null, 'meta' => [], 'errors' => ['Field "access_code" is required.']], 422);
        }

        $invitations = $entityManager->getRepository(PublicInvitation::class)->findBy([], ['id' => 'DESC']);
        $hashedAccessCode = $tokenFactory->hash($accessCode);
        $now = new \DateTimeImmutable();

        foreach ($invitations as $invitation) {
            if (!$invitation instanceof PublicInvitation) {
                continue;
            }

            $matchesToken = hash_equals($invitation->getTokenHash(), $hashedAccessCode);
            $matchesInviteCode = $invitation->getInviteCode() !== null
                && hash_equals(mb_strtolower($invitation->getInviteCode()), mb_strtolower($accessCode));

            if (!$matchesToken && !$matchesInviteCode) {
                continue;
            }

            if ($invitation->getUsedAt() !== null) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => ['La invitacion ya fue utilizada.']], 409);
            }

            if ($invitation->getExpiresAt() < $now) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => ['La invitacion ya expiro.']], 410);
            }

            if (!in_array($invitation->getStatus(), ['sent', 'opened', 'draft'], true)) {
                return $this->json(['data' => null, 'meta' => [], 'errors' => ['La invitacion no esta disponible.']], 409);
            }

            return $this->json([
                'data' => [
                    'valid' => true,
                    'email' => $invitation->getEmail(),
                    'campaign_name' => $invitation->getCampaignName(),
                    'campaign_type' => $invitation->getCampaignType(),
                    'expires_at' => $invitation->getExpiresAt()->format(DATE_ATOM),
                ],
                'meta' => [],
                'errors' => [],
            ]);
        }

        return $this->json(['data' => null, 'meta' => [], 'errors' => ['La invitacion no es valida.']], 404);
    }
}
