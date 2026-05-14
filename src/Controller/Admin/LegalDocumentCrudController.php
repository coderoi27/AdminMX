<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\LegalDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal-documents', name: 'admin_legal_documents_')]
final class LegalDocumentCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/legal_documents/index.html.twig', [
            'documents' => $entityManager->getRepository(LegalDocument::class)->findBy([], ['sortOrder' => 'ASC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $document = (new LegalDocument())
            ->setStatus(LegalDocument::STATUS_DRAFT)
            ->setVersionLabel('v1.0')
            ->setShowInFooter(true);

        return $this->handleForm($request, $entityManager, $document, false);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(LegalDocument $document, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleForm($request, $entityManager, $document, true);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(LegalDocument $document, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('archive_legal_document_%d', $document->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud.');

            return $this->redirectToRoute('admin_legal_documents_index');
        }

        $document->setStatus(LegalDocument::STATUS_ARCHIVED);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Documento "%s" archivado.', $document->getTitle()));

        return $this->redirectToRoute('admin_legal_documents_index');
    }

    private function handleForm(Request $request, EntityManagerInterface $entityManager, LegalDocument $document, bool $isEdit): Response
    {
        $errors = [];

        if ($request->isMethod('POST')) {
            $title = trim($request->request->getString('title', ''));
            $slug = $this->slugify($request->request->getString('slug', ''));
            $body = trim($request->request->getString('body', ''));
            $status = $request->request->getString('status', LegalDocument::STATUS_DRAFT);

            if ($title === '') {
                $errors[] = 'El título es obligatorio.';
            }
            if ($slug === '') {
                $errors[] = 'El slug es obligatorio.';
            }
            if ($body === '') {
                $errors[] = 'El cuerpo del documento es obligatorio.';
            }
            if (!in_array($status, LegalDocument::statuses(), true)) {
                $errors[] = 'El estado no es válido.';
            }

            $existing = $slug !== '' ? $entityManager->getRepository(LegalDocument::class)->findOneBy(['slug' => $slug]) : null;
            if ($existing instanceof LegalDocument && $existing->getId() !== $document->getId()) {
                $errors[] = 'Ya existe un documento legal con ese slug.';
            }

            if ($errors === []) {
                $document
                    ->setTitle($title)
                    ->setSlug($slug)
                    ->setVersionLabel($request->request->getString('version_label', 'v1.0'))
                    ->setStatus($status)
                    ->setSummary($request->request->getString('summary', ''))
                    ->setBody($body)
                    ->setShowInFooter($request->request->getBoolean('show_in_footer', false))
                    ->setSortOrder($request->request->getInt('sort_order', 0))
                    ->setEffectiveAt($this->parseDate($request->request->getString('effective_at', '')));

                $entityManager->persist($document);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Documento "%s" guardado.', $document->getTitle()));

                return $this->redirectToRoute('admin_legal_documents_index');
            }
        }

        return $this->render('admin/legal_documents/form.html.twig', [
            'document' => $document,
            'statuses' => LegalDocument::statuses(),
            'errors' => $errors,
            'is_edit' => $isEdit,
            'page_title' => $isEdit ? 'Editar documento legal' : 'Nuevo documento legal',
        ]);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return new \DateTimeImmutable($value);
    }

    private function slugify(string $value): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
