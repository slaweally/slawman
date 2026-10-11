<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SettingsChangedEvent extends Event
{
    /** @param array<string, mixed> $keys */
    public function __construct(private readonly array $keys)
    {
    }

    /** @return array<string, mixed> */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
