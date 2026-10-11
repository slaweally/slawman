<?php

declare(strict_types=1);

namespace App\Dto\Wxr;

final class WxrParseResult
{
    public string $siteTitle = '';
    public string $baseUrl = '';
    public string $blogUrl = '';

    /** @var array<int, WxrAttachment> */
    public array $attachments = [];

    /** @var array<int, WxrPost> */
    public array $posts = [];

    /** @var array<string, WxrTerm> */
    public array $categories = [];

    /** @var array<string, WxrTerm> */
    public array $tags = [];

    /** @var list<string> */
    public array $errors = [];

    public function counts(): array
    {
        return [
            'posts' => count(array_filter($this->posts, fn (WxrPost $p) => $p->type === 'post')),
            'pages' => count(array_filter($this->posts, fn (WxrPost $p) => $p->type === 'page')),
            'attachments' => count($this->attachments),
            'categories' => count($this->categories),
            'tags' => count($this->tags),
            'comments' => array_sum(array_map(fn (WxrPost $p) => count($p->comments), $this->posts)),
        ];
    }
}
