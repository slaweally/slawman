<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrackerMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackerMessage>
 */
class TrackerMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackerMessage::class);
    }

    /** @return TrackerMessage[] */
    public function findActiveByType(string $eventType): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.eventType = :type')
            ->andWhere('m.isActive = true')
            ->setParameter('type', $eventType)
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, TrackerMessage[]> */
    public function findAllGroupedByType(): array
    {
        $grouped = [];
        foreach ($this->findBy([], ['eventType' => 'ASC', 'id' => 'DESC']) as $message) {
            $grouped[$message->getEventType()][] = $message;
        }

        return $grouped;
    }
}
