<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\MerchantLocation;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\EventLog;
use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LegalDocument;
use App\Entity\Core\MetricRollupDaily;
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
        $categoryRepository = $entityManager->getRepository(LocationCategory::class);
        $eventLogRepository = $entityManager->getRepository(EventLog::class);
        $allLocations = $locationRepository->findBy([], ['id' => 'DESC']);
        $allCategories = $categoryRepository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $eventLogs = $eventLogRepository->findBy([], ['id' => 'DESC'], 800);
        $claims = $entityManager->getRepository(LocationClaimRequest::class)->findBy([], ['id' => 'DESC']);
        $legalDocuments = $entityManager->getRepository(LegalDocument::class)->findBy([], ['sortOrder' => 'ASC']);
        $latestRollups = $entityManager->getRepository(MetricRollupDaily::class)->findBy([], ['rollupDate' => 'DESC', 'eventCount' => 'DESC'], 12);

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

        $googlePlacesPlugin = null;
        try {
            $googlePlacesPlugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy([
                'pluginKey' => SystemPlugin::GOOGLE_PLACES_PROXY,
            ]);
        } catch (Exception) {
            $googlePlacesPlugin = null;
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
            'google_places_plugin' => $googlePlacesPlugin,
            'fake_seed_total' => count(array_filter(
                $allLocations,
                static fn (MerchantLocation $location): bool => $location->getSourceType() === MerchantLocation::SOURCE_TYPE_FAKE_SEED
            )),
            'category_total' => count($allCategories),
            'active_category_total' => count(array_filter(
                $allCategories,
                static fn (LocationCategory $category): bool => $category->isActive()
            )),
            'claims_pending_total' => count(array_filter(
                $claims,
                static fn (LocationClaimRequest $claim): bool => $claim->getStatus() === LocationClaimRequest::STATUS_PENDING
            )),
            'legal_document_total' => count($legalDocuments),
            'legal_document_published_total' => count(array_filter(
                $legalDocuments,
                static fn (LegalDocument $document): bool => $document->getStatus() === LegalDocument::STATUS_PUBLISHED
            )),
            'analytics' => [
                'favorites_added' => $this->countEvents($eventLogs, 'public_favorite_added'),
                'directions_clicked' => $this->countEvents($eventLogs, 'public_directions_clicked'),
                'whatsapp_clicked' => $this->countEvents($eventLogs, 'public_whatsapp_clicked'),
                'claims_started' => $this->countEvents($eventLogs, 'public_claim_started'),
                'addresses_saved' => $this->countEvents($eventLogs, 'public_address_saved'),
                'locations_opened' => $this->countEvents($eventLogs, 'public_location_opened'),
                'latest_rollups' => $latestRollups,
            ],
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

    /**
     * @param list<EventLog> $eventLogs
     */
    private function countEvents(array $eventLogs, string $eventName): int
    {
        return count(array_filter(
            $eventLogs,
            static fn (EventLog $eventLog): bool => $eventLog->getEventName() === $eventName
        ));
    }
}
