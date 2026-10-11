<?php

declare(strict_types=1);

namespace App\Dto\Wxr;

final class WxrTerm
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $taxonomy,
    ) {
    }
}
