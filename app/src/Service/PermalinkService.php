<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Enum\PostType;
use App\Repository\PostRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * WordPress-style permalink builder and resolver.
 */
class PermalinkService
{
    public const STRUCTURE_DEFAULT = 'default';
    public const STRUCTURE_POSTNAME = 'postname';
    public const STRUCTURE_YEAR_MONTH = 'year_month';
    public const STRUCTURE_YEAR_MONTH_DAY = 'year_month_day';
    public const STRUCTURE_CUSTOM = 'custom';

    /** @var list<string> */
    private const RESERVED_FIRST_SEGMENTS = [
        'blog',
        'notes',
        'projects',
        'software',
        'snippets',
        'howto',
        'troubleshoot',
        'compare',
        'ara',
        'iletisim',
        'etiketler',
        'comment',
        'uploads',
    ];

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly PostRepository $postRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getStructure(): string
    {
        $structure = (string) $this->settingsService->get('permalink_structure', self::STRUCTURE_DEFAULT);

        return in_array($structure, [
            self::STRUCTURE_DEFAULT,
            self::STRUCTURE_POSTNAME,
            self::STRUCTURE_YEAR_MONTH,
            self::STRUCTURE_YEAR_MONTH_DAY,
            self::STRUCTURE_CUSTOM,
        ], true) ? $structure : self::STRUCTURE_DEFAULT;
    }

    public function getCustomStructure(): string
    {
        $custom = trim((string) $this->settingsService->get('permalink_custom_structure', '/%postname%/'));

        return $custom !== '' ? $custom : '/%postname%/';
    }

    public function getSuffix(): string
    {
        return (string) $this->settingsService->get('permalink_suffix', '');
    }

    public function usesTrailingSlash(): bool
    {
        return $this->settingsService->isTruthy('permalink_trailing_slash');
    }

    public function getCategoryBase(): string
    {
        $base = trim((string) $this->settingsService->get('permalink_category_base', 'kategori'), '/');

        return $base !== '' ? $base : 'kategori';
    }

    public function getTagBase(): string
    {
        $base = trim((string) $this->settingsService->get('permalink_tag_base', 'etiket'), '/');

        return $base !== '' ? $base : 'etiket';
    }

    public function getPageBase(): string
    {
        return trim((string) $this->settingsService->get('permalink_page_base', 'sayfa'), '/');
    }

    public function buildPostPath(Post $post): string
    {
        if ($post->getType() === PostType::Page) {
            return $this->buildPagePath($post);
        }

        $structure = $this->getStructure();

        if ($structure === self::STRUCTURE_DEFAULT) {
            return $this->normalizePath('/' . $post->getType()->value . '/' . $post->getSlug());
        }

        if ($structure === self::STRUCTURE_POSTNAME) {
            return $this->normalizePath('/' . $post->getSlug() . $this->getSuffix());
        }

        if ($structure === self::STRUCTURE_YEAR_MONTH) {
            $date = $post->getPublishedAt() ?? $post->getCreatedAt() ?? new \DateTimeImmutable();

            return $this->normalizePath(sprintf(
                '/%s/%s/%s%s',
                $date->format('Y'),
                $date->format('m'),
                $post->getSlug(),
                $this->getSuffix()
            ));
        }

        if ($structure === self::STRUCTURE_YEAR_MONTH_DAY) {
            $date = $post->getPublishedAt() ?? $post->getCreatedAt() ?? new \DateTimeImmutable();

            return $this->normalizePath(sprintf(
                '/%s/%s/%s/%s%s',
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
                $post->getSlug(),
                $this->getSuffix()
            ));
        }

        return $this->normalizePath($this->expandTokens($this->getCustomStructure(), $post));
    }

    public function buildPagePath(Post $post): string
    {
        $pageBase = $this->getPageBase();
        $slug = (string) $post->getSlug();
        $suffix = $this->getSuffix();

        if ($pageBase === '') {
            $structure = $this->getStructure();
            if ($structure === self::STRUCTURE_DEFAULT) {
                return $this->normalizePath('/page/' . $slug);
            }

            if ($structure === self::STRUCTURE_POSTNAME) {
                return $this->normalizePath('/' . $slug . $suffix);
            }

            return $this->normalizePath($this->expandTokens($this->getCustomStructure(), $post));
        }

        return $this->normalizePath('/' . $pageBase . '/' . $slug . $suffix);
    }

    public function buildCategoryPath(Category $category): string
    {
        return $this->normalizePath('/' . $this->getCategoryBase() . '/' . $category->getSlug());
    }

    public function buildTagPath(Tag $tag): string
    {
        return $this->normalizePath('/' . $this->getTagBase() . '/' . $tag->getSlug());
    }

    public function buildPostUrl(Post $post, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $path = $this->buildPostPath($post);
        if ($referenceType === UrlGeneratorInterface::ABSOLUTE_PATH) {
            return $path;
        }

        return $this->absoluteUrl($path);
    }

    public function buildCategoryUrl(Category $category): string
    {
        return $this->absoluteUrl($this->buildCategoryPath($category));
    }

    public function buildTagUrl(Tag $tag): string
    {
        return $this->absoluteUrl($this->buildTagPath($tag));
    }

    public function resolvePost(string $path): ?Post
    {
        $path = $this->normalizeIncomingPath($path);
        if ($path === '' || $this->isReservedPath($path)) {
            return null;
        }

        $structure = $this->getStructure();
        if ($structure === self::STRUCTURE_DEFAULT) {
            return null;
        }

        $slug = $this->extractSlugFromPath($path, $structure);
        if ($slug === null || $slug === '') {
            return null;
        }

        $post = $this->postRepository->findOnePublishedBySlug($slug);
        if ($post === null) {
            return null;
        }

        if (!$this->pathMatchesPost($path, $post)) {
            return null;
        }

        return $post;
    }

    public function resolvePage(string $path): ?Post
    {
        $path = $this->normalizeIncomingPath($path);
        if ($path === '') {
            return null;
        }

        $pageBase = $this->getPageBase();
        $suffix = $this->getSuffix();

        if ($pageBase !== '') {
            $prefix = $pageBase . '/';
            if (!str_starts_with($path, $prefix)) {
                return null;
            }
            $slug = substr($path, strlen($prefix));
            $slug = $this->stripSuffix($slug);

            return $this->postRepository->findOnePublishedBySlugAndType($slug, PostType::Page);
        }

        $post = $this->resolvePost($path);
        if ($post !== null && $post->getType() === PostType::Page) {
            return $post;
        }

        if ($this->getStructure() === self::STRUCTURE_DEFAULT) {
            return null;
        }

        $slug = $this->extractSlugFromPath($path, $this->getStructure());
        if ($slug === null) {
            return null;
        }

        return $this->postRepository->findOnePublishedBySlugAndType($slug, PostType::Page);
    }

    public function structureOptions(): array
    {
        return [
            self::STRUCTURE_DEFAULT => 'Varsayılan (/{tip}/{slug})',
            self::STRUCTURE_POSTNAME => 'Yazı adı (/{slug}/)',
            self::STRUCTURE_YEAR_MONTH => 'Yıl / Ay / Yazı adı',
            self::STRUCTURE_YEAR_MONTH_DAY => 'Yıl / Ay / Gün / Yazı adı',
            self::STRUCTURE_CUSTOM => 'Özel yapı',
        ];
    }

    public function samplePath(Post $post): string
    {
        return $this->buildPostPath($post);
    }

    private function expandTokens(string $pattern, Post $post): string
    {
        $date = $post->getPublishedAt() ?? $post->getCreatedAt() ?? new \DateTimeImmutable();
        $category = $post->getCategories()->first() ?: null;
        $categorySlug = $category instanceof Category ? (string) $category->getSlug() : 'uncategorized';

        $replacements = [
            '%postname%' => (string) $post->getSlug(),
            '%year%' => $date->format('Y'),
            '%monthnum%' => $date->format('m'),
            '%day%' => $date->format('d'),
            '%category%' => $categorySlug,
        ];

        $path = str_replace(array_keys($replacements), array_values($replacements), $pattern);
        $suffix = $this->getSuffix();
        if ($suffix !== '' && !str_ends_with($path, $suffix)) {
            $path = rtrim($path, '/') . $suffix;
        }

        return $path;
    }

    private function extractSlugFromPath(string $path, string $structure): ?string
    {
        $suffix = $this->getSuffix();
        $barePath = $this->stripSuffix($path);

        if ($structure === self::STRUCTURE_POSTNAME) {
            return str_contains($barePath, '/') ? null : $barePath;
        }

        if ($structure === self::STRUCTURE_YEAR_MONTH) {
            if (!preg_match('#^(\d{4})/(\d{2})/([^/]+)$#', $barePath, $matches)) {
                return null;
            }

            return $matches[3];
        }

        if ($structure === self::STRUCTURE_YEAR_MONTH_DAY) {
            if (!preg_match('#^(\d{4})/(\d{2})/(\d{2})/([^/]+)$#', $barePath, $matches)) {
                return null;
            }

            return $matches[4];
        }

        if ($structure === self::STRUCTURE_CUSTOM) {
            $regex = $this->buildCustomRegex($this->getCustomStructure());
            if ($regex === null || !preg_match($regex, '/' . $barePath . '/', $matches)) {
                return null;
            }

            return $matches['postname'] ?? null;
        }

        return null;
    }

    private function pathMatchesPost(string $path, Post $post): bool
    {
        $expected = ltrim($this->buildPostPath($post), '/');
        $actual = ltrim($path, '/');

        if ($this->usesTrailingSlash()) {
            return rtrim($actual, '/') === rtrim($expected, '/');
        }

        return $actual === $expected;
    }

    private function buildCustomRegex(string $pattern): ?string
    {
        $pattern = '/' . trim($pattern, '/') . '/';
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(
            ['%postname%', '%year%', '%monthnum%', '%day%', '%category%'],
            ['(?P<postname>[^/]+)', '(?P<year>\d{4})', '(?P<monthnum>\d{2})', '(?P<day>\d{2})', '(?P<category>[^/]+)'],
            $regex
        );
        $suffix = preg_quote($this->getSuffix(), '#');
        if ($suffix !== '') {
            $regex = rtrim($regex, '/') . $suffix . '/';
        }

        return '#^' . $regex . '$#';
    }

    private function stripSuffix(string $segment): string
    {
        $suffix = $this->getSuffix();
        if ($suffix !== '' && str_ends_with($segment, $suffix)) {
            return substr($segment, 0, -strlen($suffix));
        }

        return $segment;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }

        if ($this->usesTrailingSlash()) {
            return rtrim($path, '/') . '/';
        }

        return rtrim($path, '/');
    }

    private function normalizeIncomingPath(string $path): string
    {
        return trim($path, '/');
    }

    private function isReservedPath(string $path): bool
    {
        $first = explode('/', $path, 2)[0];

        return in_array($first, self::RESERVED_FIRST_SEGMENTS, true)
            || str_ends_with($path, '.xml')
            || str_ends_with($path, '.txt');
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) ($this->settingsService->get('site_url', '') ?? ''), '/');
        if ($base !== '') {
            return $base . $path;
        }

        return $this->urlGenerator->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . ltrim($path, '/');
    }
}
