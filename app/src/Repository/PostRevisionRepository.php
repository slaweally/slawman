<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostRevision>
 */
class PostRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostRevision::class);
    }

    /** @return PostRevision[] */
    public function findLatestForPost(Post $post, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.post = :post')
            ->setParameter('post', $post)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctPosts(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.post)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{oldest: ?\DateTimeInterface, newest: ?\DateTimeInterface} */
    public function findDateRange(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('MIN(r.createdAt) as oldest', 'MAX(r.createdAt) as newest')
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'oldest' => $result['oldest'] ?? null,
            'newest' => $result['newest'] ?? null,
        ];
    }

    /** @return list<int> */
    public function findAllPostIdsWithRevisions(): array
    {
        return array_map(
            'intval',
            $this->createQueryBuilder('r')
                ->select('DISTINCT IDENTITY(r.post)')
                ->getQuery()
                ->getSingleColumnResult()
        );
    }

    /** @return list<int> */
    public function findIdsToPruneForPost(Post $post, int $keep): array
    {
        if ($keep < 0) {
            return [];
        }

        $ids = $this->createQueryBuilder('r')
            ->select('r.id')
            ->andWhere('r.post = :post')
            ->setParameter('post', $post)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', array_slice($ids, $keep));
    }

    /** @param list<int> $ids */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->getQuery()
            ->execute();
    }

    public function deleteOlderThan(\DateTimeInterface $before): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('r.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
