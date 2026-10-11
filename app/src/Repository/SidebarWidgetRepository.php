<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SidebarWidget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SidebarWidget>
 */
class SidebarWidgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SidebarWidget::class);
    }

    /** @return SidebarWidget[] */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.isActive = true')
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
