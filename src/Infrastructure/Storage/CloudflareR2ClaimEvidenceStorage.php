<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\Exception\ClaimObjectMetadataMismatchException;
use App\Domain\Claim\Exception\ClaimObjectNotFoundException;
use App\Domain\Claim\Exception\ClaimStorageUnavailableException;
use App\Domain\Claim\Exception\ClaimUploadPreparationException;
use Aws\S3\S3ClientInterface;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class CloudflareR2ClaimEvidenceStorage implements ClaimEvidenceStorageInterface
{
    private const MIME_TO_EXT = [
        'video/webm'       => 'webm',
        'video/mp4'        => 'mp4',
        'video/quicktime'  => 'mov',
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
    ];

    private S3ClientInterface $s3Client;
    private LoggerInterface $logger;
    private string $bucketName;
    private int $uploadTtl;
    private int $readTtl;
    private int $maxVideoBytes;
    private int $maxDocumentBytes;

    public function __construct(
        S3ClientInterface $s3Client,
        LoggerInterface $logger,
        string $bucketName,
        int $uploadTtl = 900,
        int $readTtl = 300,
        int $maxVideoBytes = 78643200,
        int $maxDocumentBytes = 10485760
    ) {
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        $this->bucketName = $bucketName;
        $this->uploadTtl = $uploadTtl;
        $this->readTtl = $readTtl;
        $this->maxVideoBytes = $maxVideoBytes;
        $this->maxDocumentBytes = $maxDocumentBytes;
    }

    public function prepareUpload(string $claimUuid, string $evidenceType, string $mimeType, int $sizeBytes): array
    {
        if (!isset(self::MIME_TO_EXT[$mimeType])) {
            throw new ClaimUploadPreparationException(sprintf('Unsupported MIME type: %s', $mimeType));
        }

        $isVideo = str_starts_with($mimeType, 'video/');
        $maxBytes = $isVideo ? $this->maxVideoBytes : $this->maxDocumentBytes;
        
        if ($sizeBytes > $maxBytes) {
            throw new ClaimUploadPreparationException(sprintf('File size exceeds the maximum allowed limit of %d bytes.', $maxBytes));
        }

        $extension = self::MIME_TO_EXT[$mimeType];
        $evidenceUuid = Uuid::v4()->toRfc4122();
        
        $now = new \DateTimeImmutable();
        // Private Claim evidence prefix. It intentionally avoids claimant email, local name and public media paths.
        $objectKey = sprintf(
            'claim-evidence/%s/%s/%s/%s/original.%s',
            $now->format('Y'),
            $now->format('m'),
            $claimUuid,
            $evidenceUuid,
            $extension
        );

        try {
            $cmd = $this->s3Client->getCommand('PutObject', [
                'Bucket' => $this->bucketName,
                'Key' => $objectKey,
                'ContentType' => $mimeType,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, sprintf('+%d seconds', $this->uploadTtl));

            $this->logger->info('Prepared R2 upload', [
                'claim_uuid' => $claimUuid,
                'object_key_hash' => $this->objectKeyHash($objectKey),
                'expires_in' => $this->uploadTtl
            ]);

            return [
                'upload_url' => (string) $request->getUri(),
                'expires_in_seconds' => $this->uploadTtl,
                'expires_at' => (new \DateTimeImmutable(sprintf('+%d seconds', $this->uploadTtl)))->format(\DateTimeInterface::ATOM),
                'required_headers' => [
                    'Content-Type' => $mimeType,
                ],
                'max_bytes' => $maxBytes,
                'accepted_mime_types' => array_keys(self::MIME_TO_EXT),
                'object_key' => $objectKey,
                'storage_provider' => 'cloudflare_r2',
                'bucket_name' => $this->bucketName,
            ];
        } catch (AwsException $e) {
            $this->logger->error('Failed to prepare R2 upload', ['exception' => $e->getMessage()]);
            throw new ClaimStorageUnavailableException('Could not communicate with Cloudflare R2.');
        }
    }

    public function inspectObject(string $objectKey): array
    {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucketName,
                'Key' => $objectKey,
            ]);

            $this->logger->info('Inspected R2 object', ['object_key_hash' => $this->objectKeyHash($objectKey)]);

            return [
                'object_key' => $objectKey,
                'size_bytes' => (int) $result['ContentLength'],
                'mime_type' => $result['ContentType'],
                'checksum_sha256' => trim($result['ETag'], '"'), // Standardize ETag string
                'last_modified' => $result['LastModified'] instanceof \DateTimeInterface 
                    ? $result['LastModified']->format(\DateTimeInterface::ATOM) 
                    : null
            ];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFound' || $e->getStatusCode() === 404) {
                throw new ClaimObjectNotFoundException('Evidence object not found in R2.');
            }
            
            $this->logger->error('Failed to inspect R2 object', ['object_key_hash' => $this->objectKeyHash($objectKey), 'exception_class' => $e::class]);
            throw new ClaimStorageUnavailableException('Could not communicate with Cloudflare R2.');
        }
    }

    public function createReadUrl(string $objectKey, int $expiresInSeconds = 3600): string
    {
        $ttl = $expiresInSeconds;

        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $objectKey,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, sprintf('+%d seconds', $ttl));
            
            $this->logger->info('Created R2 read URL', ['object_key_hash' => $this->objectKeyHash($objectKey)]);

            return (string) $request->getUri();
        } catch (AwsException $e) {
            $this->logger->error('Failed to create R2 read URL', ['object_key_hash' => $this->objectKeyHash($objectKey), 'exception_class' => $e::class]);
            throw new ClaimStorageUnavailableException('Could not communicate with Cloudflare R2.');
        }
    }

    public function deleteObject(string $objectKey): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $objectKey,
            ]);
            
            $this->logger->info('Deleted R2 object', ['object_key_hash' => $this->objectKeyHash($objectKey)]);
        } catch (AwsException $e) {
            $this->logger->error('Failed to delete R2 object', ['object_key_hash' => $this->objectKeyHash($objectKey), 'exception_class' => $e::class]);
            throw new ClaimStorageUnavailableException('Could not communicate with Cloudflare R2.');
        }
    }

    private function objectKeyHash(string $objectKey): string
    {
        return substr(hash('sha256', $objectKey), 0, 16);
    }
}
