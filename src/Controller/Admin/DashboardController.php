<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\MerchantLocation;
use App\Entity\Core\PublicInvitation;
use App\Entity\Core\SystemPlugin;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $publicInvitationRepository = $entityManager->getRepository(PublicInvitation::class);
        $publicInvitations = $publicInvitationRepository->findBy([], ['id' => 'DESC']);
        $locationRepository = $entityManager->getRepository(MerchantLocation::class);
        $allLocations = $locationRepository->findBy([], ['id' => 'DESC']);

        $selectedSourceType = $request->query->getString('source_type', '');
        $selectedPublicationState = $request->query->getString('publication_state', '');

        $sourceTypes = MerchantLocation::sourceTypes();
        $publicationStates = MerchantLocation::publicationStates();

        if (!in_array($selectedSourceType, $sourceTypes, true)) {
            $selectedSourceType = '';
        }

        if (!in_array($selectedPublicationState, $publicationStates, true)) {
            $selectedPublicationState = '';
        }

        $locationCriteria = [];
        if ($selectedSourceType !== '') {
            $locationCriteria['sourceType'] = $selectedSourceType;
        }
        if ($selectedPublicationState !== '') {
            $locationCriteria['publicationState'] = $selectedPublicationState;
        }

        $filteredLocations = $locationRepository->findBy($locationCriteria, ['id' => 'DESC'], 18);

        $locationStatsBySource = [];
        foreach ($sourceTypes as $sourceType) {
            $locationStatsBySource[$sourceType] = count(array_filter(
                $allLocations,
                static fn (MerchantLocation $location): bool => $location->getSourceType() === $sourceType
            ));
        }

        $locationStatsByPublication = [];
        foreach ($publicationStates as $publicationState) {
            $locationStatsByPublication[$publicationState] = count(array_filter(
                $allLocations,
                static fn (MerchantLocation $location): bool => $location->getPublicationState() === $publicationState
            ));
        }

        $demoPlugin = null;
        try {
            $demoPlugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy([
                'pluginKey' => SystemPlugin::DEMO_SEED_LOCATIONS,
            ]);
        } catch (Exception) {
            $demoPlugin = null;
        }

        $stats = [
            'public_invitation_total' => count($publicInvitations),
            'public_invitation_sent' => count(array_filter($publicInvitations, static fn (PublicInvitation $invitation): bool => $invitation->getStatus() === 'sent')),
            'public_invitation_used' => count(array_filter($publicInvitations, static fn (PublicInvitation $invitation): bool => $invitation->getUsedAt() !== null)),
            'latest_public_invitations' => array_slice($publicInvitations, 0, 8),
            'location_total' => count($allLocations),
            'locations_by_source' => $locationStatsBySource,
            'locations_by_publication' => $locationStatsByPublication,
            'latest_locations' => $filteredLocations,
            'demo_plugin' => $demoPlugin,
            'fake_seed_total' => count(array_filter(
                $allLocations,
                static fn (MerchantLocation $location): bool => $location->getSourceType() === MerchantLocation::SOURCE_TYPE_FAKE_SEED
            )),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'source_types' => $sourceTypes,
            'publication_states' => $publicationStates,
            'filters' => [
                'source_type' => $selectedSourceType,
                'publication_state' => $selectedPublicationState,
            ],
        ]);
    }
}
