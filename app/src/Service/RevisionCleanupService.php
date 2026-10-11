<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Repository\PostRevisionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * İçerik revizyonlarının manuel bakım ve temizlik işlemleri.
 */
class RevisionCleanupService
{
    public function __construct(
        private readonly PostRevisionRepository $postRevisionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{
     *     total: int,
     *     postCount: int,
     *     averagePerPost: float,
     *     oldest: ?\DateTimeInterface,
     *     newest: ?\DateTimeInterface
     * }
     */
    public function getStats(): array
    {
        $total = $this->postRevisionRepository->countAll();
        $postCount = $this->postRevisionRepository->countDistinctPosts();
        $dates = $this->postRevisionRepository->findDateRange();

        return [
            'total' => $total,
            'postCount' => $postCount,
            'averagePerPost' => $postCount > 0 ? round($total / $postCount, 1) : 0.0,
            'oldest' => $dates['oldest'],
            'newest' => $dates['newest'],
        ];
    }

    /**
     * Her içerik için en yeni N revizyonu bırakır, geri kalanını siler.
     */
    public function pruneKeepLatestPerPost(int $keepPerPost): int
    {
        if ($keepPerPost < 0) {
            throw new \InvalidArgumentException('Revizyon sayısı negatif olamaz.');
        }

        $deleted = 0;
        foreach ($this->postRevisionRepository->findAllPostIdsWithRevisions() as $postId) {
            $post = $this->entityManager->getReference(Post::class, $postId);
            $ids = $this->postRevisionRepository->findIdsToPruneForPost($post, $keepPerPost);
            $deleted += $this->postRevisionRepository->deleteByIds($ids);
        }

        return $deleted;
    }

    public function deleteAll(): int
    {
        return $this->postRevisionRepository->deleteAll();
    }

    public function deleteOlderThanDays(int $days): int
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('Gün sayısı en az 1 olmalıdır.');
        }

        $before = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->postRevisionRepository->deleteOlderThan($before);
    }
}
