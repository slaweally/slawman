<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /** @return Comment[] */
    public function findApprovedTopLevelByPost(int $postId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.post = :postId')
            ->andWhere('c.status = :status')
            ->andWhere('c.parent IS NULL')
            ->setParameter('postId', $postId)
            ->setParameter('status', \App\Enum\CommentStatus::Approved)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', \App\Enum\CommentStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteByImportBatch(string $batchId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.importBatch = :batch')
            ->setParameter('batch', $batchId)
            ->getQuery()
            ->execute();
    }

    public function findOneByWpImportId(int $wpId): ?Comment
    {
        return $this->findOneBy(['wpImportId' => $wpId]);
    }

    public function createAdminListQueryBuilder(?\App\Enum\CommentStatus $status = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.post', 'p')
            ->addSelect('p')
            ->orderBy('c.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        } else {
            $qb->andWhere('c.status != :trash')->setParameter('trash', \App\Enum\CommentStatus::Trash);
        }

        return $qb;
    }

    public function countTrash(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', \App\Enum\CommentStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteAllTrash(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.status = :status')
            ->setParameter('status', \App\Enum\CommentStatus::Trash)
            ->getQuery()
            ->execute();
    }

    /** @return Comment[] */
    public function findRecentApproved(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.post', 'p')
            ->addSelect('p')
            ->andWhere('c.status = :status')
            ->setParameter('status', \App\Enum\CommentStatus::Approved)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
