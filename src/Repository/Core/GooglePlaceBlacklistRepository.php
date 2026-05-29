<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\GooglePlaceBlacklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GooglePlaceBlacklist>
 */
class GooglePlaceBlacklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GooglePlaceBlacklist::class);
    }
}
