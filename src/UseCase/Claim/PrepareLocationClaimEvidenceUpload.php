<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\LocationClaimEvidence;
use App\Domain\Claim\ClaimEvidenceStorageInterface;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;

final class PrepareLocationClaimEvidenceUpload
{
    private const VIDEO_MIME_TYPES = ['video/webm', 'video/mp4', 'video/quicktime'];
    private const DOCUMENT_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const MIME_EXTENSIONS = [
        'video/webm' => ['webm'],
        'video/mp4' => ['mp4'],
        'video/quicktime' => ['mov', 'qt'],
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    private EntityManagerInterface $em;
    private ClaimEvidenceStorageInterface $storage;

    public function __construct(
        EntityManagerInterface $em,
        ClaimEvidenceStorageInterface $storage
    ) {
        $this->em = $em;
        $this->storage = $storage;
    }

    public function execute(LocationClaimRequest $claim, string $evidenceType, string $mimeType, int $sizeBytes, string $originalFilename, array $payload = []): array
    {
        if (!in_array($claim->getStatus(), [ClaimStateMachine::STATE_PENDING_EVIDENCE, ClaimStateMachine::STATE_NEEDS_INFO], true)) {
            throw new \DomainException('Cannot prepare upload in current state.');
        }

        $mimeType = $this->normalizeMimeType($mimeType);
        $evidenceType = $this->normalizeEvidenceType($evidenceType, $mimeType);
        $this->validateFile($evidenceType, $mimeType, $sizeBytes, $originalFilename);
        $metadata = $this->buildMetadata($claim, $payload, $mimeType, $sizeBytes);

        // 1. Prepare upload in storage
        $uploadData = $this->storage->prepareUpload($claim->getClaimUuid() ?? (string)$claim->getId(), $evidenceType, $mimeType, $sizeBytes);

        // 2. Create pending evidence entity
        $evidence = new LocationClaimEvidence();
        
        $reflection = new \ReflectionClass($evidence);
        $reflection->getProperty('claim')->setValue($evidence, $claim);
        $reflection->getProperty('evidenceType')->setValue($evidence, $evidenceType);
        $reflection->getProperty('storageProvider')->setValue($evidence, $uploadData['storage_provider']);
        $reflection->getProperty('bucketName')->setValue($evidence, $uploadData['bucket_name']);
        $reflection->getProperty('objectKey')->setValue($evidence, $uploadData['object_key']);
        $reflection->getProperty('originalFilename')->setValue($evidence, $originalFilename);
        $reflection->getProperty('mimeType')->setValue($evidence, $mimeType);
        $reflection->getProperty('sizeBytes')->setValue($evidence, $sizeBytes);
        $reflection->getProperty('durationSeconds')->setValue($evidence, $metadata['duration_seconds'] ?? null);
        $reflection->getProperty('metadataJson')->setValue($evidence, $metadata);
        $reflection->getProperty('status')->setValue($evidence, LocationClaimEvidence::STATUS_PENDING_UPLOAD);

        $this->em->persist($evidence);
        $this->em->flush();

        return [
            'evidence_id' => $evidence->getId(),
            'upload_url' => $uploadData['upload_url'],
            'required_headers' => [
                'Content-Type' => $mimeType,
            ],
            'upload_headers' => [
                'Content-Type' => $mimeType,
            ],
            'expires_in_seconds' => $uploadData['expires_in_seconds'] ?? null,
            'max_bytes' => $uploadData['max_bytes'] ?? null,
            'accepted_mime_types' => $this->acceptedMimeTypes($evidenceType),
        ];
    }

    private function buildMetadata(LocationClaimRequest $claim, array $payload, string $mimeType, int $sizeBytes): array
    {
        $captureMode = $this->enumString($payload['capture_mode'] ?? null, ['live_capture', 'uploaded_file'], 'uploaded_file');
        $metadata = [
            'capture_mode' => $captureMode,
            'has_audio' => $captureMode === 'live_capture' ? (bool) ($payload['has_audio'] ?? false) : null,
            'mime_type' => $mimeType,
            'original_mime_type' => $this->shortString($payload['original_mime_type'] ?? $payload['content_type'] ?? null, 160),
            'recorded_mime_type' => $this->shortString($payload['recorded_mime_type'] ?? null, 160),
            'browser_mime_type' => $this->shortString($payload['browser_mime_type'] ?? null, 160),
            'file_size' => $sizeBytes,
            'consent_reference' => $this->shortString($payload['consent_reference'] ?? null, 120),
            'capture_started_at' => $this->dateString($payload['capture_started_at'] ?? null),
            'capture_ended_at' => $this->dateString($payload['capture_ended_at'] ?? null),
            'duration_seconds' => $this->nullableInt($payload['duration_seconds'] ?? null, 0, 900),
            'geolocation_status' => $this->enumString($payload['geolocation_status'] ?? null, ['granted', 'denied', 'unavailable', 'timeout', 'unsupported', 'not_requested'], 'not_requested'),
            'location_signal' => 'unavailable',
            'client_metadata' => is_array($payload['client_metadata'] ?? null) ? array_intersect_key($payload['client_metadata'], ['media_recorder' => true, 'geolocation' => true, 'secure_context' => true, 'recorded_mime_type' => true, 'browser_mime_type' => true]) : [],
        ];

        if ($captureMode === 'live_capture') {
            if ($metadata['has_audio'] !== true) {
                throw new \DomainException('Live capture requires audio.');
            }
            if (($metadata['consent_reference'] ?? null) === null) {
                throw new \DomainException('Live capture requires explicit consent.');
            }
            $metadata = array_replace($metadata, $this->consumeChallenge($claim, $this->shortString($payload['challenge_reference'] ?? null, 64)));
        }

        if ($metadata['geolocation_status'] === 'granted') {
            $metadata['captured_latitude'] = $this->coordinate($payload['captured_latitude'] ?? null, -90, 90, 'latitude');
            $metadata['captured_longitude'] = $this->coordinate($payload['captured_longitude'] ?? null, -180, 180, 'longitude');
            $metadata['accuracy_meters'] = $this->nullableFloat($payload['accuracy_meters'] ?? null, 0, 50000, 'accuracy');
            $metadata['geolocation_captured_at'] = $this->dateString($payload['geolocation_captured_at'] ?? null);
            $metadata = array_replace($metadata, $this->distanceMetadata($claim, $metadata));
        }

        return array_filter($metadata, static fn (mixed $value): bool => $value !== null);
    }

    private function consumeChallenge(LocationClaimRequest $claim, ?string $reference): array
    {
        $prefill = $claim->getPrefillPayloadJson() ?? [];
        $challenge = is_array($prefill['live_evidence_challenge'] ?? null) ? $prefill['live_evidence_challenge'] : null;
        if (!$challenge || !is_string($challenge['reference'] ?? null) || !hash_equals($challenge['reference'], (string) $reference)) {
            throw new \DomainException('Live capture challenge is invalid.');
        }

        if (($challenge['status'] ?? '') !== 'issued') {
            throw new \DomainException('Live capture challenge is already used.');
        }

        $expiresAt = new \DateTimeImmutable((string) ($challenge['expires_at'] ?? 'now -1 second'));
        if (new \DateTimeImmutable() > $expiresAt) {
            $challenge['status'] = 'expired';
            $prefill['live_evidence_challenge'] = $challenge;
            $claim->setPrefillPayloadJson($prefill);
            throw new \DomainException('Live capture challenge expired.');
        }

        $challenge['status'] = 'used';
        $challenge['used_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $prefill['live_evidence_challenge'] = $challenge;
        $claim->setPrefillPayloadJson($prefill);

        return [
            'challenge_reference' => $challenge['reference'],
            'challenge_text' => $challenge['text'] ?? null,
            'challenge_status' => 'used',
            'consent_reference' => $challenge['consent_reference'] ?? null,
            'consent_accepted_at' => $challenge['consent_accepted_at'] ?? null,
        ];
    }

    private function distanceMetadata(LocationClaimRequest $claim, array $metadata): array
    {
        $expectedLat = $claim->getConfirmedLatitude();
        $expectedLng = $claim->getConfirmedLongitude();
        if ($expectedLat === null || $expectedLng === null) {
            return ['location_signal' => 'unavailable'];
        }

        $distance = $this->haversineMeters($expectedLat, $expectedLng, (float) $metadata['captured_latitude'], (float) $metadata['captured_longitude']);
        $accuracy = (float) ($metadata['accuracy_meters'] ?? 0);
        $signal = $accuracy > 1000 ? 'uncertain' : ($distance <= $accuracy + 100 ? 'near' : ($distance <= $accuracy + 500 ? 'uncertain' : 'far'));

        return [
            'expected_latitude' => $expectedLat,
            'expected_longitude' => $expectedLng,
            'distance_meters' => (int) round($distance),
            'distance_calculated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'location_signal' => $signal,
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalizeEvidenceType(string $evidenceType, string $mimeType): string
    {
        $evidenceType = trim($evidenceType);
        if (in_array($evidenceType, ['video', 'ownership_video'], true)) {
            return 'video';
        }

        if (in_array($evidenceType, ['document', 'ownership_document', 'business_license'], true)) {
            return 'document';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        throw new \DomainException('Unsupported evidence type.');
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType, 2)[0]));
    }

    private function validateFile(string $evidenceType, string $mimeType, int $sizeBytes, string $originalFilename): void
    {
        if ($sizeBytes <= 0) {
            throw new \DomainException('File size is required.');
        }

        if (!in_array($mimeType, $this->acceptedMimeTypes($evidenceType), true)) {
            throw new \DomainException('Unsupported MIME type.');
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::MIME_EXTENSIONS[$mimeType] ?? [], true)) {
            throw new \DomainException('Filename extension does not match MIME type.');
        }
    }

    /**
     * @return list<string>
     */
    private function acceptedMimeTypes(string $evidenceType): array
    {
        return $evidenceType === 'video' ? self::VIDEO_MIME_TYPES : self::DOCUMENT_MIME_TYPES;
    }

    private function enumString(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function shortString(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    private function dateString(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new \DomainException('Invalid duration.');
        }

        return $value;
    }

    private function nullableFloat(mixed $value, float $min, float $max, string $field): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (float) $value;
        if ($value < $min || $value > $max) {
            throw new \DomainException(sprintf('Invalid %s.', $field));
        }

        return $value;
    }

    private function coordinate(mixed $value, float $min, float $max, string $field): float
    {
        $coordinate = $this->nullableFloat($value, $min, $max, $field);
        if ($coordinate === null) {
            throw new \DomainException(sprintf('Missing %s.', $field));
        }

        return $coordinate;
    }
}
