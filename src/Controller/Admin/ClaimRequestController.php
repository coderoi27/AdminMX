<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\LocationClaimRequest;
use App\Entity\Core\Merchant;
use App\Entity\Core\MerchantLocation;
use App\Entity\Core\PlaceAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/claims', name: 'admin_claims_')]
final class ClaimRequestController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/claims/index.html.twig', [
            'claims' => $entityManager->getRepository(LocationClaimRequest::class)->findBy([], ['id' => 'DESC'], 60),
            'statuses' => LocationClaimRequest::statuses(),
        ]);
    }

    #[Route('/{id}/status', name: 'status', methods: ['POST'])]
    public function status(LocationClaimRequest $claim, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('claim_status_%d', $claim->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud del claim.');

            return $this->redirectToRoute('admin_claims_index');
        }

        $status = (string) $request->request->get('status');
        if (!in_array($status, LocationClaimRequest::statuses(), true)) {
            $this->addFlash('error', 'El estado del claim no es válido.');

            return $this->redirectToRoute('admin_claims_index');
        }
        if (!$claim->canTransitionTo($status)) {
            $this->addFlash('error', sprintf('La transición del claim %s -> %s no está permitida.', $claim->getStatus(), $status));

            return $this->redirectToRoute('admin_claims_index');
        }

        $claim
            ->setStatus($status)
            ->setReviewedAt(new \DateTimeImmutable());

        if ($status === LocationClaimRequest::STATUS_APPROVED) {
            $this->materializeApprovedClaim($claim, $entityManager);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Claim actualizado correctamente.');

        return $this->redirectToRoute('admin_claims_index');
    }

    private function materializeApprovedClaim(LocationClaimRequest $claim, EntityManagerInterface $entityManager): void
    {
        $location = null;

        if ($claim->getCanonicalLocationId() !== null) {
            $location = $entityManager->find(MerchantLocation::class, $claim->getCanonicalLocationId());
        }

        if (!$location instanceof MerchantLocation && $claim->getExternalSourceKey() !== null) {
            $location = $entityManager->getRepository(MerchantLocation::class)->findOneBy([
                'externalSourceKey' => $claim->getExternalSourceKey(),
            ]);
        }

        if (!$location instanceof MerchantLocation) {
            $location = $this->createCanonicalLocationFromClaim($claim, $entityManager);
            $entityManager->persist($location->getMerchant());
            $entityManager->persist($location);
            $entityManager->flush();
        }

        $location
            ->setSourceType(MerchantLocation::SOURCE_TYPE_CLAIMED)
            ->setStatus(MerchantLocation::STATUS_ACTIVE)
            ->setIsClaimable(false)
            ->setClaimedAt(new \DateTimeImmutable())
            ->setExternalSourceKey($claim->getExternalSourceKey());
        $this->publishClaimedLocation($location);

        $claim->setCanonicalLocationId($location->getId());
    }

    private function publishClaimedLocation(MerchantLocation $location): void
    {
        if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE) {
            return;
        }

        if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_HIDDEN) {
            $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_PENDING_VISIBLE);
        }

        $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE);
    }

    private function createCanonicalLocationFromClaim(LocationClaimRequest $claim, EntityManagerInterface $entityManager): MerchantLocation
    {
        $prefill = $claim->getPrefillPayloadJson() ?? [];
        $merchantName = trim($claim->getLocationName());
        $baseSlug = $this->slugify($merchantName !== '' ? $merchantName : 'local-claim');

        $merchant = (new Merchant())
            ->setName($merchantName !== '' ? $merchantName : 'Local reclamado')
            ->setSlug($this->nextAvailableMerchantSlug($entityManager, $baseSlug))
            ->setStatus('active');

        $location = (new MerchantLocation())
            ->setMerchant($merchant)
            ->setName($merchantName !== '' ? $merchantName : 'Local reclamado')
            ->setSlug($this->nextAvailableLocationSlug($entityManager, $baseSlug))
            ->setLocationType('fixed')
            ->setStatus(MerchantLocation::STATUS_ACTIVE)
            ->setPublicationState(MerchantLocation::PUBLICATION_STATE_PUBLIC_VISIBLE)
            ->setSourceType(MerchantLocation::SOURCE_TYPE_CLAIMED)
            ->setExternalSourceKey($claim->getExternalSourceKey())
            ->setShortDescription($claim->getMessage())
            ->setIsClaimable(false)
            ->setClaimedAt(new \DateTimeImmutable());

        $categorySlug = is_string($prefill['category_slug'] ?? null) ? trim((string) $prefill['category_slug']) : '';
        if ($categorySlug !== '') {
            $category = $entityManager->getRepository(LocationCategory::class)->findOneBy(['slug' => $categorySlug]);
            if ($category instanceof LocationCategory) {
                $location->setPrimaryCategory($category);
            }
        }

        $address = (new PlaceAddress())
            ->setIsPrimary(true)
            ->setLabel('Claim Google')
            ->setNeighborhood($claim->getShortAddress())
            ->setReference($claim->getShortAddress())
            ->setLatitude(isset($prefill['lat']) && is_numeric((string) $prefill['lat']) ? (string) $prefill['lat'] : '0.0000000')
            ->setLongitude(isset($prefill['lng']) && is_numeric((string) $prefill['lng']) ? (string) $prefill['lng'] : '0.0000000');

        $location->addAddress($address);

        return $location;
    }

    private function slugify(string $value): string
    {
        $normalized = mb_strtolower($value);
        if (function_exists('transliterator_transliterate')) {
            $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII;', $normalized) ?? $normalized;
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $normalized);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : 'local-claim';
    }

    private function nextAvailableMerchantSlug(EntityManagerInterface $entityManager, string $baseSlug): string
    {
        $slug = $baseSlug;
        $index = 2;

        while ($entityManager->getRepository(Merchant::class)->findOneBy(['slug' => $slug]) instanceof Merchant) {
            $slug = sprintf('%s-%d', $baseSlug, $index);
            $index += 1;
        }

        return $slug;
    }

    private function nextAvailableLocationSlug(EntityManagerInterface $entityManager, string $baseSlug): string
    {
        $slug = $baseSlug;
        $index = 2;

        while ($entityManager->getRepository(MerchantLocation::class)->findOneBy(['slug' => $slug]) instanceof MerchantLocation) {
            $slug = sprintf('%s-%d', $baseSlug, $index);
            $index += 1;
        }

        return $slug;
    }
}
