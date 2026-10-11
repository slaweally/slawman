<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    public function createListQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.createdAt', 'DESC');
    }

    /** @return Media[] */
    public function findByImportBatch(string $batchId): array
    {
        return $this->findBy(['importBatch' => $batchId]);
    }
}
