<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Enum\CommentStatus;
use App\Enum\PostStatus;
use App\Enum\PostType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function createAdminListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL');
    }

    /**
     * @param array{
     *     q?: string,
     *     type?: string,
     *     status?: string,
     *     category?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     min_comments?: int,
     *     sort?: string,
     *     direction?: string
     * } $filters
     */
    public function applyAdminFilters(QueryBuilder $qb, array $filters): QueryBuilder
    {
        if (!empty($filters['q'])) {
            $qb->andWhere('p.title LIKE :q OR p.content LIKE :q OR p.slug LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')->setParameter('type', $filters['type']);
        } elseif (($filters['exclude_pages'] ?? false) === true) {
            $qb->andWhere('p.type != :pageType')->setParameter('pageType', PostType::Page);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $qb->innerJoin('p.categories', 'filterCat')
                ->andWhere('filterCat.slug = :catSlug')
                ->setParameter('catSlug', $filters['category']);
        }

        if (!empty($filters['date_from'])) {
            $from = \DateTime::createFromFormat('Y-m-d', $filters['date_from']) ?: null;
            if ($from !== false && $from !== null) {
                $from->setTime(0, 0, 0);
                $qb->andWhere(
                    '(p.publishedAt IS NOT NULL AND p.publishedAt >= :dateFrom) OR (p.publishedAt IS NULL AND p.createdAt >= :dateFrom)'
                )->setParameter('dateFrom', $from);
            }
        }

        if (!empty($filters['date_to'])) {
            $to = \DateTime::createFromFormat('Y-m-d', $filters['date_to']) ?: null;
            if ($to !== false && $to !== null) {
                $to->setTime(23, 59, 59);
                $qb->andWhere(
                    '(p.publishedAt IS NOT NULL AND p.publishedAt <= :dateTo) OR (p.publishedAt IS NULL AND p.createdAt <= :dateTo)'
                )->setParameter('dateTo', $to);
            }
        }

        if (!empty($filters['min_comments'])) {
            $qb->andWhere(
                '(SELECT COUNT(c.id) FROM App\Entity\Comment c WHERE c.post = p AND c.status = :approvedStatus) >= :minComments'
            )
                ->setParameter('approvedStatus', CommentStatus::Approved)
                ->setParameter('minComments', (int) $filters['min_comments']);
        }

        $sort = $filters['sort'] ?? 'date';
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        match ($sort) {
            'title' => $qb->orderBy('p.title', $direction),
            'comments' => $qb->addSelect('(SELECT COUNT(c2.id) FROM App\Entity\Comment c2 WHERE c2.post = p) AS HIDDEN _sortComments')
                ->orderBy('_sortComments', $direction),
            'views' => $qb->orderBy('p.viewCount', $direction),
            default => $qb
                ->addSelect('(CASE WHEN p.publishedAt IS NOT NULL THEN p.publishedAt ELSE p.createdAt END) AS HIDDEN _sortDate')
                ->orderBy('_sortDate', $direction),
        };

        return $qb;
    }

    public function countActive(): int
    {
        return (int) $this->createAdminListQueryBuilder()
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * WordPress tarzı "sanal cron": zamanı gelmiş (publishedAt <= now) zamanlanmış
     * içerikleri tek bir DQL bulk UPDATE ile yayına alır. Entity hydrate etmez,
     * composite index (status, published_at) sayesinde tarama maliyeti minimaldir.
     */
    public function publishDueScheduledPosts(\DateTimeInterface $now): int
    {
        return (int) $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':published')
            ->andWhere('p.status = :scheduled')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('published', PostStatus::Published)
            ->setParameter('scheduled', PostStatus::Scheduled)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * 13 yıllık arşiv sayfası için: tüm postları çekmek yerine, native SQL ile
     * doğrudan veritabanı seviyesinde yıl/ay bazında COUNT+GROUP BY yapar.
     * Entity hydrate edilmez, sadece [yıl => [ay => sayı]] şeklinde hafif bir
     * dizi döner — composite (status, published_at) index'i bu sorguyu ucuzlatır.
     *
     * @return array<int, array<int, int>> [2026 => [7 => 12, 6 => 8], 2025 => [...]]
     */
    public function getArchiveCounts(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                YEAR(published_at) AS y,
                MONTH(published_at) AS m,
                COUNT(id) AS total
            FROM post
            WHERE status = :status
              AND deleted_at IS NULL
              AND published_at IS NOT NULL
            GROUP BY y, m
            ORDER BY y DESC, m DESC
        SQL;

        $rows = $conn->executeQuery($sql, ['status' => PostStatus::Published->value])->fetchAllAssociative();

        $archive = [];
        foreach ($rows as $row) {
            $year = (int) $row['y'];
            $month = (int) $row['m'];
            $archive[$year][$month] = (int) $row['total'];
        }

        return $archive;
    }

    /**
     * Belirli bir yıl/ay için başlık+link listesi (arşiv AJAX içerik yüklemesi).
     * Native SQL ile sadece gerekli kolonları çeker, entity hydrate etmez.
     *
     * @return list<array{id: int, title: string|null, slug: string, type: string, publishedAt: string}>
     */
    public function findArchiveItemsForMonth(int $year, int $month): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT id, title, slug, type, published_at
            FROM post
            WHERE status = :status
              AND deleted_at IS NULL
              AND YEAR(published_at) = :year
              AND MONTH(published_at) = :month
            ORDER BY published_at DESC
        SQL;

        $rows = $conn->executeQuery($sql, [
            'status' => PostStatus::Published->value,
            'year' => $year,
            'month' => $month,
        ])->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'type' => $row['type'],
            'publishedAt' => $row['published_at'],
        ], $rows);
    }

    public function createPublishedQueryBuilder(?PostType $type = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('status', PostStatus::Published)
            ->orderBy('p.publishedAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('p.type = :type')->setParameter('type', $type);
        }

        return $qb;
    }

    /** @return Post[] */
    public function findPublishedByType(PostType $type, int $limit = 10): array
    {
        return $this->createPublishedQueryBuilder($type)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Post[] */
    public function findFeatured(int $limit = 5): array
    {
        return $this->createPublishedQueryBuilder()
            ->andWhere('p.isFeatured = true')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPublishedByType(PostType $type): int
    {
        return (int) $this->createPublishedQueryBuilder($type)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestPublished(?PostType $type = null): ?Post
    {
        $results = $this->createPublishedQueryBuilder($type)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $results[0] ?? null;
    }

    public function findOnePublishedBySlugAndType(string $slug, PostType $type): ?Post
    {
        return $this->createPublishedQueryBuilder($type)
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function incrementViewCount(Post $post): void
    {
        $this->createQueryBuilder('p')
            ->update()
            ->set('p.viewCount', 'p.viewCount + 1')
            ->where('p.id = :id')
            ->setParameter('id', $post->getId())
            ->getQuery()
            ->execute();

        $post->setViewCount($post->getViewCount() + 1);
    }

    public function findOnePublishedBySlug(string $slug): ?Post
    {
        return $this->createPublishedQueryBuilder()
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Post[] */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createPublishedQueryBuilder()
            ->andWhere('p.type != :pageType')
            ->andWhere('p.title LIKE :q OR p.content LIKE :q OR p.excerpt LIKE :q')
            ->setParameter('pageType', PostType::Page)
            ->setParameter('q', '%' . $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Post[] */
    public function findRecentPublished(int $limit = 5, ?PostType $excludeType = null): array
    {
        $qb = $this->createPublishedQueryBuilder()
            ->andWhere('p.type != :pageType')
            ->setParameter('pageType', PostType::Page)
            ->setMaxResults($limit);

        if ($excludeType !== null) {
            $qb->andWhere('p.type != :excludeType')->setParameter('excludeType', $excludeType);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Post[] */
    public function findRelated(Post $post, int $limit = 4): array
    {
        $qb = $this->createPublishedQueryBuilder()
            ->andWhere('p.id != :id')
            ->andWhere('p.type = :type')
            ->setParameter('id', $post->getId())
            ->setParameter('type', $post->getType())
            ->setMaxResults($limit);

        if ($post->getCategories()->count() > 0) {
            $qb->join('p.categories', 'c')
                ->andWhere('c IN (:categories)')
                ->setParameter('categories', $post->getCategories()->toArray())
                ->groupBy('p.id')
                ->orderBy('p.publishedAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function findActiveById(int $id): ?Post
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isSlugTaken(string $slug): bool
    {
        $em = $this->getEntityManager();
        $filterWasEnabled = false;
        if ($em->getFilters()->isEnabled('softdeleteable')) {
            $em->getFilters()->disable('softdeleteable');
            $filterWasEnabled = true;
        }

        try {
            return (int) $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.slug = :slug')
                ->setParameter('slug', $slug)
                ->getQuery()
                ->getSingleScalarResult() > 0;
        } finally {
            if ($filterWasEnabled) {
                $em->getFilters()->enable('softdeleteable');
            }
        }
    }

    /**
     * Bir post listesinin `categories` koleksiyonlarını tek bir toplu sorguda
     * önceden yükler (Doctrine identity map'i doldurur). Listing sayfalarında
     * (anasayfa, kategori, tip bazlı listeler) her post için ayrı ayrı
     * tetiklenen N+1 lazy-load sorgusunu tek sorguya indirger; setMaxResults
     * ile birlikte kullanılan ana sorguyu etkilemez çünkü ayrı ve sonradan
     * çalışan bir adımdır.
     *
     * @param Post[] $posts
     */
    public function warmCategories(array $posts): void
    {
        if ($posts === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->leftJoin('p.categories', 'c')
            ->andWhere('p IN (:posts)')
            ->setParameter('posts', $posts)
            ->getQuery()
            ->getResult();
    }

    public function findOneByWpImportId(int $wpId): ?Post
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.meta', 'm')
            ->andWhere('m.metaKey = :key')
            ->andWhere('m.metaValue = :val')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('key', 'wp_import_id')
            ->setParameter('val', (string) $wpId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return string[] */
    public function findImportBatches(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT m.metaValue AS batch')
            ->innerJoin('p.meta', 'm')
            ->andWhere('m.metaKey = :key')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('key', 'wp_import_batch')
            ->orderBy('batch', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(static fn (array $r): string => (string) $r['batch'], $rows)));
    }

    /** @return Post[] */
    public function findByImportBatch(string $batchId, bool $includeDeleted = false): array
    {
        $em = $this->getEntityManager();
        $filterWasEnabled = false;

        if ($includeDeleted && $em->getFilters()->isEnabled('softdeleteable')) {
            $em->getFilters()->disable('softdeleteable');
            $filterWasEnabled = true;
        }

        try {
            return $this->createQueryBuilder('p')
                ->innerJoin('p.meta', 'm')
                ->andWhere('m.metaKey = :key')
                ->andWhere('m.metaValue = :batch')
                ->setParameter('key', 'wp_import_batch')
                ->setParameter('batch', $batchId)
                ->getQuery()
                ->getResult();
        } finally {
            if ($filterWasEnabled) {
                $em->getFilters()->enable('softdeleteable');
            }
        }
    }

    /** @return Post[] */
    public function findTopByViewCount(int $limit = 10): array
    {
        return $this->createPublishedQueryBuilder()
            ->andWhere('p.type != :pageType')
            ->setParameter('pageType', PostType::Page)
            ->orderBy('p.viewCount', 'DESC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{post: Post, count: int}>
     */
    public function findTopByCommentCount(int $limit = 10): array
    {
        $rows = $this->createPublishedQueryBuilder()
            ->select('p.id', 'COUNT(c.id) AS cnt')
            ->leftJoin('p.comments', 'c', 'WITH', 'c.status = :approved')
            ->andWhere('p.type != :pageType')
            ->setParameter('pageType', PostType::Page)
            ->setParameter('approved', \App\Enum\CommentStatus::Approved)
            ->groupBy('p.id')
            ->having('cnt > 0')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $counts = array_column($rows, 'cnt', 'id');
        $posts = $this->createPublishedQueryBuilder()
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($posts as $post) {
            $byId[$post->getId()] = $post;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = ['post' => $byId[$id], 'count' => (int) $counts[$id]];
            }
        }

        return $out;
    }
}
