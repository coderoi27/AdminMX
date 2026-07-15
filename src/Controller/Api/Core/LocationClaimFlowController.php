<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationClaimEvidence;
use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\UpdateLocationClaimProgress;
use App\UseCase\Claim\RequestLocationClaimOtp;
use App\UseCase\Claim\ConfirmLocationClaimOtp;
use App\UseCase\Claim\IssueLocationClaimResumeToken;
use App\UseCase\Claim\ResolveLocationClaimResumeToken;
use App\UseCase\Claim\PrepareLocationClaimEvidenceUpload;
use App\UseCase\Claim\CompleteLocationClaimEvidenceUpload;
use App\UseCase\Claim\SubmitLocationClaim;
use App\Domain\Claim\Exception\ClaimOtpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/location-claims')]
final class LocationClaimFlowController extends AbstractController
{
    #[Route('/{claimUuid}/progress', name: 'api_core_claim_progress', methods: ['PATCH'])]
    public function updateProgress(
        string $claimUuid,
        Request $request,
        EntityManagerInterface $em,
        UpdateLocationClaimProgress $useCase,
        LoggerInterface $logger
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        
        try {
            $updatedClaim = $useCase->execute($claim, $payload);
            return $this->json(['data' => [
                'status' => $updatedClaim->getStatus(),
                'last_completed_step' => $updatedClaim->getLastCompletedStep(),
            ]]);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'claim_evidence_required') {
                return $this->claimError('claim_evidence_required', 'Completa la evidencia antes de aceptar los términos.', 422);
            }

            $requestId = bin2hex(random_bytes(12));
            $logger->warning('Claim progress validation failed.', [
                'operation' => 'claim_progress_update',
                'request_id' => $requestId,
                'claim_uuid' => $claimUuid,
                'status' => $claim->getStatus(),
                'exception_class' => $e::class,
            ]);

            return $this->json([
                'error' => 'claim_progress_invalid',
                'error_code' => 'claim_progress_invalid',
                'request_id' => $requestId,
                'message' => 'No fue posible validar el avance de la reclamación.',
            ], 422);
        } catch (\Throwable $e) {
            $requestId = bin2hex(random_bytes(12));
            $logger->error('Claim progress update failed.', [
                'operation' => 'claim_progress_update',
                'request_id' => $requestId,
                'claim_uuid' => $claimUuid,
                'status' => $claim->getStatus(),
                'exception_class' => $e::class,
            ]);

            return $this->json([
                'error' => 'claim_progress_failed',
                'error_code' => 'claim_progress_failed',
                'request_id' => $requestId,
                'message' => 'No fue posible guardar el avance de la reclamación.',
            ], 500);
        }
    }

    #[Route('/{claimUuid}/email-otp/request', name: 'api_core_claim_otp_request', methods: ['POST'])]
    public function requestOtp(
        string $claimUuid,
        EntityManagerInterface $em,
        RequestLocationClaimOtp $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        try {
            $result = $useCase->execute($claim);
            return $this->json(['data' => $result]);
        } catch (\DomainException $e) {
            return $this->claimError('claim_invalid_state', 'No se puede solicitar un código en el estado actual.', 422);
        }
    }

    #[Route('/{claimUuid}/email-otp/confirm', name: 'api_core_claim_otp_confirm', methods: ['POST'])]
    public function confirmOtp(
        string $claimUuid,
        Request $request,
        EntityManagerInterface $em,
        ConfirmLocationClaimOtp $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (empty($payload['code'])) {
            return $this->json(['errors' => ['Missing code']], 400);
        }

        try {
            $result = $useCase->execute($claim, $payload['code']);
            return $this->json(['data' => $result]);
        } catch (ClaimOtpException $e) {
            return $this->claimError($e->errorCode, $e->safeMessage, $e->httpStatus);
        } catch (\DomainException $e) {
            return $this->claimError('claim_invalid_state', 'No se puede confirmar el código en el estado actual.', 422);
        }
    }

    #[Route('/resume-link', name: 'api_core_claim_resume_link', methods: ['POST'])]
    public function requestResumeLink(
        Request $request,
        IssueLocationClaimResumeToken $useCase
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!empty($payload['email'])) {
            $useCase->execute($payload['email']);
        }
        
        // Prevent enumeration: always return same response
        return $this->json([
            'data' => ['message' => 'If the email exists and has an active claim, a resume link has been sent.']
        ]);
    }

    #[Route('/resume/{token}', name: 'api_core_claim_resume_token', methods: ['GET'])]
    public function resolveResumeToken(
        string $token,
        ResolveLocationClaimResumeToken $useCase,
        EntityManagerInterface $em
    ): JsonResponse {
        $result = $useCase->execute($token);
        
        if (!$result) {
            return $this->claimError('invalid_or_expired_link', 'El enlace ya venció o fue usado.', 400);
        }

        return $this->json(['data' => [
            'claim_uuid' => $result['claim']->getClaimUuid(),
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'token_type' => $result['token_type'],
            'progress' => $this->claimProgressPayload($result['claim'], $em),
        ]]);
    }

    #[Route('/{claimUuid}', name: 'api_core_claim_show', methods: ['GET'])]
    public function showClaim(string $claimUuid, EntityManagerInterface $em): JsonResponse
    {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        return $this->json(['data' => $this->claimProgressPayload($claim, $em)]);
    }

    #[Route('/{claimUuid}/evidence/challenge', name: 'api_core_claim_evidence_challenge', methods: ['POST'])]
    public function issueEvidenceChallenge(
        string $claimUuid,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $consentReference = is_string($payload['consent_reference'] ?? null)
            ? mb_substr(trim($payload['consent_reference']), 0, 120)
            : 'claim_live_evidence_consent:v1';
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $reference = bin2hex(random_bytes(12));
        $today = (new \DateTimeImmutable())->format('d/m/Y');
        $text = sprintf('Hola Mi Monchis. Estoy grabando desde mi local hoy %s. Mi código es %s.', $today, $code);

        $prefill = $claim->getPrefillPayloadJson() ?? [];
        $prefill['live_evidence_challenge'] = [
            'reference' => $reference,
            'code_hash' => hash('sha256', $code),
            'text' => $text,
            'status' => 'issued',
            'issued_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'expires_at' => (new \DateTimeImmutable('+15 minutes'))->format(\DateTimeInterface::ATOM),
            'consent_reference' => $consentReference,
            'consent_accepted_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $claim->setPrefillPayloadJson($prefill);
        $em->flush();

        return $this->json(['data' => [
            'reference' => $reference,
            'text' => $text,
            'expires_in_seconds' => 900,
            'consent_reference' => $consentReference,
        ]]);
    }

    #[Route('/{claimUuid}/evidence/uploads', name: 'api_core_claim_evidence_prepare', methods: ['POST'])]
    public function prepareUpload(
        string $claimUuid,
        Request $request,
        EntityManagerInterface $em,
        PrepareLocationClaimEvidenceUpload $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $contentType = $payload['content_type'] ?? $payload['mime_type'] ?? 'application/octet-stream';
        $originalFilename = $payload['original_filename'] ?? $payload['filename'] ?? $payload['file_name'] ?? 'upload.bin';
        $sizeBytes = (int) ($payload['size_bytes'] ?? $payload['file_size'] ?? 0);
        
        try {
            $result = $useCase->execute(
                $claim,
                $payload['evidence_type'] ?? 'unknown',
                is_string($contentType) ? $contentType : 'application/octet-stream',
                $sizeBytes,
                is_string($originalFilename) ? $originalFilename : 'upload.bin',
                $payload
            );
            return $this->json(['data' => $this->safePrepareUploadPayload($result)]);
        } catch (\DomainException $e) {
            [$errorCode, $message] = $this->evidencePrepareError($e);

            return $this->claimError($errorCode, $message, 422);
        }
    }

    #[Route('/{claimUuid}/evidence/{evidenceId}/complete', name: 'api_core_claim_evidence_complete', methods: ['POST'])]
    public function completeUpload(
        string $claimUuid,
        int $evidenceId,
        Request $request,
        EntityManagerInterface $em,
        CompleteLocationClaimEvidenceUpload $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        try {
            $payload = json_decode($request->getContent(), true) ?? [];
            $etag = is_string($payload['etag'] ?? null) ? $payload['etag'] : null;
            $evidence = $useCase->execute($claim, $evidenceId, $etag);
            $reflection = new \ReflectionClass($evidence);
            
            return $this->json(['data' => [
                'evidence_id' => $evidenceId,
                'status' => $reflection->getProperty('status')->getValue($evidence),
            ]]);
        } catch (\Exception $e) {
            return $this->claimError('claim_evidence_complete_failed', 'El archivo se subió, pero no pudimos confirmarlo.', 422);
        }
    }

    #[Route('/{claimUuid}/submit', name: 'api_core_claim_submit', methods: ['POST'])]
    public function submitClaim(
        string $claimUuid,
        EntityManagerInterface $em,
        SubmitLocationClaim $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        try {
            $useCase->execute($claim);
            return $this->json(['data' => [
                'claim_uuid' => $claimUuid,
                'status' => $claim->getStatus(),
                'public_reference' => $this->publicReference($claim),
                'location_name' => $claim->getLocationName(),
                'submitted_at' => $claim->getSubmittedAt()?->format(\DateTimeInterface::ATOM),
            ]]);
        } catch (\DomainException $e) {
            [$errorCode, $message] = $this->submitError($e);

            return $this->claimError($errorCode, $message, 422);
        }
    }

    private function claimProgressPayload(LocationClaimRequest $claim, EntityManagerInterface $em): array
    {
        $evidenceSummary = $this->evidenceSummary($claim, $em);

        return [
            'status' => $claim->getStatus(),
            'last_completed_step' => $claim->getLastCompletedStep(),
            'next_step' => $this->resolveNextStep($claim, $evidenceSummary),
            'email_verified' => $claim->getEmailVerifiedAt() !== null,
            'claimant_name' => $claim->getClaimantName(),
            'claimant_role' => $claim->getClaimantRole(),
            'email' => $claim->getEmail(),
            'location_name' => $claim->getLocationName(),
            'legal_accepted' => $claim->getLegalAcceptanceReference() !== null,
            'evidence_summary' => $evidenceSummary,
        ];
    }

    /** @param array<string, mixed> $evidenceSummary */
    private function resolveNextStep(LocationClaimRequest $claim, array $evidenceSummary): string
    {
        if ($claim->getStatus() === LocationClaimRequest::STATUS_SUBMITTED) {
            return 'confirmation';
        }

        if ($claim->getClaimantName() === null || $claim->getEmail() === null) {
            return 'claimant';
        }

        if ($claim->getEmailVerifiedAt() === null) {
            return 'otp';
        }

        if (($evidenceSummary['has_completed_evidence'] ?? false) !== true) {
            return 'evidence';
        }

        if ($claim->getLegalAcceptanceReference() !== null) {
            return 'summary';
        }

        return 'legal';
    }

    /** @return array{has_completed_evidence: bool, status: ?string, capture_mode: ?string, geolocation_status: ?string} */
    private function evidenceSummary(LocationClaimRequest $claim, EntityManagerInterface $em): array
    {
        $evidence = $em->getRepository(LocationClaimEvidence::class)->findOneBy(
            [
                'claim' => $claim,
                'status' => [LocationClaimEvidence::STATUS_UPLOADED, LocationClaimEvidence::STATUS_VERIFIED],
            ],
            ['uploadedAt' => 'DESC', 'createdAt' => 'DESC'],
        );

        if (!$evidence instanceof LocationClaimEvidence) {
            return [
                'has_completed_evidence' => false,
                'status' => null,
                'capture_mode' => null,
                'geolocation_status' => null,
            ];
        }

        $metadata = $evidence->getMetadataJson() ?? [];

        return [
            'has_completed_evidence' => true,
            'status' => $evidence->getStatus(),
            'capture_mode' => is_string($metadata['capture_mode'] ?? null) ? $metadata['capture_mode'] : null,
            'geolocation_status' => is_string($metadata['geolocation_status'] ?? null) ? $metadata['geolocation_status'] : null,
        ];
    }

    private function claimError(string $errorCode, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => $errorCode,
            'error_code' => $errorCode,
            'message' => $message,
        ], $status);
    }

    private function publicReference(LocationClaimRequest $claim): string
    {
        $source = $claim->getClaimUuid() ?: (string) $claim->getId();

        return 'MM-' . strtoupper(substr(hash('sha256', $source), 0, 10));
    }

    /** @return array{0: string, 1: string} */
    private function submitError(\DomainException $exception): array
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'Email')) {
            return ['claim_email_not_verified', 'Verifica tu correo antes de enviar la solicitud.'];
        }

        if (str_contains($message, 'Legal')) {
            return ['claim_legal_acceptance_required', 'Acepta los términos antes de enviar la solicitud.'];
        }

        if (str_contains($message, 'evidence')) {
            return ['claim_evidence_required', 'Completa la evidencia antes de enviar la solicitud.'];
        }

        if (str_contains($message, 'current state')) {
            return ['claim_invalid_state', 'La solicitud no está lista para enviarse.'];
        }

        return ['claim_submit_failed', 'No pudimos enviar la solicitud. Intenta de nuevo.'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function evidencePrepareError(\DomainException $exception): array
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'current state')) {
            return ['claim_evidence_invalid_state', 'No puedes subir evidencia en el estado actual de la reclamación.'];
        }

        if (str_contains($message, 'size')) {
            return ['claim_evidence_invalid_size', 'El archivo supera el tamaño permitido.'];
        }

        if (str_contains($message, 'duration')) {
            return ['claim_evidence_invalid_duration', 'El video supera la duración permitida.'];
        }

        if (str_contains($message, 'audio')) {
            return ['claim_evidence_missing_audio', 'Necesitamos audio para la grabación guiada. Intenta grabarlo nuevamente.'];
        }

        if (str_contains($message, 'consent')) {
            return ['claim_evidence_invalid_consent', 'Confirma el consentimiento de cámara, micrófono y ubicación antes de grabar.'];
        }

        if (str_contains($message, 'challenge expired')) {
            return ['claim_evidence_challenge_expired', 'El reto de verificación expiró. Genera uno nuevo.'];
        }

        if (str_contains($message, 'challenge')) {
            return ['claim_evidence_challenge_expired', 'El reto de verificación expiró. Genera uno nuevo.'];
        }

        return ['claim_evidence_invalid_mime', 'No pudimos preparar este video. Intenta grabarlo nuevamente.'];
    }

    private function safePrepareUploadPayload(array $result): array
    {
        $safe = array_intersect_key($result, [
            'evidence_id' => true,
            'evidence_upload_id' => true,
            'upload_url' => true,
            'required_headers' => true,
            'upload_headers' => true,
            'expires_in_seconds' => true,
            'max_bytes' => true,
            'accepted_mime_types' => true,
        ]);

        if (!isset($safe['upload_headers']) && isset($safe['required_headers'])) {
            $safe['upload_headers'] = $safe['required_headers'];
        }

        return $safe;
    }
}
