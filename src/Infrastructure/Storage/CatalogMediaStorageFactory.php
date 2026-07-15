<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\CatalogMedia\CatalogMediaStorageInterface;

final class CatalogMediaStorageFactory
{
    public static function create(
        string $provider,
        LocalCatalogMediaStorage $localStorage,
        CloudflareR2CatalogMediaStorage $cloudflareR2Storage,
    ): CatalogMediaStorageInterface {
        return match (trim($provider)) {
            '', 'local' => $localStorage,
            'cloudflare_r2' => self::configuredCloudflareStorage($cloudflareR2Storage),
            default => throw new \RuntimeException(sprintf('Unsupported catalog media storage provider "%s".', $provider)),
        };
    }

    private static function configuredCloudflareStorage(CloudflareR2CatalogMediaStorage $storage): CloudflareR2CatalogMediaStorage
    {
        $storage->assertConfigured();

        return $storage;
    }
}
