<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SecurityAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityAlert>
 */
class SecurityAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityAlert::class);
    }

    /** Aggregation anahtarı: aynı IP + aynı tür için tekil satır bulur (upsert hedefi). */
    public function findOneByIpAndType(string $ipAddress, string $type): ?SecurityAlert
    {
        return $this->findOneBy(['ipAddress' => $ipAddress, 'type' => $type]);
    }

    public function createFilteredQueryBuilder(?string $type = null, ?string $severity = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.lastSeenAt', 'DESC');

        if ($type !== null && $type !== '') {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        if ($severity !== null && $severity !== '') {
            $qb->andWhere('a.severity = :severity')->setParameter('severity', $severity);
        }

        return $qb;
    }

    /** Toplam vaka sayısı yerine toplam istek hacmini (hit_count toplamı) sayar — aggregation sonrası satır sayısı yanıltıcıdır. */
    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) ($this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.hitCount), 0)')
            ->andWhere('a.lastSeenAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function countCriticalSince(\DateTimeImmutable $since): int
    {
        return (int) ($this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.hitCount), 0)')
            ->andWhere('a.lastSeenAt >= :since')
            ->andWhere('a.severity = :severity')
            ->setParameter('since', $since)
            ->setParameter('severity', SecurityAlert::SEVERITY_CRITICAL)
            ->getQuery()
            ->getSingleScalarResult());
    }

    /** @return list<array{ipAddress: string, hits: int}> */
    public function findTopOffenders(\DateTimeImmutable $since, int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.ipAddress AS ipAddress, SUM(a.hitCount) AS hits')
            ->andWhere('a.lastSeenAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.ipAddress')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Günlük özet maili için tür bazında vaka/istek sayısı kırılımı. @return list<array{type: string, cases: int, hits: int}> */
    public function countByTypeSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.type AS type, COUNT(a.id) AS cases, COALESCE(SUM(a.hitCount), 0) AS hits')
            ->andWhere('a.lastSeenAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.type')
            ->orderBy('hits', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Özet penceresinde en az bir kez banlanmaya yol açmış (mailSent=true) alarm sayısı. */
    public function countBansSince(\DateTimeImmutable $since): int
    {
        return (int) ($this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.lastSeenAt >= :since')
            ->andWhere('a.mailSent = :sent')
            ->setParameter('since', $since)
            ->setParameter('sent', true)
            ->getQuery()
            ->getSingleScalarResult());
    }

    /** Ayarlardan gelen `auto_purge_days` eşiğine göre eski (uzun süredir görülmemiş) kayıtları siler. */
    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.lastSeenAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    public function markAllRead(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->update()
            ->set('a.isRead', ':read')
            ->setParameter('read', true)
            ->andWhere('a.isRead = :unread')
            ->setParameter('unread', false)
            ->getQuery()
            ->execute();
    }

    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
