<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\Exception\ClaimStorageConfigurationException;
use Psr\Log\LoggerInterface;

final class ClaimEvidenceStorageFactory
{
    public static function create(
        string $provider,
        InMemoryClaimEvidenceStorage $inMemoryStorage,
        CloudflareR2ClaimEvidenceStorage $r2Storage,
        LoggerInterface $logger
    ): ClaimEvidenceStorageInterface {
        if ($provider === 'in_memory') {
            $logger->info('Using InMemoryClaimEvidenceStorage as ClaimEvidenceStorageInterface');
            return $inMemoryStorage;
        }

        if ($provider === 'cloudflare_r2') {
            $logger->info('Using CloudflareR2ClaimEvidenceStorage as ClaimEvidenceStorageInterface');
            return $r2Storage;
        }

        throw new ClaimStorageConfigurationException(sprintf(
            'Invalid CLAIM_STORAGE_PROVIDER "%s". Valid options are "in_memory" and "cloudflare_r2". No fallback is permitted.',
            $provider
        ));
    }
}
