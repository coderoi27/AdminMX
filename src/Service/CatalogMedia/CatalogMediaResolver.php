<?php

declare(strict_types=1);

namespace App\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaUsageSlot;

final class CatalogMediaResolver
{
    /**
     * @param list<array{id:string|int,url?:string|null,media_type:string,priority?:int,weight?:int,active?:bool,pool_version?:int}> $assets
     * @return list<array{id:string|int,url?:string|null,media_type:string,priority?:int,weight?:int,active?:bool,pool_version?:int}>
     */
    public function resolve(array $assets, CatalogMediaResolutionRequest $request): array
    {
        $limit = min($request->requestedCount, $request->usageSlot === CatalogMediaUsageSlot::LOCATION_GALLERY ? 5 : $request->requestedCount);
        $seed = hash('sha256', implode('|', [
            $request->sourceType,
            $request->locationIdentity,
            $request->categoryIdentity,
            $request->usageSlot,
            (string) $request->poolVersion,
        ]));

        $eligible = [];
        foreach ($assets as $asset) {
            if (($asset['active'] ?? true) !== true) {
                continue;
            }

            if (isset($asset['pool_version']) && (int) $asset['pool_version'] !== $request->poolVersion) {
                continue;
            }

            CatalogMediaUsageSlot::assertAcceptsMediaType($request->usageSlot, (string) $asset['media_type']);
            $eligible[$this->assetId($asset)] = $asset;
        }

        $eligible = array_values($eligible);
        usort($eligible, static function (array $left, array $right) use ($seed): int {
            $priority = ((int) ($right['priority'] ?? 0)) <=> ((int) ($left['priority'] ?? 0));
            if ($priority !== 0) {
                return $priority;
            }

            $weight = ((int) ($right['weight'] ?? 0)) <=> ((int) ($left['weight'] ?? 0));
            if ($weight !== 0) {
                return $weight;
            }

            $leftHash = hash('sha256', $seed.'|'.(string) $left['id']);
            $rightHash = hash('sha256', $seed.'|'.(string) $right['id']);

            return $leftHash <=> $rightHash;
        });

        return array_slice($eligible, 0, $limit);
    }

    /**
     * @param array{id:string|int} $asset
     */
    private function assetId(array $asset): string
    {
        return (string) $asset['id'];
    }
}
