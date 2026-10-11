<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function createListQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('t')->orderBy('t.name', 'ASC');
    }

    /** @return Tag[] */
    public function findForFooter(string $sort, int $limit): array
    {
        $qb = $this->createQueryBuilder('t')->setMaxResults($limit);

        return match ($sort) {
            'recent' => $qb->orderBy('t.createdAt', 'DESC')->getQuery()->getResult(),
            'first' => $qb->orderBy('t.name', 'ASC')->getQuery()->getResult(),
            default => $qb
                ->leftJoin('t.posts', 'p')
                ->addSelect('COUNT(p.id) AS HIDDEN postCount')
                ->groupBy('t.id')
                ->orderBy('postCount', 'DESC')
                ->addOrderBy('t.name', 'ASC')
                ->getQuery()
                ->getResult(),
        };
    }

    /** @return Tag[] */
    public function findPopular(int $limit = 10): array
    {
        return $this->findForFooter('popular', $limit);
    }
}
