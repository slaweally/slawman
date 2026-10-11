<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Media;
use Symfony\Contracts\EventDispatcher\Event;

class MediaLifecycleEvent extends Event
{
    public function __construct(
        private readonly Media $media,
        private readonly string $action,
    ) {
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
