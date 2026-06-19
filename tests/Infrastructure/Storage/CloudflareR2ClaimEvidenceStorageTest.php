<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Storage;

use App\Domain\Claim\Exception\ClaimUploadPreparationException;
use App\Infrastructure\Storage\CloudflareR2ClaimEvidenceStorage;
use Aws\S3\S3ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CloudflareR2ClaimEvidenceStorageTest extends TestCase
{
    private $s3Client;
    private $logger;
    private $storage;

    protected function setUp(): void
    {
        $this->s3Client = $this->createMock(S3ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->storage = new CloudflareR2ClaimEvidenceStorage(
            $this->s3Client,
            $this->logger,
            'test-bucket',
            900,
            300,
            78643200,
            10485760
        );
    }

    public function testPrepareUploadRejectsInvalidMime(): void
    {
        $this->expectException(ClaimUploadPreparationException::class);
        $this->storage->prepareUpload('uuid', 'doc', 'application/json', 100);
    }

    public function testPrepareUploadRejectsLargeVideo(): void
    {
        $this->expectException(ClaimUploadPreparationException::class);
        $this->storage->prepareUpload('uuid', 'video', 'video/mp4', 78643200 + 1);
    }

    public function testPrepareUploadRejectsLargeDocument(): void
    {
        $this->expectException(ClaimUploadPreparationException::class);
        $this->storage->prepareUpload('uuid', 'doc', 'application/pdf', 10485760 + 1);
    }
}
