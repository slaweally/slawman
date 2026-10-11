<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Comment;
use Symfony\Contracts\EventDispatcher\Event;

class CommentLifecycleEvent extends Event
{
    public function __construct(
        private readonly Comment $comment,
        private readonly string $action,
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
