<?php

declare(strict_types=1);

namespace App\Message;

class AsyncHookMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $name,
        private readonly array $payload = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
