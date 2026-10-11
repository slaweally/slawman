<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Post;
use Symfony\Contracts\EventDispatcher\Event;

class PostLifecycleEvent extends Event
{
    public function __construct(
        private readonly Post $post,
        private readonly string $action,
    ) {
    }

    public function getPost(): Post
    {
        return $this->post;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
