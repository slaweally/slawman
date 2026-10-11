<?php

declare(strict_types=1);

namespace App\Dto\Wxr;

final class WxrComment
{
    public function __construct(
        public readonly int $id,
        public readonly int $parentId,
        public readonly string $author,
        public readonly string $email,
        public readonly string $content,
        public readonly string $date,
        public readonly bool $approved,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly ?string $authorUrl = null,
    ) {
    }
}
