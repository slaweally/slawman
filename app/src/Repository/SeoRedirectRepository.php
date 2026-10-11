<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SeoRedirect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoRedirect>
 */
class SeoRedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoRedirect::class);
    }

    public function findActiveBySourcePath(string $path): ?SeoRedirect
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.sourcePath = :path')
            ->andWhere('r.isActive = true')
            ->setParameter('path', $path)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
