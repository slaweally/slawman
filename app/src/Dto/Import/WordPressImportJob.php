<?php

declare(strict_types=1);

namespace App\Dto\Import;

final class WordPressImportJob
{
    public const PHASE_TERMS = 'terms';
    public const PHASE_MEDIA = 'media';
    public const PHASE_POSTS = 'posts';
    public const PHASE_FINALIZE = 'finalize';
    public const PHASE_DONE = 'done';

    /**
     * @param list<int>              $postQueue
     * @param list<string>           $mediaQueue
     * @param array<string, string>  $urlMap
     * @param array<int, int>        $commentMap
     * @param array<string, mixed>   $stats
     * @param list<string>           $errors
     */
    public function __construct(
        public string $jobId,
        public string $batchId,
        public string $filePath,
        public string $parseCachePath,
        public int $authorId,
        public string $phase = self::PHASE_TERMS,
        public int $chunkSize = 10,
        public bool $downloadMedia = true,
        public bool $termsImported = false,
        public int $postOffset = 0,
        public int $mediaOffset = 0,
        public array $postQueue = [],
        public array $mediaQueue = [],
        public array $urlMap = [],
        public array $commentMap = [],
        public array $stats = [],
        public array $errors = [],
        public int $totalPosts = 0,
        public int $totalMedia = 0,
        public string $createdAt = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'batch_id' => $this->batchId,
            'file_path' => $this->filePath,
            'parse_cache_path' => $this->parseCachePath,
            'author_id' => $this->authorId,
            'phase' => $this->phase,
            'chunk_size' => $this->chunkSize,
            'download_media' => $this->downloadMedia,
            'terms_imported' => $this->termsImported,
            'post_offset' => $this->postOffset,
            'media_offset' => $this->mediaOffset,
            'post_queue' => $this->postQueue,
            'media_queue' => $this->mediaQueue,
            'url_map' => $this->urlMap,
            'comment_map' => $this->commentMap,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'total_posts' => $this->totalPosts,
            'total_media' => $this->totalMedia,
            'created_at' => $this->createdAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            jobId: (string) $data['job_id'],
            batchId: (string) $data['batch_id'],
            filePath: (string) $data['file_path'],
            parseCachePath: (string) $data['parse_cache_path'],
            authorId: (int) $data['author_id'],
            phase: (string) ($data['phase'] ?? self::PHASE_TERMS),
            chunkSize: (int) ($data['chunk_size'] ?? 10),
            downloadMedia: (bool) ($data['download_media'] ?? true),
            termsImported: (bool) ($data['terms_imported'] ?? false),
            postOffset: (int) ($data['post_offset'] ?? 0),
            mediaOffset: (int) ($data['media_offset'] ?? 0),
            postQueue: array_values(array_map('intval', $data['post_queue'] ?? [])),
            mediaQueue: array_values(array_map('strval', $data['media_queue'] ?? [])),
            urlMap: (array) ($data['url_map'] ?? []),
            commentMap: array_map('intval', (array) ($data['comment_map'] ?? [])),
            stats: (array) ($data['stats'] ?? []),
            errors: array_values(array_map('strval', $data['errors'] ?? [])),
            totalPosts: (int) ($data['total_posts'] ?? 0),
            totalMedia: (int) ($data['total_media'] ?? 0),
            createdAt: (string) ($data['created_at'] ?? ''),
        );
    }
}
