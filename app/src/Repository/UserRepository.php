<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOwner(): ?User
    {
        $users = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        return $users[0] ?? null;
    }
}
