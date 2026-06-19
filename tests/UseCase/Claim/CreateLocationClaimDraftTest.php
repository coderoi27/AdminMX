<?php

declare(strict_types=1);

namespace App\Tests\UseCase\Claim;

use App\Domain\Claim\ClaimStateMachine;
use App\Entity\Core\LocationClaimRequest;
use App\UseCase\Claim\CreateLocationClaimDraft;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CreateLocationClaimDraftTest extends TestCase
{
    public function testExecuteCreatesDraftClaim(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $useCase = new CreateLocationClaimDraft($em);
        $claim = $useCase->execute('Mi Local', 'owner@example.com');

        $this->assertSame('Mi Local', $claim->getLocationName());
        $this->assertSame('owner@example.com', $claim->getEmail());
        $this->assertSame(ClaimStateMachine::STATE_DRAFT, $claim->getStatus());
        $this->assertNotNull($claim->getClaimUuid());
    }
}
