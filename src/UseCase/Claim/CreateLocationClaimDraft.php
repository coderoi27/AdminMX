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

    public function execute(string $locationName, string $email, ?string $externalSourceKey = null, ?int $canonicalLocationId = null): LocationClaimRequest
    {
        $claim = new LocationClaimRequest();
        $claim->setSourceType('google_places'); // or other if needed, but google_places is default
        $claim->setLocationName($locationName);
        $claim->setEmail($email);
        
        $reflection = new \ReflectionClass($claim);
        
        if ($externalSourceKey !== null) {
            $prop = $reflection->getProperty('externalSourceKey');
            $prop->setValue($claim, $externalSourceKey);
        }
        
        if ($canonicalLocationId !== null) {
            $prop = $reflection->getProperty('canonicalLocationId');
            $prop->setValue($claim, $canonicalLocationId);
        }

        $propStatus = $reflection->getProperty('status');
        $propStatus->setValue($claim, ClaimStateMachine::STATE_DRAFT);
        
        $propUuid = $reflection->getProperty('claimUuid');
        $propUuid->setValue($claim, Uuid::v4()->toRfc4122());

        $this->em->persist($claim);
        $this->em->flush();

        return $claim;
    }
}
