<?php

declare(strict_types=1);

namespace App\UseCase\Claim;

use App\Entity\Core\LocationClaimRequest;
use App\Domain\Claim\ClaimStateMachine;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateLocationClaimProgress
{
    private EntityManagerInterface $em;
    private ClaimStateMachine $stateMachine;

    public function __construct(EntityManagerInterface $em, ClaimStateMachine $stateMachine)
    {
        $this->em = $em;
        $this->stateMachine = $stateMachine;
    }

    public function execute(LocationClaimRequest $claim, array $dto): LocationClaimRequest
    {
        if (!in_array($claim->getStatus(), [
            ClaimStateMachine::STATE_DRAFT,
            ClaimStateMachine::STATE_PENDING_EMAIL_VERIFICATION,
            ClaimStateMachine::STATE_PENDING_EVIDENCE,
            ClaimStateMachine::STATE_NEEDS_INFO
        ], true)) {
            throw new \DomainException('Cannot update progress in current state.');
        }

        $reflection = new \ReflectionClass($claim);

        if (array_key_exists('claimant_name', $dto)) {
            $claim->setClaimantName($dto['claimant_name']);
        }
        
        if (array_key_exists('claimant_role', $dto)) {
            $prop = $reflection->getProperty('claimantRole');
            $prop->setValue($claim, $dto['claimant_role']);
        }

        if (array_key_exists('claimant_phone_e164', $dto)) {
            $prop = $reflection->getProperty('claimantPhoneE164');
            $prop->setValue($claim, $dto['claimant_phone_e164']);
        }

        if (array_key_exists('business_phone_e164', $dto)) {
            $prop = $reflection->getProperty('businessPhoneE164');
            $prop->setValue($claim, $dto['business_phone_e164']);
        }

        if (array_key_exists('proposed_name', $dto)) {
            $prop = $reflection->getProperty('proposedName');
            $prop->setValue($claim, $dto['proposed_name']);
        }

        if (array_key_exists('proposed_address_json', $dto)) {
            $prop = $reflection->getProperty('proposedAddressJson');
            $prop->setValue($claim, $dto['proposed_address_json']);
        }

        if (array_key_exists('confirmed_latitude', $dto)) {
            $prop = $reflection->getProperty('confirmedLatitude');
            $prop->setValue($claim, $dto['confirmed_latitude'] !== null ? (float) $dto['confirmed_latitude'] : null);
        }

        if (array_key_exists('confirmed_longitude', $dto)) {
            $prop = $reflection->getProperty('confirmedLongitude');
            $prop->setValue($claim, $dto['confirmed_longitude'] !== null ? (float) $dto['confirmed_longitude'] : null);
        }

        if (array_key_exists('last_completed_step', $dto)) {
            $prop = $reflection->getProperty('lastCompletedStep');
            $prop->setValue($claim, $dto['last_completed_step']);
        }

        $this->em->flush();

        return $claim;
    }
}
