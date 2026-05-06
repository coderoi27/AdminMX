<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\MerchantLocation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocationFeedController extends AbstractController
{
    #[Route('/api/v1/locations/feed', name: 'api_core_locations_feed', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $locations = $entityManager->getRepository(MerchantLocation::class)->findBy(
            ['publicationState' => MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE],
            ['id' => 'DESC'],
            50
        );
        $categories = $entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $data = array_map(
            static function (MerchantLocation $location) use ($lat, $lng): array {
                $primaryAddress = $location->getAddresses()->first();

                $distanceMeters = null;
                if ($primaryAddress !== false && $lat !== null && $lng !== null) {
                    $distanceMeters = self::distanceMeters(
                        (float) $lat,
                        (float) $lng,
                        (float) $primaryAddress->getLatitude(),
                        (float) $primaryAddress->getLongitude(),
                    );
                }

                return [
                    'location_id' => $location->getId(),
                    'merchant_name' => $location->getMerchant()->getName(),
                    'location_name' => $location->getName(),
                    'lat' => $primaryAddress !== false ? (float) $primaryAddress->getLatitude() : null,
                    'lng' => $primaryAddress !== false ? (float) $primaryAddress->getLongitude() : null,
                    'short_address' => $primaryAddress !== false ? $primaryAddress->toShortAddress() : null,
                    'distance_meters' => $distanceMeters,
                    'whatsapp_enabled' => $location->isWhatsappEnabled(),
                    'whatsapp_e164' => $location->getWhatsappE164(),
                    'is_claimable' => $location->isClaimable(),
                    'source_type' => $location->getSourceType(),
                    'external_source_key' => $location->getExternalSourceKey(),
                    'publication_state' => $location->getPublicationState(),
                    'category_id' => $location->getPrimaryCategory()?->getId(),
                    'category_slug' => $location->getPrimaryCategory()?->getSlug(),
                    'category_name' => $location->getPrimaryCategory()?->getName(),
                    'category_icon_key' => $location->getPrimaryCategory()?->getIconKey(),
                    'category_color_hex' => $location->getPrimaryCategory()?->getColorHex(),
                    'category_default_photo_url' => $location->getPrimaryCategory()?->getDefaultPhotoUrl(),
                    'category_cover_photo_url' => $location->getPrimaryCategory()?->getCoverPhotoUrl(),
                ];
            },
            $locations
        );

        return $this->json([
            'data' => $data,
            'meta' => [
                'page' => 1,
                'per_page' => count($data),
                'contract_version' => '2026-05-03',
                'contract' => [
                    'source_type' => MerchantLocation::sourceTypes(),
                    'publication_state' => MerchantLocation::publicationStates(),
                    'dedup_priority' => ['owner_registered', 'claimed', 'admin_curated', 'fake_seed', 'google_places'],
                ],
                'category_catalog' => array_map(
                    static fn (LocationCategory $category): array => [
                        'id' => $category->getId(),
                        'name' => $category->getName(),
                        'slug' => $category->getSlug(),
                        'icon_key' => $category->getIconKey(),
                        'color_hex' => $category->getColorHex(),
                        'default_photo_url' => $category->getDefaultPhotoUrl(),
                        'cover_photo_url' => $category->getCoverPhotoUrl(),
                        'regional_strategy' => $category->getRegionalStrategy(),
                        'featured_region_scope' => $category->getFeaturedRegionScope(),
                        'google_place_type_mappings' => $category->getGooglePlaceTypeMappings(),
                        'sort_order' => $category->getSortOrder(),
                        'is_active' => $category->isActive(),
                    ],
                    $categories
                ),
            ],
            'errors' => [],
        ]);
    }

    private static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
