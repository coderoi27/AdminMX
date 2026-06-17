<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\LocationOpeningException;
use App\Entity\Core\LocationOpeningHour;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\PlaceCategoryRule;
use App\Entity\Core\SystemPlugin;
use App\Entity\Core\GooglePlaceBlacklist;
use Doctrine\DBAL\Exception as DbalException;
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
        $locations = $entityManager->getRepository(MerchantLocation::class)->createQueryBuilder('location')
            ->leftJoin('location.merchant', 'merchant')->addSelect('merchant')
            ->leftJoin('location.primaryCategory', 'category')->addSelect('category')
            ->leftJoin('location.addresses', 'address')->addSelect('address')
            ->leftJoin('location.serviceProfile', 'serviceProfile')->addSelect('serviceProfile')
            ->leftJoin('location.openingHours', 'openingHour')->addSelect('openingHour')
            ->leftJoin('location.openingExceptions', 'openingException')->addSelect('openingException')
            ->leftJoin('location.mediaItems', 'mediaItem')->addSelect('mediaItem')
            ->leftJoin('location.socialLinks', 'socialLink')->addSelect('socialLink')
            ->where('location.publicationState = :publicationState')
            ->setParameter('publicationState', MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE)
            ->orderBy('location.id', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
        $categories = $entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $googlePlacesPlugin = $this->findGooglePlacesPlugin($entityManager);
        $googlePlacesEnabled = $googlePlacesPlugin !== null && $googlePlacesPlugin->isEnabled();
        $blacklistedPlaces = $this->findGooglePlaceBlacklist($entityManager);
        $placeCategoryRules = $this->findPlaceCategoryRules($entityManager);
        $claimedGooglePlaceIds = $this->findClaimedGooglePlaceIds($entityManager);

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $data = array_map(
            function (MerchantLocation $location) use ($lat, $lng): array {
                $primaryAddress = $location->getAddresses()->first();
                $serviceProfile = $location->getServiceProfile();
                $availability = $this->canonicalAvailability($location);
                $primaryMedia = $location->getPrimaryMediaItem();

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
                    'location_slug' => $location->getSlug(),
                    'merchant_name' => $location->getMerchant()->getName(),
                    'location_name' => $location->getName(),
                    'lat' => $primaryAddress !== false ? (float) $primaryAddress->getLatitude() : null,
                    'lng' => $primaryAddress !== false ? (float) $primaryAddress->getLongitude() : null,
                    'short_address' => $primaryAddress !== false ? $primaryAddress->toShortAddress() : null,
                    'distance_meters' => $distanceMeters,
                    'whatsapp_enabled' => $location->isWhatsappEnabled(),
                    'whatsapp_e164' => $location->getWhatsappE164(),
                    'service_delivery' => $serviceProfile?->offersDelivery() ?? false,
                    'service_takeaway' => $serviceProfile?->offersTakeaway() ?? false,
                    'service_dine_in' => $serviceProfile?->offersDineIn() ?? false,
                    'service_delivery_notes' => $serviceProfile?->getDeliveryNotes(),
                    'service_notes' => $serviceProfile?->getServiceNotes(),
                    'photo_url' => $primaryMedia?->getUrl(),
                    'media_items' => $this->mediaItemsPayload($location),
                    'social_links' => $this->socialLinksPayload($location),
                    'open_now' => $availability['open_now'],
                    'business_status' => 'OPERATIONAL',
                    'opening_hours_text' => $availability['opening_hours_text'],
                    'availability_source' => $availability['source'],
                    'is_claimable' => $location->isClaimable(),
                    'source_type' => $location->getSourceType(),
                    'external_source_key' => $location->getExternalSourceKey(),
                    'publication_state' => $location->getPublicationState(),
                    'gem_status' => $location->getGemStatus(),
                    'is_joyita' => $location->isEditorialGem(),
                    'gem_reason_tags' => $location->getGemReasonTags() ?? [],
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
        $data = $this->deduplicateLocations($data);

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
                    'favorites_policy' => 'Solo locales canónicos Mi Monchis con location_id estable pueden guardarse como favoritos. Google Places se puede reclamar antes de volverse favorito.',
                ],
                'plugins' => [
                    'google_places_proxy' => $googlePlacesEnabled,
                ],
                'settings' => [
                    'map' => $this->mapSettings($entityManager),
                    'google_places_proxy' => $this->googlePlacesSettings($googlePlacesPlugin),
                    'public_branding' => $this->publicBrandingSettings($entityManager),
                ],
                'google_places_blacklist' => array_map(
                    static fn (GooglePlaceBlacklist $item): string => $item->getExternalSourceKey(),
                    array_values(array_filter(
                        $googlePlacesEnabled ? $blacklistedPlaces : [],
                        static fn (GooglePlaceBlacklist $item): bool => $item->getMatchType() === GooglePlaceBlacklist::MATCH_TYPE_PLACE_ID
                    ))
                ),
                'google_places_blacklist_name_keywords' => array_map(
                    static fn (GooglePlaceBlacklist $item): string => $item->getExternalSourceKey(),
                    array_values(array_filter(
                        $googlePlacesEnabled ? $blacklistedPlaces : [],
                        static fn (GooglePlaceBlacklist $item): bool => $item->getMatchType() === GooglePlaceBlacklist::MATCH_TYPE_NAME_KEYWORD
                    ))
                ),
                'google_places_blacklist_rules' => array_map(
                    static fn (GooglePlaceBlacklist $item): array => [
                        'match_type' => $item->getMatchType(),
                        'match_value' => $item->getExternalSourceKey(),
                        'reason' => $item->getReason(),
                    ],
                    $googlePlacesEnabled ? $blacklistedPlaces : []
                ),
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
                'place_category_rules' => array_map(
                    static fn (PlaceCategoryRule $rule): array => [
                        'id' => $rule->getId(),
                        'category_id' => $rule->getCategory()?->getId(),
                        'rule_type' => $rule->getRuleType(),
                        'match_value' => $rule->getMatchValue(),
                        'priority' => $rule->getPriority(),
                    ],
                    $googlePlacesEnabled ? $placeCategoryRules : []
                ),
                'claimed_google_place_ids' => $googlePlacesEnabled ? $claimedGooglePlaceIds : [],
            ],
            'errors' => [],
        ]);
    }

    private function findGooglePlacesPlugin(EntityManagerInterface $entityManager): ?SystemPlugin
    {
        try {
            $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => SystemPlugin::GOOGLE_PLACES_PROXY]);

            return $plugin instanceof SystemPlugin ? $plugin : null;
        } catch (DbalException|\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSettings(EntityManagerInterface $entityManager): array
    {
        try {
            $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => SystemPlugin::MAP_SETTINGS]);

            return array_replace([
                'default_zoom' => 18,
                'focused_zoom' => 18,
                'street_label_weight' => 'normal',
            ], $plugin instanceof SystemPlugin ? ($plugin->getConfigJson() ?? []) : []);
        } catch (DbalException|\Throwable) {
            return [
                'default_zoom' => 18,
                'focused_zoom' => 18,
                'street_label_weight' => 'normal',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function googlePlacesSettings(?SystemPlugin $plugin): array
    {
        $enabled = $plugin instanceof SystemPlugin && $plugin->isEnabled();

        return array_replace([
            'enabled' => $enabled,
            'operational_mode' => $enabled ? 'enabled' : 'disabled',
            'disabled_effects' => [
                'Public must not call Google Places search endpoints.',
                'Core returns empty google_places_blacklist and place_category_rules arrays.',
                'Canonical category_catalog remains available.',
            ],
            'nearby_radius_meters' => 1000,
            'nearby_max_results' => 20,
            'text_search_page_size' => 10,
            'text_search_mode' => 'category_only',
            'max_total_places' => 60,
            'cache_ttl_seconds' => 300,
            'empty_cache_ttl_seconds' => 60,
            'include_photos' => true,
            'include_ratings' => true,
            'include_opening_hours' => true,
            'include_service_attributes' => true,
        ], $plugin instanceof SystemPlugin ? ($plugin->getConfigJson() ?? []) : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicBrandingSettings(EntityManagerInterface $entityManager): array
    {
        $defaults = [
            'app_name' => 'Mi Monchis',
            'logo_horizontal_url' => '/images/branding/logo-simple-horizontal.png',
            'logo_square_url' => '/images/branding/logo-simple-square.png',
            'favicon_url' => '/favicon.ico',
            'theme_color' => '#ff7a00',
            'default_meta_title' => 'Mi Monchis MX',
            'default_meta_description' => 'Explora locales cerca de ti con Mi Monchis MX.',
            'social_share' => [
                'default_og_title' => '',
                'default_og_description' => '',
                'default_og_image' => '',
                'twitter_card_type' => 'summary_large_image',
                'twitter_title' => '',
                'twitter_description' => '',
                'twitter_image' => '',
                'facebook_title' => '',
                'facebook_description' => '',
                'facebook_image' => '',
                'threads_title' => '',
                'threads_description' => '',
                'threads_image' => '',
                'google_title' => '',
                'google_description' => '',
                'google_image' => '',
            ]
        ];

        try {
            $plugin = $entityManager->getRepository(SystemPlugin::class)->findOneBy(['pluginKey' => SystemPlugin::PUBLIC_BRANDING]);

            return array_replace($defaults, $plugin instanceof SystemPlugin ? ($plugin->getConfigJson() ?? []) : []);
        } catch (DbalException|\Throwable) {
            return $defaults;
        }
    }

    /**
     * @return list<GooglePlaceBlacklist>
     */
    private function findGooglePlaceBlacklist(EntityManagerInterface $entityManager): array
    {
        try {
            $items = $entityManager->getRepository(GooglePlaceBlacklist::class)->findAll();

            return array_values(array_filter($items, static fn (mixed $item): bool => $item instanceof GooglePlaceBlacklist));
        } catch (DbalException|\Throwable) {
            return [];
        }
    }

    /**
     * @return list<PlaceCategoryRule>
     */
    private function findPlaceCategoryRules(EntityManagerInterface $entityManager): array
    {
        try {
            $rules = $entityManager->getRepository(PlaceCategoryRule::class)->findBy(['isActive' => true], ['priority' => 'ASC', 'matchValue' => 'ASC']);

            return array_values(array_filter($rules, static fn (mixed $rule): bool => $rule instanceof PlaceCategoryRule));
        } catch (DbalException|\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function findClaimedGooglePlaceIds(EntityManagerInterface $entityManager): array
    {
        try {
            $rows = $entityManager->createQueryBuilder()
                ->select('location.externalSourceKey AS external_source_key')
                ->from(MerchantLocation::class, 'location')
                ->where('location.externalSourceKey IS NOT NULL')
                ->andWhere('location.externalSourceKey <> :empty')
                ->andWhere('location.sourceType <> :googlePlaces')
                ->andWhere('location.publicationState = :publicVisible')
                ->setParameter('empty', '')
                ->setParameter('googlePlaces', MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES)
                ->setParameter('publicVisible', MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE)
                ->getQuery()
                ->getArrayResult();

            $placeIds = [];
            foreach ($rows as $row) {
                $placeId = is_string($row['external_source_key'] ?? null) ? trim($row['external_source_key']) : '';
                if ($placeId !== '') {
                    $placeIds[$placeId] = $placeId;
                }
            }

            return array_values($placeIds);
        } catch (DbalException|\Throwable) {
            return [];
        }
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

    /**
     * @return array{open_now: bool|null, opening_hours_text: list<string>, source: string}
     */
    private function canonicalAvailability(MerchantLocation $location): array
    {
        $timezone = new \DateTimeZone('America/Mexico_City');
        $now = new \DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $todayException = null;

        foreach ($location->getOpeningExceptions() as $exception) {
            if ($exception instanceof LocationOpeningException && $exception->getExceptionDate()->format('Y-m-d') === $today) {
                $todayException = $exception;
                break;
            }
        }

        $openingHoursText = $this->openingHoursText($location);

        if ($todayException instanceof LocationOpeningException) {
            return [
                'open_now' => $this->scheduleEntryIsOpen($todayException, $now),
                'opening_hours_text' => $openingHoursText,
                'source' => 'canonical_exception',
            ];
        }

        $openingHour = $location->getOpeningHourForDay((int) $now->format('N'));
        if (!$openingHour instanceof LocationOpeningHour) {
            return [
                'open_now' => null,
                'opening_hours_text' => $openingHoursText,
                'source' => 'canonical_unavailable',
            ];
        }

        return [
            'open_now' => $this->scheduleEntryIsOpen($openingHour, $now),
            'opening_hours_text' => $openingHoursText,
            'source' => 'canonical_weekly',
        ];
    }

    private function scheduleEntryIsOpen(LocationOpeningHour|LocationOpeningException $entry, \DateTimeImmutable $now): bool
    {
        if ($entry->isClosed() || $entry->getOpensAt() === null || $entry->getClosesAt() === null) {
            return false;
        }

        $current = $now->format('H:i');
        $opensAt = $entry->getOpensAt()->format('H:i');
        $closesAt = $entry->getClosesAt()->format('H:i');

        if ($opensAt === $closesAt) {
            return true;
        }

        if ($opensAt < $closesAt) {
            return $current >= $opensAt && $current < $closesAt;
        }

        return $current >= $opensAt || $current < $closesAt;
    }

    /**
     * @return list<array{media_type:string, url:string, title:string|null, alt_text:string|null, is_primary:bool}>
     */
    private function mediaItemsPayload(MerchantLocation $location): array
    {
        $items = [];
        foreach ($location->getMediaItems() as $mediaItem) {
            if (!$mediaItem instanceof \App\Entity\Core\LocationMediaItem || !$mediaItem->isActive()) {
                continue;
            }

            $items[] = [
                'media_type' => $mediaItem->getMediaType(),
                'url' => $mediaItem->getUrl(),
                'title' => $mediaItem->getTitle(),
                'alt_text' => $mediaItem->getAltText(),
                'is_primary' => $mediaItem->isPrimary(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{platform:string, url:string, label:string|null}>
     */
    private function socialLinksPayload(MerchantLocation $location): array
    {
        $items = [];
        foreach ($location->getSocialLinks() as $socialLink) {
            if (!$socialLink instanceof \App\Entity\Core\LocationSocialLink || !$socialLink->isActive()) {
                continue;
            }

            $items[] = [
                'platform' => $socialLink->getPlatform(),
                'url' => $socialLink->getUrl(),
                'label' => $socialLink->getLabel(),
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function openingHoursText(MerchantLocation $location): array
    {
        $labels = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado',
            7 => 'domingo',
        ];

        $lines = [];
        foreach ($labels as $day => $label) {
            $openingHour = $location->getOpeningHourForDay($day);
            if (!$openingHour instanceof LocationOpeningHour) {
                continue;
            }

            if ($openingHour->isClosed() || $openingHour->getOpensAt() === null || $openingHour->getClosesAt() === null) {
                $lines[] = sprintf('%s: cerrado', $label);
                continue;
            }

            $lines[] = sprintf(
                '%s: %s - %s',
                $label,
                $openingHour->getOpensAt()->format('H:i'),
                $openingHour->getClosesAt()->format('H:i')
            );
        }

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $locations
     *
     * @return list<array<string, mixed>>
     */
    private function deduplicateLocations(array $locations): array
    {
        $ranked = [];
        foreach ($locations as $location) {
            $dedupKey = $this->dedupKey($location);
            $existing = $ranked[$dedupKey] ?? null;

            if ($existing === null || $this->sourcePriority((string) $location['source_type']) < $this->sourcePriority((string) $existing['source_type'])) {
                $location['deduped_from_count'] = isset($existing['deduped_from_count']) ? ((int) $existing['deduped_from_count']) + 1 : 0;
                $ranked[$dedupKey] = $location;
            } elseif ($existing !== null) {
                $ranked[$dedupKey]['deduped_from_count'] = ((int) ($ranked[$dedupKey]['deduped_from_count'] ?? 0)) + 1;
            }
        }

        return array_values($ranked);
    }

    /**
     * @param array<string, mixed> $location
     */
    private function dedupKey(array $location): string
    {
        if (!empty($location['external_source_key'])) {
            return sprintf('external:%s', (string) $location['external_source_key']);
        }

        $name = $this->normalizeText((string) ($location['location_name'] ?? ''));
        $address = $this->normalizeText((string) ($location['short_address'] ?? ''));
        $lat = is_numeric($location['lat'] ?? null) ? round((float) $location['lat'], 4) : 'na';
        $lng = is_numeric($location['lng'] ?? null) ? round((float) $location['lng'], 4) : 'na';

        return sprintf('physical:%s|%s|%s|%s', $name, $address, $lat, $lng);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function sourcePriority(string $sourceType): int
    {
        return match ($sourceType) {
            MerchantLocation::SOURCE_TYPE_OWNER_REGISTERED => 10,
            MerchantLocation::SOURCE_TYPE_CLAIMED => 20,
            MerchantLocation::SOURCE_TYPE_ADMIN_CURATED => 30,
            MerchantLocation::SOURCE_TYPE_FAKE_SEED => 40,
            MerchantLocation::SOURCE_TYPE_GOOGLE_PLACES => 50,
            default => 99,
        };
    }
}
