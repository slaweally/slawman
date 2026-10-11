<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /** @return MenuItem[] */
    public function findActiveByLocation(string $location): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.location = :location')
            ->andWhere('m.isActive = true')
            ->setParameter('location', $location)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Sadece kök (parent'ı olmayan) öğeler — children ilişkisi lazy-load ile gelir. */
    public function findRootItemsByLocation(string $location): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.location = :location')
            ->andWhere('m.parent IS NULL')
            ->setParameter('location', $location)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Aktif kök menü + aktif children (front nav dropdown için, tek sorguda). */
    public function findActiveTreeByLocation(string $location): array
    {
        $all = $this->createQueryBuilder('m')
            ->andWhere('m.location = :location')
            ->andWhere('m.isActive = true')
            ->setParameter('location', $location)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($all, static fn (MenuItem $item): bool => $item->getParent() === null));
    }
}
