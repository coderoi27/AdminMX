<?php

declare(strict_types=1);

namespace App\Tests\Service\CatalogMedia;

use App\Domain\CatalogMedia\CatalogMediaType;
use App\Domain\CatalogMedia\CatalogMediaUsageSlot;
use App\Service\CatalogMedia\CatalogMediaResolutionRequest;
use App\Service\CatalogMedia\CatalogMediaResolver;
use PHPUnit\Framework\TestCase;

final class CatalogMediaResolverTest extends TestCase
{
    private CatalogMediaResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CatalogMediaResolver();
    }

    public function testSameInputReturnsSameAssets(): void
    {
        $assets = $this->assets(8);
        $request = new CatalogMediaResolutionRequest('fake_seed', 'loc-1', 'tacos', CatalogMediaUsageSlot::LOCATION_GALLERY, 1, 5);

        self::assertSame(
            array_column($this->resolver->resolve($assets, $request), 'id'),
            array_column($this->resolver->resolve($assets, $request), 'id'),
        );
    }

    public function testDifferentLocationCanVarySelection(): void
    {
        $assets = $this->assets(8);
        $left = $this->resolver->resolve($assets, new CatalogMediaResolutionRequest('fake_seed', 'loc-1', 'tacos', CatalogMediaUsageSlot::LOCATION_GALLERY, 1, 5));
        $right = $this->resolver->resolve($assets, new CatalogMediaResolutionRequest('fake_seed', 'loc-2', 'tacos', CatalogMediaUsageSlot::LOCATION_GALLERY, 1, 5));

        self::assertNotSame(array_column($left, 'id'), array_column($right, 'id'));
    }

    public function testGalleryCapsAtFiveAndDoesNotDuplicate(): void
    {
        $resolved = $this->resolver->resolve(
            $this->assets(8),
            new CatalogMediaResolutionRequest('canonical', 'loc-1', 'pizza', CatalogMediaUsageSlot::LOCATION_GALLERY, 1, 10),
        );

        self::assertCount(5, $resolved);
        self::assertSame(array_column($resolved, 'id'), array_values(array_unique(array_column($resolved, 'id'))));
    }

    public function testPoolVersionFiltersSelection(): void
    {
        $assets = [
            ['id' => 'v1', 'url' => '/a.jpg', 'media_type' => CatalogMediaType::IMAGE, 'pool_version' => 1],
            ['id' => 'v2', 'url' => '/b.jpg', 'media_type' => CatalogMediaType::IMAGE, 'pool_version' => 2],
        ];

        $resolved = $this->resolver->resolve(
            $assets,
            new CatalogMediaResolutionRequest('canonical', 'loc-1', 'pizza', CatalogMediaUsageSlot::MAP_CARD, 2, 1),
        );

        self::assertSame(['v2'], array_column($resolved, 'id'));
    }

    public function testInsufficientPoolReturnsAvailableWithoutRepeating(): void
    {
        $resolved = $this->resolver->resolve(
            $this->assets(2),
            new CatalogMediaResolutionRequest('canonical', 'loc-1', 'pizza', CatalogMediaUsageSlot::LOCATION_GALLERY, 1, 5),
        );

        self::assertCount(2, $resolved);
        self::assertSame(array_column($resolved, 'id'), array_values(array_unique(array_column($resolved, 'id'))));
    }

    /**
     * @return list<array{id:string,url:string,media_type:string,pool_version:int}>
     */
    private function assets(int $count): array
    {
        $assets = [];
        for ($i = 1; $i <= $count; $i += 1) {
            $assets[] = [
                'id' => 'asset-'.$i,
                'url' => sprintf('/media/%d.jpg', $i),
                'media_type' => CatalogMediaType::IMAGE,
                'pool_version' => 1,
            ];
        }

        return $assets;
    }
}
