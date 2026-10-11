<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrackerLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackerLog>
 */
class TrackerLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackerLog::class);
    }

    /** @return TrackerLog[] */
    public function findPage(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestBySessionKey(string $sessionKey): ?TrackerLog
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.sessionKey = :sessionKey')
            ->andWhere('t.eventType = :type')
            ->setParameter('sessionKey', $sessionKey)
            ->setParameter('type', 'visit')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastTemplateIdByType(string $eventType): ?int
    {
        $row = $this->createQueryBuilder('t')
            ->select('t.templateId')
            ->andWhere('t.eventType = :type')
            ->setParameter('type', $eventType)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row['templateId'] ?? null;
    }

    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    public function markAllRead(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.isRead', ':read')
            ->setParameter('read', true)
            ->andWhere('t.isRead = :unread')
            ->setParameter('unread', false)
            ->getQuery()
            ->execute();
    }

    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()
            ->execute();
    }

    public function deleteBeyondLimit(int $maxRows): int
    {
        $total = $this->countAll();
        if ($total <= $maxRows) {
            return 0;
        }

        $cutoffId = $this->createQueryBuilder('t')
            ->select('t.id')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($maxRows)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($cutoffId === null) {
            return 0;
        }

        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.id <= :cutoffId')
            ->setParameter('cutoffId', $cutoffId['id'])
            ->getQuery()
            ->execute();
    }
}
