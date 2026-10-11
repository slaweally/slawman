<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Dto\Import\WordPressImportJob;
use App\Dto\Wxr\WxrParseResult;
use App\Dto\Wxr\WxrPost;
use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\PostMeta;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\CommentStatus;
use App\Enum\PostStatus;
use App\Enum\PostType;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\ContentSanitizerService;
use App\Service\SluggerService;
use App\Service\SubscriberService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Adım adım WordPress import işlemlerini yürütür.
 */
class WordPressImportRunnerService
{
    private const META_MAP = [
        'rank_math_title' => 'seo_title',
        'rank_math_description' => 'seo_description',
        'rank_math_focus_keyword' => 'seo_keywords',
        'rank_math_facebook_title' => 'og_title',
        'rank_math_facebook_description' => 'og_description',
        'rank_math_facebook_image' => 'og_image',
        'rank_math_twitter_title' => 'twitter_title',
        'rank_math_twitter_description' => 'twitter_description',
        'rank_math_twitter_image' => 'twitter_image',
    ];

    private const SKIP_META_PREFIXES = ['_edit_', '_wp_', '_oembed_', '_elementor_', '_menu_item_'];

    /** @var array<string, Category> */
    private array $categoryImportCache = [];

    /** @var array<string, Tag> */
    private array $tagImportCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly CommentRepository $commentRepository,
        private readonly UserRepository $userRepository,
        private readonly SluggerService $slugger,
        private readonly ContentSanitizerService $sanitizer,
        private readonly WxrImportValidator $validator,
        private readonly ImportMediaDownloader $mediaDownloader,
        private readonly WordPressImportJobManager $jobManager,
        private readonly WordPressImportRollbackService $rollbackService,
        private readonly SubscriberService $subscriberService,
    ) {
    }

  /**
     * @return array{
     *     job_id: string,
     *     batch_id: string,
     *     total_posts: int,
     *     total_media: int,
     *     chunk_size: int,
     *     download_media: bool
     * }
     */
    public function startJob(string $filePath, User $author, int $chunkSize, bool $downloadMedia): array
    {
        $validation = $this->validator->validate($filePath);
        if (!$validation['valid'] || !$validation['parse'] instanceof WxrParseResult) {
            throw new \RuntimeException(implode(' ', $validation['errors']) ?: 'WXR doğrulaması başarısız.');
        }

        $parse = $validation['parse'];
        $jobId = Uuid::v4()->toRfc4122();
        $batchId = Uuid::v4()->toRfc4122();
        $chunkSize = $this->normalizeChunkSize($chunkSize);

        $postQueue = [];
        $skipped = 0;
        foreach ($parse->posts as $wpPost) {
            if ($this->postRepository->findOneByWpImportId($wpPost->id) !== null) {
                ++$skipped;
                continue;
            }
            $postQueue[] = $wpPost->id;
        }

        $mediaQueue = $downloadMedia ? $this->validator->collectRequiredMediaUrls($parse) : [];
        $parseCachePath = $this->jobManager->saveParseCache($jobId, $parse);

        $job = new WordPressImportJob(
            jobId: $jobId,
            batchId: $batchId,
            filePath: $filePath,
            parseCachePath: $parseCachePath,
            authorId: (int) $author->getId(),
            chunkSize: $chunkSize,
            downloadMedia: $downloadMedia,
            postQueue: $postQueue,
            mediaQueue: $mediaQueue,
            stats: $this->emptyStats($batchId, $skipped),
            totalPosts: count($postQueue),
            totalMedia: count($mediaQueue),
            createdAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $this->jobManager->save($job);

        return [
            'job_id' => $jobId,
            'batch_id' => $batchId,
            'total_posts' => count($postQueue),
            'total_media' => count($mediaQueue),
            'chunk_size' => $chunkSize,
            'download_media' => $downloadMedia,
            'skipped' => $skipped,
        ];
    }

    /** @return array<string, mixed> */
    public function runStep(string $jobId): array
    {
        @set_time_limit(120);

        $job = $this->jobManager->load($jobId);
        if ($job === null) {
            throw new \RuntimeException('Import işi bulunamadı.');
        }

        if ($job->phase === WordPressImportJob::PHASE_DONE) {
            return $this->buildResponse($job, 'İçe aktarma zaten tamamlanmış.', true);
        }

        $author = $this->userRepository->find($job->authorId);
        if (!$author instanceof User) {
            throw new \RuntimeException('Yazar kullanıcı bulunamadı.');
        }

        $parse = $this->jobManager->loadParseCache($job->parseCachePath);
        $this->mediaDownloader->setUrlMap($job->urlMap);

        try {
            $message = match ($job->phase) {
                WordPressImportJob::PHASE_TERMS => $this->runTermsStep($job, $parse),
                WordPressImportJob::PHASE_MEDIA => $this->runMediaStep($job, $parse, $author),
                WordPressImportJob::PHASE_POSTS => $this->runPostsStep($job, $parse, $author),
                WordPressImportJob::PHASE_FINALIZE => $this->runFinalizeStep($job, $parse),
                default => 'Bilinmeyen adım.',
            };

            $job->urlMap = $this->mediaDownloader->getUrlMap();
            if ($this->entityManager->isOpen()) {
                $this->entityManager->flush();
            }
            $this->jobManager->save($job);

            $done = $job->phase === WordPressImportJob::PHASE_DONE;
            if ($done) {
                $this->jobManager->delete($job);
            }

            return $this->buildResponse($job, $message, $done);
        } catch (\Throwable $e) {
            $this->addError($job, $e->getMessage());
            $this->jobManager->save($job);

            throw $e;
        }
    }

    /** @return array{success: bool, message: string} */
    public function cancelJob(string $jobId): array
    {
        $job = $this->jobManager->load($jobId);
        if ($job === null) {
            return ['success' => false, 'message' => 'İş bulunamadı.'];
        }

        if ($job->phase !== WordPressImportJob::PHASE_DONE && $job->phase !== WordPressImportJob::PHASE_TERMS) {
            $rollback = $this->rollbackService->rollbackBatch($job->batchId);
            $this->jobManager->delete($job);

            return ['success' => true, 'message' => 'İçe aktarma iptal edildi. ' . $rollback['message']];
        }

        $this->jobManager->delete($job);

        return ['success' => true, 'message' => 'İçe aktarma iptal edildi.'];
    }

    /** @return array<string, mixed> */
    public function getStatus(string $jobId): array
    {
        $job = $this->jobManager->load($jobId);
        if ($job === null) {
            throw new \RuntimeException('Import işi bulunamadı.');
        }

        return $this->buildResponse($job, 'Durum sorgulandı.', $job->phase === WordPressImportJob::PHASE_DONE);
    }

    private function runTermsStep(WordPressImportJob $job, WxrParseResult $parse): string
    {
        $this->importTerms($parse, $job->stats);
        $job->termsImported = true;
        $job->phase = $job->downloadMedia && $job->mediaQueue !== []
            ? WordPressImportJob::PHASE_MEDIA
            : WordPressImportJob::PHASE_POSTS;

        return sprintf(
            'Kategoriler ve etiketler hazır (%d kategori, %d etiket).',
            $job->stats['categories'],
            $job->stats['tags']
        );
    }

    private function runMediaStep(WordPressImportJob $job, WxrParseResult $parse, User $author): string
    {
        $limit = $this->mediaChunkSize($job->chunkSize);
        $chunk = array_slice($job->mediaQueue, $job->mediaOffset, $limit);
        $processed = 0;

        foreach ($chunk as $url) {
            $alt = null;
            foreach ($parse->attachments as $attachment) {
                if ($attachment->url === $url) {
                    $alt = $attachment->alt;
                    break;
                }
            }

            $local = $this->mediaDownloader->download($url, $author, $job->batchId, $alt);
            if ($local !== null) {
                ++$job->stats['media'];
            } else {
                ++$job->stats['media_failed'];
                $this->addError($job, 'Medya indirilemedi: ' . $url);
            }
            ++$processed;
        }

        $job->mediaOffset += $processed;

        if ($job->mediaOffset >= count($job->mediaQueue)) {
            $job->phase = WordPressImportJob::PHASE_POSTS;
        }

        return sprintf(
            '%d medya işlendi (%d / %d).',
            $processed,
            min($job->mediaOffset, count($job->mediaQueue)),
            count($job->mediaQueue)
        );
    }

    private function runPostsStep(WordPressImportJob $job, WxrParseResult $parse, User $author): string
    {
        $chunk = array_slice($job->postQueue, $job->postOffset, $job->chunkSize);
        $categoryMap = $this->buildCategoryMap($parse);
        $tagMap = $this->buildTagMap($parse);
        $imported = 0;

        foreach ($chunk as $wpPostId) {
            if (!isset($parse->posts[$wpPostId])) {
                continue;
            }

            $wxrPost = $parse->posts[$wpPostId];
            if ($this->postRepository->findOneByWpImportId($wxrPost->id) !== null) {
                ++$job->stats['skipped'];
                continue;
            }

            try {
                $post = $this->createPostFromWxr($wxrPost, $author, $parse, $job->batchId);
                $this->attachTerms($post, $wxrPost, $categoryMap, $tagMap);
                $this->entityManager->persist($post);
                $this->entityManager->flush();

                if ($wxrPost->type === 'page') {
                    ++$job->stats['pages'];
                } else {
                    ++$job->stats['posts'];
                }

                foreach ($wxrPost->comments as $wxrComment) {
                    if ($this->commentRepository->findOneByWpImportId($wxrComment->id) !== null) {
                        continue;
                    }

                    $comment = new Comment();
                    $comment->setPost($post);
                    $comment->setAuthorName($wxrComment->author);
                    $comment->setAuthorEmail($wxrComment->email);
                    $comment->setAuthorWebsite($wxrComment->authorUrl);
                    $comment->setContent($this->sanitizer->sanitize($wxrComment->content));
                    $comment->setStatus($wxrComment->approved ? CommentStatus::Approved : CommentStatus::Pending);
                    $comment->setIpAddress($wxrComment->ip);
                    $comment->setUserAgent($wxrComment->userAgent);
                    $comment->setCreatedAt($this->parseDate($wxrComment->date));
                    $comment->setUpdatedAt($this->parseDate($wxrComment->date));
                    $comment->setWpImportId($wxrComment->id);
                    $comment->setImportBatch($job->batchId);
                    $this->entityManager->persist($comment);
                    $this->entityManager->flush();

                    $this->subscriberService->subscribeFromComment($wxrComment->email);

                    $job->commentMap[$wxrComment->id] = (int) $comment->getId();
                    ++$job->stats['comments'];
                }

                ++$imported;
            } catch (\Throwable $e) {
                $this->addError($job, sprintf('WP #%d (%s): %s', $wxrPost->id, $wxrPost->title, $e->getMessage()));
            }
        }

        $job->postOffset += count($chunk);

        if ($job->postOffset >= count($job->postQueue)) {
            $job->phase = WordPressImportJob::PHASE_FINALIZE;
        }

        return sprintf(
            '%d içerik aktarıldı (%d / %d).',
            $imported,
            min($job->postOffset, count($job->postQueue)),
            count($job->postQueue)
        );
    }

    private function runFinalizeStep(WordPressImportJob $job, WxrParseResult $parse): string
    {
        foreach ($parse->posts as $wxrPost) {
            foreach ($wxrPost->comments as $wxrComment) {
                if ($wxrComment->parentId <= 0) {
                    continue;
                }
                if (!isset($job->commentMap[$wxrComment->id], $job->commentMap[$wxrComment->parentId])) {
                    continue;
                }

                $comment = $this->commentRepository->find($job->commentMap[$wxrComment->id]);
                $parent = $this->commentRepository->find($job->commentMap[$wxrComment->parentId]);
                if ($comment !== null && $parent !== null) {
                    $comment->setParent($parent);
                }
            }
        }

        $this->entityManager->flush();
        $job->phase = WordPressImportJob::PHASE_DONE;

        return 'Yorum ilişkileri tamamlandı. İçe aktarma bitti.';
    }

    /** @param array<string, mixed> $stats */
    private function importTerms(WxrParseResult $parse, array &$stats): void
    {
        $this->categoryImportCache = [];
        $this->tagImportCache = [];

        foreach ($parse->categories as $slug => $term) {
            $this->ensureCategory($term->name, (string) $slug, $stats);
        }

        foreach ($parse->tags as $slug => $term) {
            $this->ensureTag($term->name, (string) $slug, $stats);
        }

        foreach ($parse->posts as $wxrPost) {
            foreach ($wxrPost->terms as $term) {
                $slug = (string) $term['slug'];
                if ($term['domain'] === 'category') {
                    $this->ensureCategory((string) $term['name'], $slug, $stats);
                }
                if ($term['domain'] === 'post_tag') {
                    $this->ensureTag((string) $term['name'], $slug, $stats);
                }
            }
        }

        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $stats */
    private function ensureCategory(string $name, string $slug, array &$stats): void
    {
        if ($this->findCategoryBySlug($slug) !== null) {
            return;
        }

        $this->findOrCreateCategory($name, $slug);
        ++$stats['categories'];
    }

    /** @param array<string, mixed> $stats */
    private function ensureTag(string $name, string $slug, array &$stats): void
    {
        if ($this->findTagBySlug($slug) !== null) {
            return;
        }

        $this->findOrCreateTag($name, $slug);
        ++$stats['tags'];
    }

    private function normalizeTermSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return $slug !== '' ? $slug : 'term-' . bin2hex(random_bytes(4));
    }

    private function findCategoryBySlug(string $slug): ?Category
    {
        $slug = $this->normalizeTermSlug($slug);
        if (isset($this->categoryImportCache[$slug])) {
            return $this->categoryImportCache[$slug];
        }

        $existing = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if ($existing !== null) {
            $this->categoryImportCache[$slug] = $existing;
        }

        return $existing;
    }

    private function findTagBySlug(string $slug): ?Tag
    {
        $slug = $this->normalizeTermSlug($slug);
        if (isset($this->tagImportCache[$slug])) {
            return $this->tagImportCache[$slug];
        }

        $existing = $this->tagRepository->findOneBy(['slug' => $slug]);
        if ($existing !== null) {
            $this->tagImportCache[$slug] = $existing;
        }

        return $existing;
    }

    /** @return array<string, Category> */
    private function buildCategoryMap(WxrParseResult $parse): array
    {
        $map = [];
        foreach ($parse->categories as $slug => $term) {
            $entity = $this->findCategoryBySlug((string) $slug);
            if ($entity !== null) {
                $map[$this->normalizeTermSlug((string) $slug)] = $entity;
            }
        }
        foreach ($parse->posts as $wxrPost) {
            foreach ($wxrPost->terms as $term) {
                if ($term['domain'] !== 'category') {
                    continue;
                }
                $slug = $this->normalizeTermSlug((string) $term['slug']);
                if (!isset($map[$slug])) {
                    $entity = $this->findCategoryBySlug($slug);
                    if ($entity !== null) {
                        $map[$slug] = $entity;
                    }
                }
            }
        }

        return $map;
    }

    /** @return array<string, Tag> */
    private function buildTagMap(WxrParseResult $parse): array
    {
        $map = [];
        foreach ($parse->tags as $slug => $term) {
            $entity = $this->findTagBySlug((string) $slug);
            if ($entity !== null) {
                $map[$this->normalizeTermSlug((string) $slug)] = $entity;
            }
        }
        foreach ($parse->posts as $wxrPost) {
            foreach ($wxrPost->terms as $term) {
                if ($term['domain'] !== 'post_tag') {
                    continue;
                }
                $slug = $this->normalizeTermSlug((string) $term['slug']);
                if (!isset($map[$slug])) {
                    $entity = $this->findTagBySlug($slug);
                    if ($entity !== null) {
                        $map[$slug] = $entity;
                    }
                }
            }
        }

        return $map;
    }

    private function createPostFromWxr(WxrPost $wxrPost, User $author, WxrParseResult $parse, string $batchId): Post
    {
        $content = $this->mediaDownloader->replaceContentUrls($wxrPost->content);
        $content = $this->sanitizer->sanitize($content);

        $post = new Post();
        $post->setAuthor($author);
        $post->setTitle($wxrPost->title);
        $post->setSlug($this->preserveImportSlug($wxrPost->slug));
        $post->setContent($content);
        $post->setExcerpt($this->normalizeExcerpt($wxrPost->excerpt));
        $post->setType($wxrPost->type === 'page' ? PostType::Page : PostType::Article);
        $post->setStatus($this->mapStatus($wxrPost->status));
        $post->setPublishedAt($this->parseDate($wxrPost->postDate));
        $post->setIsSticky($wxrPost->isSticky);

        if (isset($wxrPost->meta['post_views_count'])) {
            $post->setViewCount(max(0, (int) $wxrPost->meta['post_views_count']));
        }

        $this->addMeta($post, 'wp_import_id', (string) $wxrPost->id);
        $this->addMeta($post, 'wp_import_batch', $batchId);
        if ($parse->baseUrl !== '') {
            $this->addMeta($post, 'wp_import_url', $parse->baseUrl);
        }
        if ($wxrPost->link !== '') {
            $this->addMeta($post, 'wp_original_link', $wxrPost->link);
        }

        $featured = $this->resolveFeaturedImage($wxrPost, $parse, $author, $batchId);
        if ($featured !== null) {
            $post->setFeaturedImage($featured);
        }

        foreach ($wxrPost->meta as $key => $value) {
            if ($key === '_thumbnail_id' || $value === '') {
                continue;
            }
            if (isset(self::META_MAP[$key])) {
                $this->addMeta($post, self::META_MAP[$key], $value);
                continue;
            }
            if ($this->shouldStoreRawMeta($key)) {
                $this->addMeta($post, 'wp_' . $key, $value);
            }
        }

        return $post;
    }

    private function resolveFeaturedImage(WxrPost $wxrPost, WxrParseResult $parse, User $author, string $batchId): ?string
    {
        if (!isset($wxrPost->meta['_thumbnail_id'])) {
            return null;
        }

        $thumbId = (int) $wxrPost->meta['_thumbnail_id'];
        if (!isset($parse->attachments[$thumbId])) {
            return null;
        }

        $attachment = $parse->attachments[$thumbId];

        return $this->mediaDownloader->resolveUrl($attachment->url)
            ?? $this->mediaDownloader->download($attachment->url, $author, $batchId, $attachment->alt);
    }

    /** @param array<string, Category> $categoryMap @param array<string, Tag> $tagMap */
    private function attachTerms(Post $post, WxrPost $wxrPost, array $categoryMap, array $tagMap): void
    {
        foreach ($wxrPost->terms as $term) {
            $slug = $this->normalizeTermSlug((string) $term['slug']);
            if ($term['domain'] === 'category' && isset($categoryMap[$slug])) {
                $post->addCategory($categoryMap[$slug]);
            }
            if ($term['domain'] === 'post_tag' && isset($tagMap[$slug])) {
                $post->addTag($tagMap[$slug]);
            }
        }
    }

    private function shouldStoreRawMeta(string $key): bool
    {
        if (str_starts_with($key, 'rank_math_')) {
            return true;
        }
        foreach (self::SKIP_META_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return false;
            }
        }

        return !str_starts_with($key, '_');
    }

    private function addMeta(Post $post, string $key, string $value): void
    {
        $meta = new PostMeta();
        $meta->setPost($post);
        $meta->setMetaKey($key);
        $meta->setMetaValue($value);
        $post->addMeta($meta);
    }

    private function findOrCreateCategory(string $name, string $slug): Category
    {
        $slug = $this->normalizeTermSlug($slug);
        $existing = $this->findCategoryBySlug($slug);
        if ($existing !== null) {
            return $existing;
        }

        $cat = new Category();
        $cat->setName($name);
        $cat->setSlug($slug);
        $cat->setIsActive(true);
        $this->entityManager->persist($cat);
        $this->categoryImportCache[$slug] = $cat;

        return $cat;
    }

    private function findOrCreateTag(string $name, string $slug): Tag
    {
        $slug = $this->normalizeTermSlug($slug);
        $existing = $this->findTagBySlug($slug);
        if ($existing !== null) {
            return $existing;
        }

        $tag = new Tag();
        $tag->setName($name);
        $tag->setSlug($slug);
        $this->entityManager->persist($tag);
        $this->tagImportCache[$slug] = $tag;

        return $tag;
    }

    private function preserveImportSlug(string $slug): string
    {
        $base = trim($slug);
        if ($base === '') {
            $base = 'post-' . bin2hex(random_bytes(4));
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $base)) {
            $base = $this->slugger->slugify($base);
        }

        $candidate = $base;
        $i = 1;
        while ($this->postRepository->isSlugTaken($candidate)) {
            $candidate = $base . '-' . $i;
            ++$i;
        }

        return $candidate;
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $this->slugger->slugify($slug);
        $candidate = $base;
        $i = 1;
        while ($this->postRepository->isSlugTaken($candidate)) {
            $candidate = $base . '-' . $i;
            ++$i;
        }

        return $candidate;
    }

    private function normalizeExcerpt(string $excerpt): ?string
    {
        $text = trim(strip_tags($excerpt));

        return $text === '' ? null : mb_substr($text, 0, 500);
    }

    private function mapStatus(string $status): PostStatus
    {
        return match ($status) {
            'publish', 'published' => PostStatus::Published,
            'private' => PostStatus::Private,
            'draft', 'pending', 'future' => PostStatus::Draft,
            default => PostStatus::Draft,
        };
    }

    private function parseDate(string $date): \DateTimeInterface
    {
        try {
            return new \DateTime($date);
        } catch (\Throwable) {
            return new \DateTime();
        }
    }

    private function normalizeChunkSize(int $chunkSize): int
    {
        return match ($chunkSize) {
            20, 50 => $chunkSize,
            default => 10,
        };
    }

    private function mediaChunkSize(int $chunkSize): int
    {
        return min($chunkSize, 10);
    }

    private function addError(WordPressImportJob $job, string $error): void
    {
        if (count($job->errors) < 100) {
            $job->errors[] = $error;
        }
    }

    /** @return array{batch_id: string, posts: int, pages: int, categories: int, tags: int, comments: int, media: int, skipped: int, media_failed: int, errors: list<string>} */
    private function emptyStats(string $batchId, int $skipped = 0): array
    {
        return [
            'batch_id' => $batchId,
            'posts' => 0,
            'pages' => 0,
            'categories' => 0,
            'tags' => 0,
            'comments' => 0,
            'media' => 0,
            'skipped' => $skipped,
            'media_failed' => 0,
            'errors' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildResponse(WordPressImportJob $job, string $message, bool $done): array
    {
        $phaseLabels = [
            WordPressImportJob::PHASE_TERMS => 'Kategoriler ve etiketler',
            WordPressImportJob::PHASE_MEDIA => 'Medya indiriliyor',
            WordPressImportJob::PHASE_POSTS => 'İçerikler aktarılıyor',
            WordPressImportJob::PHASE_FINALIZE => 'Yorumlar tamamlanıyor',
            WordPressImportJob::PHASE_DONE => 'Tamamlandı',
        ];

        $phase = $job->phase;
        $current = match ($phase) {
            WordPressImportJob::PHASE_TERMS => 0,
            WordPressImportJob::PHASE_MEDIA => $job->mediaOffset,
            WordPressImportJob::PHASE_POSTS => $job->postOffset,
            WordPressImportJob::PHASE_FINALIZE => $job->totalPosts,
            WordPressImportJob::PHASE_DONE => $job->totalPosts,
            default => 0,
        };
        $total = match ($phase) {
            WordPressImportJob::PHASE_TERMS => 1,
            WordPressImportJob::PHASE_MEDIA => max(1, $job->totalMedia),
            WordPressImportJob::PHASE_POSTS, WordPressImportJob::PHASE_FINALIZE, WordPressImportJob::PHASE_DONE => max(1, $job->totalPosts),
            default => 1,
        };

        $overall = $this->overallPercent($job);

        return [
            'done' => $done,
            'job_id' => $job->jobId,
            'batch_id' => $job->stats['batch_id'],
            'message' => $message,
            'phase' => $phase,
            'phase_label' => $phaseLabels[$phase] ?? $phase,
            'progress' => [
                'current' => $current,
                'total' => $total,
                'phase_percent' => $total > 0 ? (int) round(min(100, ($current / $total) * 100)) : 0,
                'overall_percent' => $overall,
            ],
            'stats' => $job->stats,
            'errors' => $job->errors,
        ];
    }

    private function overallPercent(WordPressImportJob $job): int
    {
        $termsWeight = 5;
        $mediaWeight = $job->downloadMedia && $job->totalMedia > 0 ? 30 : 0;
        $postsWeight = 60;
        $finalizeWeight = 5;

        $totalWeight = $termsWeight + $mediaWeight + $postsWeight + $finalizeWeight;
        $progress = 0.0;

        if ($job->termsImported || $job->phase !== WordPressImportJob::PHASE_TERMS) {
            $progress += $termsWeight;
        }

        if ($mediaWeight > 0) {
            $progress += $mediaWeight * min(1, $job->mediaOffset / max(1, $job->totalMedia));
        } elseif ($job->phase !== WordPressImportJob::PHASE_TERMS && $job->phase !== WordPressImportJob::PHASE_MEDIA) {
            $progress += $mediaWeight;
        }

        if ($job->phase === WordPressImportJob::PHASE_POSTS || $job->phase === WordPressImportJob::PHASE_FINALIZE || $job->phase === WordPressImportJob::PHASE_DONE) {
            $progress += $postsWeight * min(1, $job->postOffset / max(1, $job->totalPosts));
        }

        if ($job->phase === WordPressImportJob::PHASE_FINALIZE) {
            $progress += $finalizeWeight * 0.5;
        }
        if ($job->phase === WordPressImportJob::PHASE_DONE) {
            $progress += $finalizeWeight;
        }

        return (int) round(min(100, ($progress / $totalWeight) * 100));
    }
}
