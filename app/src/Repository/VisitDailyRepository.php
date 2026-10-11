<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VisitDaily;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisitDaily>
 */
class VisitDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisitDaily::class);
    }

    public function findByDate(\DateTimeImmutable $date): ?VisitDaily
    {
        return $this->findOneBy(['visitDate' => $date]);
    }

    /** @return VisitDaily[] */
    public function findLastDays(int $days): array
    {
        $from = new \DateTimeImmutable(sprintf('-%d days', max(1, $days - 1)));

        return $this->createQueryBuilder('v')
            ->andWhere('v.visitDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('v.visitDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{page_views: int, unique_visitors: int} */
    public function sumTotals(): array
    {
        $result = $this->createQueryBuilder('v')
            ->select('SUM(v.pageViews) AS pageViews')
            ->addSelect('SUM(v.uniqueVisitors) AS uniqueVisitors')
            ->getQuery()
            ->getSingleResult();

        return [
            'page_views' => (int) ($result['pageViews'] ?? 0),
            'unique_visitors' => (int) ($result['uniqueVisitors'] ?? 0),
        ];
    }

    public function sumPageViewsSince(\DateTimeImmutable $from): int
    {
        $value = $this->createQueryBuilder('v')
            ->select('SUM(v.pageViews)')
            ->andWhere('v.visitDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($value ?? 0);
    }

    public function sumUniqueVisitorsSince(\DateTimeImmutable $from): int
    {
        $value = $this->createQueryBuilder('v')
            ->select('SUM(v.uniqueVisitors)')
            ->andWhere('v.visitDate >= :from')
            ->setParameter('from', $from->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($value ?? 0);
    }

    /** @return VisitDaily[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.visitDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return VisitDaily[] */
    public function findByYearMonth(int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = (new \DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');

        return $this->createQueryBuilder('v')
            ->andWhere('v.visitDate >= :from')
            ->andWhere('v.visitDate <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('v.visitDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return VisitDaily[] */
    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.visitDate >= :from')
            ->andWhere('v.visitDate <= :to')
            ->setParameter('from', sprintf('%04d-01-01', $year))
            ->setParameter('to', sprintf('%04d-12-31', $year))
            ->orderBy('v.visitDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
