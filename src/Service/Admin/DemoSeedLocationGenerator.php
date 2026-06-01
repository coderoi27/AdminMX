<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Core\DemoSeedBatch;
use App\Entity\Core\DemoSeedBatchItem;
use App\Entity\Core\LocationCategory;
use App\Entity\Core\Merchant;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\PlaceAddress;
use Doctrine\ORM\EntityManagerInterface;

final class DemoSeedLocationGenerator
{
    /**
     * @return array{generated:int, categories:list<string>}
     */
    public function generate(DemoSeedBatch $batch, EntityManagerInterface $entityManager): array
    {
        $plugin = $batch->getPlugin();
        if (!$plugin->isEnabled()) {
            throw new \RuntimeException('El plugin demo está desactivado. Actívalo antes de sembrar una tanda.');
        }

        if (in_array($batch->getStatus(), [DemoSeedBatch::STATUS_DISABLED, DemoSeedBatch::STATUS_EXPIRED, DemoSeedBatch::STATUS_PURGED], true)) {
            throw new \RuntimeException('La tanda no se puede sembrar en su estado actual.');
        }

        $centerLat = $batch->getCenterLatitude();
        $centerLng = $batch->getCenterLongitude();
        if ($centerLat === null || $centerLng === null || !is_numeric($centerLat) || !is_numeric($centerLng)) {
            throw new \RuntimeException('La tanda necesita coordenadas centro válidas para poder sembrar locales demo.');
        }

        $remaining = max(0, $batch->getRequestedLocationsCount() - $batch->getGeneratedLocationsCount());
        if ($remaining <= 0) {
            throw new \RuntimeException('Esta tanda ya no tiene locales pendientes por generar.');
        }

        $categoryRepository = $entityManager->getRepository(LocationCategory::class);
        $selectedSlugs = $batch->getCategorySlugs();
        $categories = $selectedSlugs === []
            ? $categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC'])
            : $categoryRepository->findBy(['slug' => $selectedSlugs], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        if ($categories === []) {
            throw new \RuntimeException('La tanda no tiene categorías activas válidas para sembrar locales demo.');
        }

        $generated = 0;
        $usedCategorySlugs = [];

        for ($index = 0; $index < $remaining; $index += 1) {
            $category = $categories[$index % count($categories)];
            $coordinates = $this->randomPointWithinRadius(
                (float) $centerLat,
                (float) $centerLng,
                $batch->getRadiusMeters()
            );

            $merchantName = $this->buildMerchantName($batch, $category, $index + $batch->getGeneratedLocationsCount() + 1);
            $locationName = $this->buildLocationName($batch, $category, $index + $batch->getGeneratedLocationsCount() + 1);

            $merchant = new Merchant();
            $merchant
                ->setName($merchantName)
                ->setSlug($this->buildUniqueSlug($entityManager, Merchant::class, $merchantName))
                ->setStatus(MerchantLocation::STATUS_ACTIVE)
                ->setDescription($this->merchantDescription($batch, $category));
            $entityManager->persist($merchant);

            $location = new MerchantLocation();
            $location
                ->setMerchant($merchant)
                ->setName($locationName)
                ->setSlug($this->buildUniqueSlug($entityManager, MerchantLocation::class, $locationName))
                ->setPrimaryCategory($category)
                ->setLocationType('fixed')
                ->setStatus(MerchantLocation::STATUS_ACTIVE)
                ->setSourceType(MerchantLocation::SOURCE_TYPE_FAKE_SEED)
                ->setPublicationState(MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE)
                ->setShortDescription($this->locationDescription($batch, $category))
                ->setWhatsappEnabled(true)
                ->setWhatsappE164($this->demoWhatsapp($index))
                ->setIsClaimable(false);

            $address = (new PlaceAddress())
                ->setLabel('Demo')
                ->setCountryCode($batch->getCountryCode())
                ->setState($batch->getState())
                ->setCity($batch->getCity())
                ->setNeighborhood($this->buildNeighborhood($batch, $category, $index))
                ->setStreet($this->buildStreet($category, $index))
                ->setExtNumber((string) ($index + 11))
                ->setZipCode($this->buildZipCode($index))
                ->setReference($batch->getRegionLabel() ?: $batch->getName())
                ->setLatitude(number_format($coordinates['lat'], 7, '.', ''))
                ->setLongitude(number_format($coordinates['lng'], 7, '.', ''))
                ->setIsPrimary(true);

            $location->addAddress($address);
            $entityManager->persist($location);

            $item = new DemoSeedBatchItem();
            $item
                ->setDemoSeedBatch($batch)
                ->setMerchantLocation($location)
                ->setStatus(DemoSeedBatchItem::STATUS_ACTIVE)
                ->setGeneratedAt(new \DateTimeImmutable())
                ->setNotes(sprintf('Sembrado desde la tanda "%s".', $batch->getName()));
            $entityManager->persist($item);

            $generated += 1;
            $usedCategorySlugs[] = $category->getSlug();
        }

        $batch
            ->setGeneratedLocationsCount($batch->getGeneratedLocationsCount() + $generated)
            ->setSeededAt(new \DateTimeImmutable())
            ->setStatus(DemoSeedBatch::STATUS_ACTIVE);

        $plugin->setLastRunAt(new \DateTimeImmutable());

        $entityManager->flush();

        return [
            'generated' => $generated,
            'categories' => array_values(array_unique($usedCategorySlugs)),
        ];
    }

    /**
     * @return array{lat:float,lng:float}
     */
    private function randomPointWithinRadius(float $centerLat, float $centerLng, int $radiusMeters): array
    {
        $distance = sqrt(mt_rand() / mt_getrandmax()) * $radiusMeters;
        $bearing = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
        $earthRadius = 6371000.0;

        $lat1 = deg2rad($centerLat);
        $lng1 = deg2rad($centerLng);
        $angularDistance = $distance / $earthRadius;

        $lat2 = asin(
            sin($lat1) * cos($angularDistance)
            + cos($lat1) * sin($angularDistance) * cos($bearing)
        );
        $lng2 = $lng1 + atan2(
            sin($bearing) * sin($angularDistance) * cos($lat1),
            cos($angularDistance) - sin($lat1) * sin($lat2)
        );

        return [
            'lat' => rad2deg($lat2),
            'lng' => rad2deg($lng2),
        ];
    }

    private function buildMerchantName(DemoSeedBatch $batch, LocationCategory $category, int $sequence): string
    {
        $prefix = $this->categoryMerchantPrefix($category);

        return sprintf('%s %s %02d', $prefix, $batch->getCity() ?: 'Demo', $sequence);
    }

    private function buildLocationName(DemoSeedBatch $batch, LocationCategory $category, int $sequence): string
    {
        $suffix = $batch->getRegionLabel() ?: ($batch->getCity() ?: 'Zona Demo');

        return sprintf('%s %s %02d', $category->getName(), $suffix, $sequence);
    }

    private function merchantDescription(DemoSeedBatch $batch, LocationCategory $category): string
    {
        return sprintf(
            'Merchant demo sembrado para la narrativa %s dentro de la categoría %s.',
            $batch->getName(),
            $category->getName()
        );
    }

    private function locationDescription(DemoSeedBatch $batch, LocationCategory $category): string
    {
        $city = $batch->getCity() ?: 'la zona demo';

        return sprintf('Local demo de %s generado para ensayos de densidad y exploración pública en %s.', $category->getName(), $city);
    }

    private function categoryMerchantPrefix(LocationCategory $category): string
    {
        $slug = $category->getSlug();

        return match (true) {
            str_contains($slug, 'taco') => 'Tacos',
            str_contains($slug, 'veg') => 'Verde',
            str_contains($slug, 'cafe') => 'Café',
            default => 'Monchis',
        };
    }

    private function buildNeighborhood(DemoSeedBatch $batch, LocationCategory $category, int $index): string
    {
        $base = $batch->getRegionLabel() ?: ($batch->getCity() ?: 'Centro');

        return sprintf('%s %s', $base, $this->labelSuffix($category, $index));
    }

    private function buildStreet(LocationCategory $category, int $index): string
    {
        $base = match (true) {
            str_contains($category->getSlug(), 'taco') => 'Calle Maíz',
            str_contains($category->getSlug(), 'veg') => 'Calle Huerto',
            str_contains($category->getSlug(), 'cafe') => 'Calle Grano',
            default => 'Calle Sabor',
        };

        return sprintf('%s %d', $base, ($index % 25) + 1);
    }

    private function buildZipCode(int $index): string
    {
        return str_pad((string) (10000 + (($index * 37) % 89999)), 5, '0', STR_PAD_LEFT);
    }

    private function demoWhatsapp(int $index): string
    {
        return '+521551' . str_pad((string) (($index * 731) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function labelSuffix(LocationCategory $category, int $index): string
    {
        $suffixes = match (true) {
            str_contains($category->getSlug(), 'taco') => ['Taquera', 'Antojera', 'Nocturna'],
            str_contains($category->getSlug(), 'veg') => ['Fresca', 'Botánica', 'Natural'],
            str_contains($category->getSlug(), 'cafe') => ['Tostado', 'Brunch', 'Moka'],
            default => ['Sabores', 'Mercado', 'Barrio'],
        };

        return $suffixes[$index % count($suffixes)];
    }

    private function buildUniqueSlug(EntityManagerInterface $entityManager, string $entityClass, string $value): string
    {
        $baseSlug = $this->slugify($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'registro';
        $suffix = 2;
        $repository = $entityManager->getRepository($entityClass);

        while ($repository->findOneBy(['slug' => $slug]) !== null) {
            $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'registro', $suffix);
            $suffix += 1;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
