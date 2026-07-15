<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CreateLocationClaimDraft
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function execute(
        string $sourceType,
        string $locationName,
        ?string $externalSourceKey = null,
        ?int $canonicalLocationId = null,
        ?string $email = null,
        ?string $claimantName = null,
        ?string $shortAddress = null,
        ?array $prefillPayload = null
    ): LocationClaimRequest {
        if ($sourceType === 'google_places' && empty($externalSourceKey)) {
            throw new \InvalidArgumentException('external_source_key is required for google_places.');
        }
        if ($sourceType === 'canonical' && empty($canonicalLocationId)) {
            throw new \InvalidArgumentException('canonical_location_id is required for canonical.');
        }
        if ($sourceType === 'google_places' && !empty($canonicalLocationId)) {
            throw new \InvalidArgumentException('canonical_location_id must not be provided for google_places.');
        }
        if ($sourceType === 'canonical' && !empty($externalSourceKey)) {
            throw new \InvalidArgumentException('external_source_key must not be provided for canonical.');
        }

        $safePrefill = null;
        if (is_array($prefillPayload)) {
            $allowed = ['lat', 'lng', 'category_slug', 'photo_url', 'short_address', 'display_source'];
            $safePrefill = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $prefillPayload)) {
                    $safePrefill[$key] = $prefillPayload[$key];
                }
            }
            if (empty($safePrefill)) {
                $safePrefill = null;
            }
        }

        $claim = new LocationClaimRequest();
        $claim->setSourceType($sourceType);
        $claim->setLocationName($locationName);
        $claim->setExternalSourceKey($externalSourceKey);
        $claim->setCanonicalLocationId($canonicalLocationId);
        
        $claim->setEmail($email);
        $claim->setClaimantName($claimantName);
        $claim->setShortAddress($shortAddress);
        $claim->setPrefillPayloadJson($safePrefill);

        $claim->setStatus(ClaimStateMachine::STATE_DRAFT);
        $claim->setSubmissionMode('assisted');
        $claim->setClaimUuid(Uuid::v4()->toRfc4122());

        $this->em->persist($claim);
        $this->em->flush();

        return $claim;
    }
}
