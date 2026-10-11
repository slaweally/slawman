<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Repository\CommentRepository;
use App\Repository\MediaRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WordPressImportRollbackService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly MediaRepository $mediaRepository,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
    ) {
    }

    /** @return array{success: bool, message: string, deleted: array{posts: int, comments: int, media: int}} */
    public function rollbackBatch(string $batchId): array
    {
        if ($batchId === '') {
            return ['success' => false, 'message' => 'Geçersiz batch ID.', 'deleted' => ['posts' => 0, 'comments' => 0, 'media' => 0]];
        }

        $filters = $this->entityManager->getFilters();
        $softDeleteWasEnabled = $filters->isEnabled('softdeleteable');
        if ($softDeleteWasEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            $posts = $this->postRepository->findByImportBatch($batchId, true);
            $postCount = count($posts);
            $commentCount = $this->commentRepository->deleteByImportBatch($batchId);
            $mediaItems = $this->mediaRepository->findByImportBatch($batchId);
            $mediaCount = count($mediaItems);

            foreach ($posts as $post) {
                foreach ($post->getComments()->toArray() as $comment) {
                    $this->entityManager->remove($comment);
                }
                $this->entityManager->remove($post);
            }

            foreach ($mediaItems as $media) {
                $path = $media->getPath();
                if ($path !== null && is_file($path)) {
                    @unlink($path);
                }
                $this->entityManager->remove($media);
            }

            $batchDir = $this->uploadDir . '/wp-import/' . $batchId;
            if (is_dir($batchDir)) {
                $this->removeDirectory($batchDir);
            }

            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => sprintf('%d içerik, %d yorum, %d medya kalıcı olarak silindi.', $postCount, $commentCount, $mediaCount),
                'deleted' => ['posts' => $postCount, 'comments' => $commentCount, 'media' => $mediaCount],
            ];
        } finally {
            if ($softDeleteWasEnabled) {
                $filters->enable('softdeleteable');
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
