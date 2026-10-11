<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\PostType;
use App\PostType\PostTypeConfig;

/**
 * Google/Bing/Yandex'in beklediği schema.org JSON-LD bloklarını üretir.
 * Tek bir <script type="application/ld+json"> içine birden fazla şema
 * @graph altında toplanır (Google'ın önerdiği pattern).
 */
class JsonLdBuilderService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly PostMetaService $postMetaService,
        private readonly PermalinkService $permalinkService,
    ) {
    }

    /** @return array<string, mixed> */
    public function forPost(Post $post): array
    {
        $graph = [
            $this->organization(),
            $this->website(),
            $this->breadcrumbForPost($post),
            $this->articleOrCreativeWork($post),
        ];

        $software = $this->softwareSourceCode($post);
        if ($software !== null) {
            $graph[] = $software;
        }

        $howTo = $this->howTo($post);
        if ($howTo !== null) {
            $graph[] = $howTo;
        }

        return $this->graph(array_values(array_filter($graph)));
    }

    /** @return array<string, mixed> */
    public function forHome(): array
    {
        return $this->graph([
            $this->organization(),
            $this->website(),
        ]);
    }

    /** @return array<string, mixed> */
    public function forArchive(string $title, ?string $canonical = null): array
    {
        $graph = [$this->organization(), $this->website()];

        if ($canonical !== null) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    $this->breadcrumbItem(1, 'Anasayfa', $this->siteUrl()),
                    $this->breadcrumbItem(2, $title, $canonical),
                ],
            ];
        }

        return $this->graph($graph);
    }

    /** @return array<string, mixed> */
    private function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => $nodes,
        ];
    }

    /** @return array<string, mixed> */
    private function organization(): array
    {
        $logo = trim((string) ($this->settingsService->get('site_logo', '') ?? ''));

        $node = [
            '@type' => 'Organization',
            '@id' => $this->siteUrl() . '#organization',
            'name' => $this->siteTitle(),
            'url' => $this->siteUrl(),
        ];

        if ($logo !== '') {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url' => $this->absoluteUrl($logo),
            ];
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->siteUrl() . '#website',
            'name' => $this->siteTitle(),
            'url' => $this->siteUrl(),
            'publisher' => ['@id' => $this->siteUrl() . '#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->siteUrl() . '/ara?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function breadcrumbForPost(Post $post): array
    {
        $items = [$this->breadcrumbItem(1, 'Anasayfa', $this->siteUrl())];
        $position = 2;

        $category = $post->getCategories()->first() ?: null;
        if ($category instanceof Category) {
            $items[] = $this->breadcrumbItem(
                $position++,
                (string) $category->getName(),
                $this->absoluteUrl($this->permalinkService->buildCategoryPath($category))
            );
        }

        $items[] = $this->breadcrumbItem(
            $position,
            (string) ($post->getTitle() ?? PostTypeConfig::label($post->getType())),
            $this->absoluteUrl($this->permalinkService->buildPostPath($post))
        );

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function breadcrumbItem(int $position, string $name, string $url): array
    {
        return [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $name,
            'item' => $url,
        ];
    }

    /** @return array<string, mixed> */
    private function articleOrCreativeWork(Post $post): array
    {
        $author = $post->getAuthor();
        $image = $this->absoluteUrl((string) ($post->getFeaturedImage() ?? ''));
        $canonical = $this->absoluteUrl($this->permalinkService->buildPostPath($post));

        $node = [
            '@type' => $post->getType() === PostType::Article ? 'BlogPosting' : 'CreativeWork',
            '@id' => $canonical . '#content',
            'mainEntityOfPage' => ['@id' => $canonical],
            'headline' => (string) ($post->getTitle() ?? $this->siteTitle()),
            'description' => (string) ($post->getExcerpt() ?? ''),
            'inLanguage' => 'tr-TR',
            'datePublished' => $this->iso($post->getPublishedAt()),
            'dateModified' => $this->iso($post->getUpdatedAt() ?? $post->getPublishedAt()),
            'publisher' => ['@id' => $this->siteUrl() . '#organization'],
        ];

        if ($image !== '') {
            $node['image'] = $image;
        }

        if ($author instanceof User) {
            $node['author'] = $this->person($author);
        }

        $keywords = $this->postKeywords($post);
        if ($keywords !== []) {
            $node['keywords'] = implode(', ', $keywords);
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function person(User $author): array
    {
        $sameAs = array_values(array_filter([
            $author->getWebsite(),
            $author->getGithub(),
            $author->getTwitter(),
            $author->getLinkedin(),
        ]));

        $node = [
            '@type' => 'Person',
            'name' => $author->getDisplayName(),
        ];

        $avatar = $author->getAvatar();
        if ($avatar !== null && $avatar !== '') {
            $node['image'] = $this->absoluteUrl($avatar);
        }

        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /** Software/Snippet tipleri için kaynak kod/yazılım şeması — bu site tipine özgü ayrışma noktası. */
    private function softwareSourceCode(Post $post): ?array
    {
        if (!in_array($post->getType(), [PostType::Software, PostType::Snippet], true)) {
            return null;
        }

        $meta = $this->postMetaService->all($post);
        $canonical = $this->absoluteUrl($this->permalinkService->buildPostPath($post));

        $node = [
            '@type' => $post->getType() === PostType::Software ? 'SoftwareApplication' : 'SoftwareSourceCode',
            '@id' => $canonical . '#software',
            'name' => (string) ($post->getTitle() ?? ''),
            'description' => (string) ($post->getExcerpt() ?? ''),
        ];

        if ($post->getType() === PostType::Software) {
            $version = $meta[PostTypeConfig::META_VERSION] ?? '';
            if ($version !== '') {
                $node['softwareVersion'] = $version;
            }

            $license = $meta[PostTypeConfig::META_LICENSE] ?? '';
            if ($license !== '') {
                $node['license'] = $license;
            }

            $downloadUrl = $meta[PostTypeConfig::META_DOWNLOAD_URL] ?? '';
            if ($downloadUrl !== '') {
                $node['downloadUrl'] = $downloadUrl;
            }

            $node['applicationCategory'] = 'DeveloperApplication';
        } else {
            $sourceUrl = $meta[PostTypeConfig::META_SOURCE_URL] ?? '';
            if ($sourceUrl !== '') {
                $node['codeRepository'] = $sourceUrl;
            }

            if ($post->getProgrammingLanguage() !== null) {
                $node['programmingLanguage'] = $post->getProgrammingLanguage();
            }
        }

        return $node;
    }

    /**
     * HowTo adımları şu an serbest metin ("1. 2. 3." formatlı paragraflar) olarak
     * saklanıyor, yapılandırılmış veri değil. Numaralı satırları best-effort olarak
     * HowToStep dizisine ayrıştırır; hiç eşleşme yoksa (adımlar numaralı yazılmamışsa)
     * null döner ve HowTo şeması hiç eklenmez — yanlış/boş step listesi üretmemek için.
     */
    private function howTo(Post $post): ?array
    {
        if ($post->getType() !== PostType::HowTo) {
            return null;
        }

        $steps = $this->parseNumberedSteps((string) $post->getContent());
        if ($steps === []) {
            return null;
        }

        $meta = $this->postMetaService->all($post);
        $canonical = $this->absoluteUrl($this->permalinkService->buildPostPath($post));

        $node = [
            '@type' => 'HowTo',
            '@id' => $canonical . '#howto',
            'name' => (string) ($post->getTitle() ?? ''),
            'description' => (string) ($post->getExcerpt() ?? ''),
            'step' => array_map(
                static fn (string $text, int $i): array => [
                    '@type' => 'HowToStep',
                    'position' => $i + 1,
                    'text' => $text,
                ],
                $steps,
                array_keys($steps)
            ),
        ];

        $estimatedTime = $meta[PostTypeConfig::META_ESTIMATED_TIME] ?? '';
        if ($estimatedTime !== '' && preg_match('/^\d+$/', $estimatedTime) === 1) {
            $node['totalTime'] = 'PT' . $estimatedTime . 'M';
        }

        return $node;
    }

    /** @return list<string> */
    private function parseNumberedSteps(string $content): array
    {
        $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $content)));
        if ($plain === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $plain) ?: [];
        $steps = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d{1,2}[.)]\s+(.{5,})$/u', $line, $matches) === 1) {
                $steps[] = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            }
        }

        return $steps;
    }

    /** @return list<string> */
    private function postKeywords(Post $post): array
    {
        $tags = [];
        foreach ($post->getTags() as $tag) {
            if ($tag instanceof Tag) {
                $tags[] = (string) $tag->getName();
            }
        }

        return $tags;
    }

    private function iso(?\DateTimeInterface $date): ?string
    {
        return $date?->format(\DateTimeInterface::ATOM);
    }

    private function siteTitle(): string
    {
        return (string) ($this->settingsService->get('site_title', 'Slaweally') ?? 'Slaweally');
    }

    private function siteUrl(): string
    {
        return rtrim((string) ($this->settingsService->get('site_url', '') ?? ''), '/');
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->siteUrl() . '/' . ltrim($path, '/');
    }
}
