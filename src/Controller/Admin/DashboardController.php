<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\PublicInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $entityManager): Response
    {
        $publicInvitationRepository = $entityManager->getRepository(PublicInvitation::class);
        $publicInvitations = $publicInvitationRepository->findBy([], ['id' => 'DESC']);

        $stats = [
            'public_invitation_total' => count($publicInvitations),
            'public_invitation_sent' => count(array_filter($publicInvitations, static fn (PublicInvitation $invitation): bool => $invitation->getStatus() === 'sent')),
            'public_invitation_used' => count(array_filter($publicInvitations, static fn (PublicInvitation $invitation): bool => $invitation->getUsedAt() !== null)),
            'latest_public_invitations' => array_slice($publicInvitations, 0, 8),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }
}
