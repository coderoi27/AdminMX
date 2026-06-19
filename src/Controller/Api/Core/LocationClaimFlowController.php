<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\UpdateLocationClaimProgress;
use App\UseCase\Claim\RequestLocationClaimOtp;
use App\UseCase\Claim\ConfirmLocationClaimOtp;
use App\UseCase\Claim\IssueLocationClaimResumeToken;
use App\UseCase\Claim\ResolveLocationClaimResumeToken;
use App\UseCase\Claim\PrepareLocationClaimEvidenceUpload;
use App\UseCase\Claim\CompleteLocationClaimEvidenceUpload;
use App\UseCase\Claim\SubmitLocationClaim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/api/v1/location-claims')]
final class LocationClaimFlowController extends AbstractController
{
    #[Route('/{claimUuid}/progress', name: 'api_core_claim_progress', methods: ['PATCH'])]
    public function updateProgress(
        string $claimUuid,
        Request $request,
        EntityManagerInterface $em,
        UpdateLocationClaimProgress $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        
        try {
            $updatedClaim = $useCase->execute($claim, $payload);
            return $this->json(['data' => ['status' => $updatedClaim->getStatus(), 'last_completed_step' => $updatedClaim->getLastCompletedStep()]]);
        } catch (\DomainException $e) {
            return $this->json(['errors' => [$e->getMessage()]], 422);
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
            return $this->json(['errors' => [$e->getMessage()]], 422);
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
        } catch (\Exception $e) {
            return $this->json(['errors' => [$e->getMessage()]], 422);
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
        ResolveLocationClaimResumeToken $useCase
    ): JsonResponse {
        $result = $useCase->execute($token);
        
        if (!$result) {
            return $this->json(['errors' => ['invalid_resume_token']], 400);
        }

        return $this->json(['data' => [
            'claim_uuid' => $result['claim']->getClaimUuid(),
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'token_type' => $result['token_type'],
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
        
        try {
            $result = $useCase->execute(
                $claim,
                $payload['evidence_type'] ?? 'unknown',
                $payload['content_type'] ?? 'application/octet-stream',
                (int)($payload['size_bytes'] ?? 0),
                $payload['original_filename'] ?? 'upload.bin'
            );
            return $this->json(['data' => $result]);
        } catch (\DomainException $e) {
            return $this->json(['errors' => [$e->getMessage()]], 422);
        }
    }

    #[Route('/{claimUuid}/evidence/{evidenceId}/complete', name: 'api_core_claim_evidence_complete', methods: ['POST'])]
    public function completeUpload(
        string $claimUuid,
        int $evidenceId,
        EntityManagerInterface $em,
        CompleteLocationClaimEvidenceUpload $useCase
    ): JsonResponse {
        $claim = $em->getRepository(LocationClaimRequest::class)->findOneBy(['claimUuid' => $claimUuid]);
        if (!$claim) {
            return $this->json(['errors' => ['Claim not found']], 404);
        }

        try {
            $evidence = $useCase->execute($claim, $evidenceId);
            $reflection = new \ReflectionClass($evidence);
            
            return $this->json(['data' => [
                'evidence_id' => $evidenceId,
                'status' => $reflection->getProperty('status')->getValue($evidence),
            ]]);
        } catch (\Exception $e) {
            return $this->json(['errors' => [$e->getMessage()]], 422);
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
                'status' => $claim->getStatus()
            ]]);
        } catch (\DomainException $e) {
            return $this->json(['errors' => [$e->getMessage()]], 422);
        }
    }
}
