<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\PostType\PostTypeConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SeoMetaBuilderService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly PostMetaService $postMetaService,
        private readonly PermalinkService $permalinkService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JsonLdBuilderService $jsonLdBuilderService,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
    ) {
    }

    /** @return array<string, mixed> */
    public function forPost(Post $post): array
    {
        $siteName = $this->siteTitle();
        $seo = $this->postMetaService->getSeoData($post);
        $title = trim((string) ($seo[PostTypeConfig::META_SEO_TITLE] ?: ($post->getTitle() ?? $siteName)));
        $description = trim((string) ($seo[PostTypeConfig::META_SEO_DESCRIPTION] ?: ($post->getExcerpt() ?? '')));
        $keywords = trim((string) ($seo[PostTypeConfig::META_SEO_KEYWORDS] ?? ''));
        $canonical = $this->absolute($this->permalinkService->buildPostPath($post));
        $ogImage = $this->absoluteUrl((string) ($seo[PostTypeConfig::META_OG_IMAGE] ?: ($post->getFeaturedImage() ?? '')));
        $robots = $post->getStatus()->value === 'published'
            ? $this->defaultRobots()
            : 'noindex,nofollow';

        $meta = $this->buildMeta(
            pageTitle: $title,
            description: $description,
            canonical: $canonical,
            ogType: $post->getType()->value === 'article' ? 'article' : 'website',
            ogImage: $ogImage,
            robots: $robots,
            keywords: $keywords,
            appendSuffix: true,
        );

        $meta['article_published_time'] = $post->getPublishedAt()?->format(\DateTimeInterface::ATOM) ?? '';
        $meta['article_modified_time'] = ($post->getUpdatedAt() ?? $post->getPublishedAt())?->format(\DateTimeInterface::ATOM) ?? '';
        $meta['article_author'] = $post->getAuthor()?->getDisplayName() ?? '';
        $meta['json_ld'] = json_encode($this->jsonLdBuilderService->forPost($post), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        return $meta;
    }

    /** @return array<string, mixed> */
    public function forHome(): array
    {
        $title = trim((string) ($this->settingsService->get('seo_home_title', '') ?: $this->siteTitle()));
        $description = trim((string) ($this->settingsService->get('seo_home_description', '') ?: $this->siteDescription()));

        $meta = $this->buildMeta(
            pageTitle: $title,
            description: $description,
            canonical: $this->homeCanonical(),
            ogType: 'website',
            ogImage: $this->absoluteUrl((string) $this->settingsService->get('seo_home_og_image', '')),
            robots: $this->defaultRobots(),
            keywords: trim((string) $this->settingsService->get('seo_home_keywords', '')),
            appendSuffix: false,
        );

        $meta['json_ld'] = json_encode($this->jsonLdBuilderService->forHome(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        return $meta;
    }

    /**
     * Liste/arşiv sayfaları (kategori, etiket, tip bazlı listeler) için.
     * canonicalPath verilmezse (eski çağrı biçimi) anasayfaya düşer — bu yüzden
     * tüm liste controller'ları artık kendi path'ini geçmelidir, aksi halde
     * Google Search Console'da bu sayfalar anasayfayla karışır.
     */
    public function forArchive(string $pageTitle, ?string $description = null, ?string $canonicalPath = null, int $page = 1): array
    {
        $baseCanonical = $canonicalPath !== null ? $this->absolute($canonicalPath) : $this->homeCanonical();
        $canonical = $baseCanonical;
        if ($page > 1) {
            $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $page;
        }

        $meta = $this->buildMeta(
            pageTitle: $page > 1 ? trim($pageTitle) . ' — Sayfa ' . $page : trim($pageTitle),
            description: trim($description ?? $this->siteDescription()),
            canonical: $canonical,
            ogType: 'website',
            ogImage: '',
            // 2. ve sonraki sayfalar aynı içeriğin tekrarıdır — index'lenmesin ama linkleri takip edilsin.
            robots: $page > 1 ? 'noindex,follow' : $this->defaultRobots(),
            keywords: '',
            appendSuffix: true,
        );

        $meta['json_ld'] = json_encode(
            $this->jsonLdBuilderService->forArchive(trim($pageTitle), $canonicalPath !== null ? $baseCanonical : null),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );

        return $meta;
    }

    /** @return array<string, string> */
    public function defaults(): array
    {
        return $this->forHome();
    }

    /**
     * @return array<string, string>
     */
    private function buildMeta(
        string $pageTitle,
        string $description,
        string $canonical,
        string $ogType,
        string $ogImage,
        string $robots,
        string $keywords,
        bool $appendSuffix,
    ): array {
        $siteName = $this->siteTitle();
        $documentTitle = $appendSuffix
            ? $this->buildDocumentTitle($pageTitle)
            : ($pageTitle !== '' ? $pageTitle : $siteName);
        [$ogImageWidth, $ogImageHeight] = $this->imageDimensions($ogImage);

        return [
            'title' => $pageTitle !== '' ? $pageTitle : $siteName,
            'document_title' => $documentTitle,
            'description' => $description,
            'canonical' => $canonical,
            'og_type' => $ogType,
            'og_title' => $pageTitle !== '' ? $pageTitle : $siteName,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_image_width' => $ogImageWidth,
            'og_image_height' => $ogImageHeight,
            'twitter_title' => $pageTitle !== '' ? $pageTitle : $siteName,
            'twitter_description' => $description,
            'twitter_card' => $ogImage !== '' ? 'summary_large_image' : 'summary',
            'robots' => $robots,
            'keywords' => $keywords,
            'site_name' => $siteName,
        ];
    }

    /**
     * og:image için gerçek piksel boyutunu döner (Facebook/LinkedIn crawler'ları
     * bunu önerir). Sadece yerel /uploads/ dosyaları için diskten okunur — harici
     * URL'lerde (uzak host) network isteği yapmamak için boş döner.
     *
     * @return array{0: string, 1: string}
     */
    private function imageDimensions(string $ogImageUrl): array
    {
        if ($ogImageUrl === '' || !str_contains($ogImageUrl, '/uploads/')) {
            return ['', ''];
        }

        $filename = basename($ogImageUrl);
        $path = $this->uploadDir . '/' . $filename;
        if (!is_file($path)) {
            return ['', ''];
        }

        $size = @getimagesize($path);
        if ($size === false) {
            return ['', ''];
        }

        return [(string) $size[0], (string) $size[1]];
    }

    private function buildDocumentTitle(string $pageTitle): string
    {
        $pageTitle = trim($pageTitle);
        if ($pageTitle === '') {
            return $this->siteTitle();
        }

        $suffix = trim((string) ($this->settingsService->get('seo_meta_title_suffix', '') ?? ''));
        if ($suffix === '') {
            $suffix = ' — ' . $this->siteTitle();
        } elseif (!preg_match('/^[\s|—\-–]/u', $suffix)) {
            $suffix = ' ' . $suffix;
        }

        return $pageTitle . $suffix;
    }

    private function siteTitle(): string
    {
        return (string) ($this->settingsService->get('site_title', 'Slaweally') ?? 'Slaweally');
    }

    private function siteDescription(): string
    {
        return (string) ($this->settingsService->get('site_description', '') ?? '');
    }

    private function defaultRobots(): string
    {
        return (string) ($this->settingsService->get('seo_default_robots', 'index,follow') ?? 'index,follow');
    }

    private function homeCanonical(): string
    {
        $siteUrl = trim((string) ($this->settingsService->get('site_url', '') ?? ''));
        if ($siteUrl !== '') {
            return rtrim($siteUrl, '/');
        }

        return $this->urlGenerator->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function absolute(string $path): string
    {
        $base = rtrim((string) ($this->settingsService->get('site_url', '') ?? ''), '/');
        if ($base !== '') {
            return $base . $path;
        }

        return $this->urlGenerator->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . ltrim($path, '/');
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

        return $this->absolute($path);
    }
}
