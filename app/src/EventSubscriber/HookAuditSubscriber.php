<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Contract\Hook\DomainHookSubscriberInterface;
use App\Event\CommentLifecycleEvent;
use App\Event\MediaLifecycleEvent;
use App\Event\PostLifecycleEvent;
use App\Message\AsyncHookMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class HookAuditSubscriber implements EventSubscriberInterface, DomainHookSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostLifecycleEvent::class => 'onPostLifecycle',
            CommentLifecycleEvent::class => 'onCommentLifecycle',
            MediaLifecycleEvent::class => 'onMediaLifecycle',
        ];
    }

    public static function getHookSubscriptions(): array
    {
        return [
            'post.lifecycle' => PostLifecycleEvent::class,
            'comment.lifecycle' => CommentLifecycleEvent::class,
            'media.lifecycle' => MediaLifecycleEvent::class,
        ];
    }

    public function onPostLifecycle(PostLifecycleEvent $event): void
    {
        $this->logger->info('post.lifecycle', ['action' => $event->getAction(), 'post_id' => $event->getPost()->getId()]);
        $this->messageBus->dispatch(new AsyncHookMessage('post.lifecycle', ['action' => $event->getAction(), 'post_id' => $event->getPost()->getId()]));
    }

    public function onCommentLifecycle(CommentLifecycleEvent $event): void
    {
        $this->logger->info('comment.lifecycle', ['action' => $event->getAction(), 'comment_id' => $event->getComment()->getId()]);
        $this->messageBus->dispatch(new AsyncHookMessage('comment.lifecycle', ['action' => $event->getAction(), 'comment_id' => $event->getComment()->getId()]));
    }

    public function onMediaLifecycle(MediaLifecycleEvent $event): void
    {
        $this->logger->info('media.lifecycle', ['action' => $event->getAction(), 'media_id' => $event->getMedia()->getId()]);
        $this->messageBus->dispatch(new AsyncHookMessage('media.lifecycle', ['action' => $event->getAction(), 'media_id' => $event->getMedia()->getId()]));
    }
}
