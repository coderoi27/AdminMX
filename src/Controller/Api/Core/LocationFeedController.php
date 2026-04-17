<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

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
            ['publicationState' => 'public_visible'],
            ['id' => 'DESC'],
            50
        );

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
                    'source_type' => 'owner_registered',
                    'publication_state' => $location->getPublicationState(),
                ];
            },
            $locations
        );

        return $this->json([
            'data' => $data,
            'meta' => [
                'page' => 1,
                'per_page' => count($data),
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
