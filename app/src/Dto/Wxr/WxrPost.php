<?php

declare(strict_types=1);

namespace App\Dto\Wxr;

final class WxrPost
{
    /**
     * @param list<array{domain: string, slug: string, name: string}> $terms
     * @param array<string, string>                                  $meta
     * @param list<WxrComment>                                       $comments
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $content,
        public readonly string $excerpt,
        public readonly string $status,
        public readonly string $postDate,
        public readonly bool $isSticky,
        public readonly array $terms,
        public readonly array $meta,
        public readonly array $comments,
        public readonly string $link,
    ) {
    }
}
