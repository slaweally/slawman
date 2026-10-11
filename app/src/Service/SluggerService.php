<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Enum\PostType;

class SluggerService
{
    public function __construct(private readonly PermalinkService $permalinkService)
    {
    }

    public function buildPostPath(string $type, string $slug): string
    {
        $post = new Post();
        $post->setSlug($slug);
        $post->setType(PostType::from($type));

        return $this->permalinkService->buildPostPath($post);
    }

    public function buildPostPathForEntity(Post $post): string
    {
        return $this->permalinkService->buildPostPath($post);
    }

    public function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '';
        $text = trim($text, '-');

        return $text !== '' ? $text : 'item-' . bin2hex(random_bytes(4));
    }
}
