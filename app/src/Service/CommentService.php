<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Post;
use App\Enum\CommentStatus;
use App\Event\CommentLifecycleEvent;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AdminNotificationService;
use App\Service\SettingsService;
use App\Service\SubscriberService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentRepository $commentRepository,
        private readonly SettingsService $settingsService,
        private readonly SubscriberService $subscriberService,
        private readonly AdminNotificationService $adminNotificationService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settingsService->isTruthy('comments_enabled');
    }

    /** @return Comment[] */
    public function getApprovedForPost(Post $post): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return $this->commentRepository->findApprovedTopLevelByPost((int) $post->getId());
    }

    public function create(
        Post $post,
        string $authorName,
        string $authorEmail,
        string $content,
        ?Comment $parent = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $authorWebsite = null,
    ): Comment {
        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthorName($authorName);
        $comment->setAuthorEmail($authorEmail);
        $comment->setAuthorWebsite($authorWebsite);
        $comment->setContent($content);
        $comment->setParent($parent);
        $comment->setIpAddress($ip);
        $comment->setUserAgent($userAgent);
        $comment->setStatus(
            $this->settingsService->isTruthy('moderation_enabled')
                ? CommentStatus::Pending
                : CommentStatus::Approved
        );

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->subscriberService->subscribeFromComment($authorEmail);

        $this->adminNotificationService->notifyNewComment($comment);
        $this->eventDispatcher->dispatch(new CommentLifecycleEvent($comment, 'created'));

        return $comment;
    }

    public function createAdminReply(
        Post $post,
        string $authorName,
        string $authorEmail,
        string $content,
        Comment $parent,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Comment {
        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthorName($authorName);
        $comment->setAuthorEmail($authorEmail);
        $comment->setContent($content);
        $comment->setParent($parent);
        $comment->setIpAddress($ip);
        $comment->setUserAgent($userAgent);
        $comment->setStatus(CommentStatus::Approved);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new CommentLifecycleEvent($comment, 'admin_reply'));

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $this->eventDispatcher->dispatch(new CommentLifecycleEvent($comment, 'deleted'));
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }

    public function purgeTrash(): int
    {
        return $this->commentRepository->deleteAllTrash();
    }
}
