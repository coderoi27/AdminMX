<?php

declare(strict_types=1);

namespace App\Controller\Api\Core;

use App\Entity\Core\LegalDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LegalDocumentCollectionController extends AbstractController
{
    #[Route('/api/v1/legal-documents', name: 'api_core_legal_documents_collection', methods: ['GET'])]
    public function collection(EntityManagerInterface $entityManager): JsonResponse
    {
        $documents = $entityManager->getRepository(LegalDocument::class)->findBy(
            ['status' => LegalDocument::STATUS_PUBLISHED],
            ['sortOrder' => 'ASC', 'title' => 'ASC']
        );

        return $this->json([
            'data' => array_map(static fn (LegalDocument $document): array => self::serialize($document, false), $documents),
            'meta' => ['contract_version' => '2026-05-11'],
            'errors' => [],
        ]);
    }

    #[Route('/api/v1/legal-documents/{slug}', name: 'api_core_legal_documents_show', methods: ['GET'])]
    public function show(string $slug, EntityManagerInterface $entityManager): JsonResponse
    {
        $document = $entityManager->getRepository(LegalDocument::class)->findOneBy([
            'slug' => $slug,
            'status' => LegalDocument::STATUS_PUBLISHED,
        ]);

        if (!$document instanceof LegalDocument) {
            return $this->json([
                'data' => null,
                'meta' => [],
                'errors' => ['Documento legal no encontrado.'],
            ], 404);
        }

        return $this->json([
            'data' => self::serialize($document, true),
            'meta' => ['contract_version' => '2026-05-11'],
            'errors' => [],
        ]);
    }

    private static function serialize(LegalDocument $document, bool $includeBody): array
    {
        $data = [
            'slug' => $document->getSlug(),
            'title' => $document->getTitle(),
            'summary' => $document->getSummary(),
            'version_label' => $document->getVersionLabel(),
            'show_in_footer' => $document->shouldShowInFooter(),
            'updated_at' => $document->getUpdatedAt()->format(DATE_ATOM),
        ];

        if ($includeBody) {
            $data['body'] = $document->getBody();
        }

        return $data;
    }
}
