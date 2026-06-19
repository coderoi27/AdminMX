<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\Claim\ClaimAccessSessionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ClaimAccessTokenAuthenticator extends AbstractAuthenticator
{
    private ClaimAccessSessionManager $sessionManager;

    private const PROTECTED_ROUTES = [
        'api_core_claim_progress' => 'claim:write',
        'api_core_claim_evidence_prepare' => 'claim:evidence',
        'api_core_claim_evidence_complete' => 'claim:evidence',
        'api_core_claim_submit' => 'claim:submit',
    ];

    public function __construct(ClaimAccessSessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function supports(Request $request): ?bool
    {
        $routeName = $request->attributes->get('_route');
        return isset(self::PROTECTED_ROUTES[$routeName]);
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
            throw new CustomUserMessageAuthenticationException('claim_authentication_required');
        }

        $tokenPlain = $matches[1];

        $session = $this->sessionManager->resolveToken($tokenPlain);

        if (!$session) {
            throw new CustomUserMessageAuthenticationException('claim_session_invalid');
        }

        // Verify that the token's claim matches the URL's claimUuid
        $requestClaimUuid = $request->attributes->get('claimUuid');
        if ($requestClaimUuid && $session->getClaim()->getClaimUuid() !== $requestClaimUuid) {
            throw new CustomUserMessageAuthenticationException('claim_access_denied');
        }

        // Verify scope
        $routeName = $request->attributes->get('_route');
        $requiredScope = self::PROTECTED_ROUTES[$routeName] ?? null;

        if ($requiredScope && !in_array($requiredScope, $session->getScopes(), true)) {
            throw new CustomUserMessageAuthenticationException('claim_scope_forbidden');
        }

        $userIdentifier = 'claim_session_' . $session->getId();

        return new SelfValidatingPassport(new UserBadge($userIdentifier, function() use ($session) {
            return new AuthenticatedClaimContext(
                $session->getClaim()->getClaimUuid(),
                $session->getClaim()->getId(),
                $session->getClaim()->getEmailVerifiedAt() !== null,
                $session->getScopes(),
                $session->getId(),
                $session->getExpiresAt()
            );
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Continue request processing
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $errorKey = $exception->getMessageKey();
        
        // Map our custom message keys to appropriate HTTP status codes
        $status = Response::HTTP_UNAUTHORIZED;
        if (in_array($errorKey, ['claim_access_denied', 'claim_scope_forbidden'], true)) {
            $status = Response::HTTP_FORBIDDEN;
        }

        return new JsonResponse(['error' => $errorKey], $status);
    }
}
