<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\BlockedEmailDomain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BlockedEmailDomainCollectionController extends AbstractController
{
    #[Route('/api/v1/blocked-email-domains', name: 'api_core_blocked_email_domains_collection', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $domains = $entityManager->getRepository(BlockedEmailDomain::class)->findBy(['isActive' => true], ['domain' => 'ASC']);

        $data = array_map(static fn (BlockedEmailDomain $domain): array => [
            'domain' => $domain->getDomain(),
            'provider_name' => $domain->getProviderName(),
            'reason' => $domain->getReason(),
        ], $domains);

        return $this->json(['data' => $data, 'meta' => [], 'errors' => []]);
    }
}
