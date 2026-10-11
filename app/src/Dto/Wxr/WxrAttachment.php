<?php

declare(strict_types=1);

namespace App\Dto\Wxr;

final class WxrAttachment
{
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        public readonly string $title,
        public readonly ?string $alt,
        public readonly string $mimeType,
    ) {
    }
}
