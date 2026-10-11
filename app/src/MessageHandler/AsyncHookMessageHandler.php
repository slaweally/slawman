<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AsyncHookMessage;
use App\Repository\PostRepository;
use App\Service\IndexNowService;
use App\Service\PermalinkService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class AsyncHookMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IndexNowService $indexNowService,
        private readonly PostRepository $postRepository,
        private readonly PermalinkService $permalinkService,
    ) {
    }

    public function __invoke(AsyncHookMessage $message): void
    {
        $this->logger->info('async.hook', [
            'name' => $message->getName(),
            'payload' => $message->getPayload(),
        ]);

        if ($message->getName() === 'post.lifecycle') {
            $this->notifyIndexNow($message->getPayload());
        }
    }

    /** @param array<string, mixed> $payload */
    private function notifyIndexNow(array $payload): void
    {
        if (!$this->indexNowService->isEnabled()) {
            return;
        }

        $action = $payload['action'] ?? '';
        if (!in_array($action, ['created', 'updated', 'status_change'], true)) {
            return;
        }

        $postId = $payload['post_id'] ?? null;
        if (!is_int($postId)) {
            return;
        }

        $post = $this->postRepository->find($postId);
        if ($post === null || $post->getStatus()->value !== 'published') {
            return;
        }

        $this->indexNowService->submitUrl(
            $this->permalinkService->buildPostUrl($post, UrlGeneratorInterface::ABSOLUTE_URL)
        );
    }
}
