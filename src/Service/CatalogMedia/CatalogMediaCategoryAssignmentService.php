<?php

declare(strict_types=1);

namespace App\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaModerationStatus;
use App\Domain\CatalogMedia\CatalogMediaStatus;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Entity\Core\CatalogMediaAsset;
use App\Entity\Core\CatalogMediaCategoryAssignment;
use App\Entity\Core\CatalogMediaUsagePool;
use App\Entity\Core\LocationCategory;
use Doctrine\ORM\EntityManagerInterface;

final class CatalogMediaCategoryAssignmentService
{
    private const PUBLIC_ASSET_BASE_URL = 'https://assets.mimonchis.mx/';
    private const SINGULAR_SLOTS = [
        CatalogMediaUsageSlot::CATEGORY_ICON,
        CatalogMediaUsageSlot::CATEGORY_DEFAULT,
        CatalogMediaUsageSlot::CATEGORY_COVER,
    ];
    private const POOL_SLOTS = [
        CatalogMediaUsageSlot::LOCATION_COVER,
        CatalogMediaUsageSlot::LOCATION_GALLERY,
        CatalogMediaUsageSlot::STORY_IMAGE,
        CatalogMediaUsageSlot::STORY_VIDEO,
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<CatalogMediaAsset>
     */
    public function pickerAssets(): array
    {
        return $this->entityManager->getRepository(CatalogMediaAsset::class)
            ->createQueryBuilder('asset')
            ->orderBy('asset.id', 'DESC')
            ->setMaxResults(160)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, CatalogMediaCategoryAssignment>
     */
    public function activeSingularAssignments(LocationCategory $category): array
    {
        $assignments = [];
        foreach ($this->activeAssignments($category, self::SINGULAR_SLOTS) as $assignment) {
            if ($assignment->getAsset() instanceof CatalogMediaAsset) {
                $assignments[$assignment->getUsageSlot()] = $assignment;
            }
        }

        return $assignments;
    }

    /**
     * @return array<string, list<CatalogMediaCategoryAssignment>>
     */
    public function activePoolAssignments(LocationCategory $category): array
    {
        $assignmentsBySlot = [];
        foreach ($this->activeAssignments($category, self::POOL_SLOTS) as $assignment) {
            if (!$assignment->getAsset() instanceof CatalogMediaAsset) {
                continue;
            }

            $assignmentsBySlot[$assignment->getUsageSlot()][] = $assignment;
        }

        foreach ($assignmentsBySlot as &$assignments) {
            usort(
                $assignments,
                static fn (CatalogMediaCategoryAssignment $left, CatalogMediaCategoryAssignment $right): int => $left->getPriority() <=> $right->getPriority()
            );
        }

        return $assignmentsBySlot;
    }

    /**
     * @param array<string, string|null> $singularSelections
     * @param array<string, list<string>> $poolSelections
     * @param list<string> $touchedSingularSlots
     * @param list<string> $touchedPoolSlots
     * @return list<string>
     */
    public function applySelections(
        LocationCategory $category,
        array $singularSelections,
        array $poolSelections,
        array $touchedSingularSlots,
        array $touchedPoolSlots,
    ): array {
        $errors = [];

        foreach (self::SINGULAR_SLOTS as $slot) {
            if (!in_array($slot, $touchedSingularSlots, true)) {
                continue;
            }

            $assetUuid = trim((string) ($singularSelections[$slot] ?? ''));
            $asset = $assetUuid !== '' ? $this->assetByUuid($assetUuid) : null;
            if ($assetUuid !== '' && !$asset instanceof CatalogMediaAsset) {
                $errors[] = sprintf('No se encontró el asset seleccionado para %s.', $this->labelForSlot($slot));
                continue;
            }

            if ($asset instanceof CatalogMediaAsset) {
                $issues = $this->selectionIssues($asset, $slot);
                if ($issues !== []) {
                    $errors[] = sprintf('%s no puede usar "%s": %s', $this->labelForSlot($slot), $asset->getTitle(), implode(' ', $issues));
                    continue;
                }
            }

            $this->replaceSingularAssignment($category, $slot, $asset);
        }

        foreach (self::POOL_SLOTS as $slot) {
            if (!in_array($slot, $touchedPoolSlots, true)) {
                continue;
            }

            $assets = [];
            foreach (array_values(array_unique($poolSelections[$slot] ?? [])) as $assetUuid) {
                $asset = $this->assetByUuid($assetUuid);
                if (!$asset instanceof CatalogMediaAsset) {
                    $errors[] = sprintf('No se encontró un asset seleccionado para el pool %s.', $this->labelForSlot($slot));
                    continue;
                }

                $issues = $this->selectionIssues($asset, $slot);
                if ($issues !== []) {
                    $errors[] = sprintf('%s no puede usar "%s": %s', $this->labelForSlot($slot), $asset->getTitle(), implode(' ', $issues));
                    continue;
                }

                $assets[] = $asset;
            }

            if ($errors === []) {
                $this->replacePoolAssignments($category, $slot, $assets);
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function selectionIssues(CatalogMediaAsset $asset, string $usageSlot): array
    {
        $issues = [];
        if ($asset->getStatus() !== CatalogMediaStatus::ACTIVE) {
            $issues[] = 'debe estar activo.';
        }
        if ($asset->getModerationStatus() !== CatalogMediaModerationStatus::APPROVED) {
            $issues[] = 'debe tener moderación aprobada.';
        }
        if (!$asset->isRightsVerified()) {
            $issues[] = 'debe tener derechos verificados.';
        }
        if ($asset->getChecksum() === null) {
            $issues[] = 'debe tener storage confirmado.';
        }
        if (!CatalogMediaUsageSlot::acceptsMediaType($usageSlot, $asset->getMediaType())) {
            $issues[] = 'el tipo de medio no es compatible con el slot.';
        }

        $publicUrl = $asset->getPublicUrl();
        if ($publicUrl === null || !str_starts_with($publicUrl, self::PUBLIC_ASSET_BASE_URL)) {
            $issues[] = 'la URL pública debe usar assets.mimonchis.mx.';
        }
        if ($publicUrl !== null && preg_match('#(/media/|admin\.mimonchis\.mx|r2\.cloudflarestorage\.com)#i', $publicUrl) === 1) {
            $issues[] = 'la URL pública no puede usar storage legacy, Admin ni endpoint R2.';
        }

        return $issues;
    }

    /**
     * @return list<array{slot:string,url:string}>
     */
    public function legacyWarnings(LocationCategory $category): array
    {
        $warnings = [];
        foreach ([
            CatalogMediaUsageSlot::CATEGORY_ICON => $category->getIconAssetUrl(),
            CatalogMediaUsageSlot::CATEGORY_DEFAULT => $category->getDefaultPhotoUrl(),
            CatalogMediaUsageSlot::CATEGORY_COVER => $category->getCoverPhotoUrl(),
        ] as $slot => $url) {
            if ($url !== null && preg_match('#(/media/categories/|admin\.mimonchis\.mx/media|mimonchis\.mx/media)#i', $url) === 1) {
                $warnings[] = ['slot' => $slot, 'url' => $url];
            }
        }

        return $warnings;
    }

    /**
     * @param list<string> $slots
     * @return list<CatalogMediaCategoryAssignment>
     */
    private function activeAssignments(LocationCategory $category, array $slots): array
    {
        if ($category->getId() === null) {
            return [];
        }

        return $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)
            ->createQueryBuilder('assignment')
            ->leftJoin('assignment.asset', 'asset')
            ->addSelect('asset')
            ->andWhere('assignment.category = :category')
            ->andWhere('assignment.usageSlot IN (:slots)')
            ->andWhere('assignment.active = true')
            ->setParameter('category', $category)
            ->setParameter('slots', $slots)
            ->orderBy('assignment.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function replaceSingularAssignment(LocationCategory $category, string $slot, ?CatalogMediaAsset $asset): void
    {
        $this->deactivateSlot($category, $slot);

        if ($asset instanceof CatalogMediaAsset) {
            $assignment = (new CatalogMediaCategoryAssignment())
                ->setCategory($category)
                ->setAsset($asset)
                ->setUsageSlot($slot)
                ->setPriority(0)
                ->setWeight(1)
                ->setActive(true);
            $assignment->assertValid();
            $this->entityManager->persist($assignment);
        }

        $this->projectLegacyUrl($category, $slot, $asset?->getPublicUrl());
    }

    /**
     * @param list<CatalogMediaAsset> $assets
     */
    private function replacePoolAssignments(LocationCategory $category, string $slot, array $assets): void
    {
        $this->deactivateSlot($category, $slot);
        if ($assets === []) {
            return;
        }

        $pool = $this->poolFor($category, $slot);
        if ($pool->getId() !== null) {
            $pool->setPoolVersion($pool->getPoolVersion() + 1);
        }
        $this->entityManager->persist($pool);

        foreach ($assets as $index => $asset) {
            $assignment = (new CatalogMediaCategoryAssignment())
                ->setCategory($category)
                ->setPool($pool)
                ->setAsset($asset)
                ->setUsageSlot($slot)
                ->setPriority($index)
                ->setWeight(1)
                ->setActive(true);
            $assignment->assertValid();
            $this->entityManager->persist($assignment);
        }
    }

    private function deactivateSlot(LocationCategory $category, string $slot): void
    {
        $assignments = $this->entityManager->getRepository(CatalogMediaCategoryAssignment::class)->findBy([
            'category' => $category,
            'usageSlot' => $slot,
            'active' => true,
        ]);

        foreach ($assignments as $assignment) {
            $assignment->setActive(false);
        }
    }

    private function poolFor(LocationCategory $category, string $slot): CatalogMediaUsagePool
    {
        $slug = sprintf('category-%s-%s', $category->getSlug(), str_replace('_', '-', $slot));
        $pool = $this->entityManager->getRepository(CatalogMediaUsagePool::class)->findOneBy(['slug' => $slug]);
        if ($pool instanceof CatalogMediaUsagePool) {
            return $pool
                ->setName(sprintf('%s / %s', $category->getName(), $this->labelForSlot($slot)))
                ->setUsageSlot($slot)
                ->setActive(true);
        }

        return (new CatalogMediaUsagePool())
            ->setName(sprintf('%s / %s', $category->getName(), $this->labelForSlot($slot)))
            ->setSlug($slug)
            ->setDescription('Pool de categoría administrado desde Biblioteca de medios.')
            ->setUsageSlot($slot)
            ->setActive(true)
            ->setPoolVersion(1);
    }

    private function projectLegacyUrl(LocationCategory $category, string $slot, ?string $publicUrl): void
    {
        match ($slot) {
            CatalogMediaUsageSlot::CATEGORY_ICON => $category->setIconAssetUrl($publicUrl),
            CatalogMediaUsageSlot::CATEGORY_DEFAULT => $category->setDefaultPhotoUrl($publicUrl),
            CatalogMediaUsageSlot::CATEGORY_COVER => $category->setCoverPhotoUrl($publicUrl),
            default => null,
        };
    }

    private function assetByUuid(string $uuid): ?CatalogMediaAsset
    {
        return $this->entityManager->getRepository(CatalogMediaAsset::class)->findOneBy(['uuid' => $uuid]);
    }

    private function labelForSlot(string $slot): string
    {
        return match ($slot) {
            CatalogMediaUsageSlot::CATEGORY_ICON => 'Icono de categoría',
            CatalogMediaUsageSlot::CATEGORY_DEFAULT => 'Foto default de categoría',
            CatalogMediaUsageSlot::CATEGORY_COVER => 'Cover de categoría',
            CatalogMediaUsageSlot::LOCATION_COVER => 'Portadas de establecimientos',
            CatalogMediaUsageSlot::LOCATION_GALLERY => 'Galería',
            CatalogMediaUsageSlot::STORY_IMAGE => 'Stories imagen',
            CatalogMediaUsageSlot::STORY_VIDEO => 'Stories video',
            default => $slot,
        };
    }
}
