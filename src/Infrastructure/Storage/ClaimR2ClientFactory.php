<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Aws\S3\S3ClientInterface;
use Aws\S3\S3Client;

final class ClaimR2ClientFactory
{
    public static function create(
        string $endpoint,
        string $region,
        string $accessKeyId,
        string $secretAccessKey
    ): S3ClientInterface {
        return new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
        ]);
    }
}
