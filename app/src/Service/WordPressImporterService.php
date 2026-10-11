<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\PostRepository;
use App\Service\Import\WordPressImportRunnerService;
use App\Service\Import\WxrImportValidator;
use App\Service\Import\WordPressImportRollbackService;

class WordPressImporterService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly WxrImportValidator $validator,
        private readonly WordPressImportRollbackService $rollbackService,
        private readonly WordPressImportRunnerService $runner,
    ) {
    }

    /** @return array{valid: bool, errors: list<string>, warnings: list<string>, preview: array<string, mixed>} */
    public function preview(string $filePath): array
    {
        $validation = $this->validator->validate($filePath);

        return [
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'preview' => $validation['preview'],
        ];
    }

    /**
     * @return array{
     *     batch_id: string,
     *     posts: int, pages: int, categories: int, tags: int, comments: int,
     *     media: int, skipped: int, media_failed: int,
     *     errors: list<string>
     * }
     */
    public function importFromWxr(string $filePath, User $author, bool $downloadMedia = true, int $chunkSize = 20): array
    {
        $start = $this->runner->startJob($filePath, $author, $chunkSize, $downloadMedia);

        do {
            $step = $this->runner->runStep($start['job_id']);
        } while (!($step['done'] ?? false));

        return $step['stats'] ?? [];
    }

    /** @return array{success: bool, message: string, deleted: array{posts: int, comments: int, media: int}} */
    public function rollback(string $batchId): array
    {
        return $this->rollbackService->rollbackBatch($batchId);
    }

    /** @return string[] */
    public function listBatches(): array
    {
        return $this->postRepository->findImportBatches();
    }

    /**
     * @param array{host: string, port: int, database: string, user: string, password: string, prefix: string, url: string} $config
     *
     * @return array{batch_id: string, posts: int, pages: int, categories: int, tags: int, comments: int, media: int, skipped: int, media_failed: int, errors: list<string>}
     */
    public function importFromDatabase(array $config, User $author): array
    {
        throw new \RuntimeException('MySQL içe aktarma geçici olarak devre dışı. Lütfen WXR dosyası kullanın.');
    }
}
