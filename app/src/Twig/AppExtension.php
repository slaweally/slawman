<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\Tag;
use App\Entity\TrackerLog;
use App\Entity\User;
use App\Enum\PostType;
use App\PostType\PostTypeConfig;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\MenuItemRepository;
use App\Repository\PostRepository;
use App\Repository\SidebarWidgetRepository;
use App\Repository\TagRepository;
use App\Service\PermalinkService;
use App\Service\PostMetaService;
use App\Service\SettingsService;
use App\Service\SiteOwnerService;
use App\Service\SluggerService;
use App\Service\TrackerLogService;
use App\Service\VisitorStatsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly MenuItemRepository $menuRepository,
        private readonly SidebarWidgetRepository $sidebarWidgetRepository,
        private readonly CommentRepository $commentRepository,
        private readonly VisitorStatsService $visitorStatsService,
        private readonly RequestStack $requestStack,
        private readonly SluggerService $sluggerService,
        private readonly PermalinkService $permalinkService,
        private readonly PostMetaService $postMetaService,
        private readonly SiteOwnerService $siteOwnerService,
        private readonly TrackerLogService $trackerLogService,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'upload_dir')]
        private readonly string $uploadDir,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('time_ago', [$this, 'timeAgo']),
            new TwigFilter('reading_time', [$this, 'readingTime']),
            new TwigFilter('truncate', [$this, 'truncate']),
            new TwigFilter('slugify', [$this, 'slugify']),
            new TwigFilter('tech_stack_list', [$this, 'techStackList']),
            new TwigFilter('compare_criteria_list', [$this, 'compareCriteriaList']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_setting', [$this, 'getSetting']),
            new TwigFunction('setting_enabled', [$this, 'settingEnabled']),
            new TwigFunction('lazy_attr', [$this, 'lazyAttr']),
            new TwigFunction('responsive_image', [$this, 'responsiveImage']),
            new TwigFunction('absolute_url', [$this, 'absoluteUrl']),
            new TwigFunction('get_related_posts', [$this, 'getRelatedPosts']),
            new TwigFunction('get_post_type_label', [$this, 'getPostTypeLabel']),
            new TwigFunction('get_post_type_color', [$this, 'getPostTypeColor']),
            new TwigFunction('howto_difficulty_label', [$this, 'howtoDifficultyLabel']),
            new TwigFunction('software_distribution_label', [$this, 'softwareDistributionLabel']),
            new TwigFunction('compare_winner_label', [$this, 'compareWinnerLabel']),
            new TwigFunction('get_sidebar_posts', [$this, 'getSidebarPosts']),
            new TwigFunction('get_sidebar_categories', [$this, 'getSidebarCategories']),
            new TwigFunction('get_popular_tags', [$this, 'getPopularTags']),
            new TwigFunction('get_footer_tags', [$this, 'getFooterTags']),
            new TwigFunction('get_recent_comments', [$this, 'getRecentComments']),
            new TwigFunction('get_site_public_stats', [$this, 'getSitePublicStats']),
            new TwigFunction('get_sidebar_widgets', [$this, 'getSidebarWidgets']),
            new TwigFunction('get_menu_items', [$this, 'getMenuItems']),
            new TwigFunction('turnstile_enabled', [$this, 'turnstileEnabled']),
            new TwigFunction('turnstile_site_key', [$this, 'turnstileSiteKey']),
            new TwigFunction('post_meta', [$this, 'postMeta']),
            new TwigFunction('get_pages', [$this, 'getPages']),
            new TwigFunction('profile_stack_tags', [$this, 'profileStackTags']),
            new TwigFunction('site_owner', [$this, 'getSiteOwner']),
            new TwigFunction('post_path', [$this, 'postPath']),
            new TwigFunction('post_url', [$this, 'postUrl']),
            new TwigFunction('page_path', [$this, 'pagePath']),
            new TwigFunction('category_path', [$this, 'categoryPath']),
            new TwigFunction('category_url', [$this, 'categoryUrl']),
            new TwigFunction('tag_path', [$this, 'tagPath']),
            new TwigFunction('tag_url', [$this, 'tagUrl']),
            new TwigFunction('page_url', [$this, 'pageUrl']),
            new TwigFunction('tracker_log_message', [$this, 'trackerLogMessage']),
        ];
    }

    public function timeAgo(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        $diff = (new \DateTime())->getTimestamp() - $date->getTimestamp();
        if ($diff < 60) {
            return 'az önce';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' dakika önce';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' saat önce';
        }

        return (int) floor($diff / 86400) . ' gün önce';
    }

    public function readingTime(?string $content): string
    {
        $words = str_word_count(strip_tags($content ?? ''));
        $minutes = max(1, (int) ceil($words / 200));

        return $minutes . ' dk okuma';
    }

    public function truncate(?string $text, int $length = 100, string $suffix = '...'): string
    {
        $text = $text ?? '';
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    public function slugify(string $text): string
    {
        return $this->sluggerService->slugify($text);
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        return $this->settingsService->get($key, $default);
    }

    public function settingEnabled(string $key): bool
    {
        return $this->settingsService->isTruthy($key);
    }

    public function lazyAttr(): string
    {
        return $this->settingsService->isTruthy('performance_lazyload') ? 'loading="lazy"' : '';
    }

    /**
     * MediaUploadService'in ürettiği _medium/_large WebP varyantlarını (varsa)
     * kullanarak <img srcset> değeri üretir. Varyant dosyaları diskte yoksa
     * (eski yüklemeler, WordPress import görselleri vb.) boş döner ve çağıran
     * taraf orijinal görseli tek başına kullanmaya devam eder.
     *
     * @return array{webp_srcset: string, sizes: string}
     */
    public function responsiveImage(?string $imageUrl): array
    {
        if ($imageUrl === null || $imageUrl === '' || !str_starts_with($imageUrl, '/uploads/')) {
            return ['webp_srcset' => '', 'sizes' => ''];
        }

        $filename = basename($imageUrl);
        $variants = ['medium' => 768, 'large' => 1280];
        $parts = [];

        foreach ($variants as $suffix => $width) {
            $webpFilename = preg_replace('/\.[^.]+$/', '_' . $suffix . '.webp', $filename);
            if (is_string($webpFilename) && is_file($this->uploadDir . '/' . $webpFilename)) {
                $parts[] = '/uploads/' . $webpFilename . ' ' . $width . 'w';
            }
        }

        if ($parts === []) {
            return ['webp_srcset' => '', 'sizes' => ''];
        }

        return [
            'webp_srcset' => implode(', ', $parts),
            'sizes' => '(max-width: 768px) 100vw, 768px',
        ];
    }

    /** Göreli bir path'i (örn. /uploads/x.jpg) site_url ayarına göre mutlak URL'e çevirir. */
    public function absoluteUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim((string) ($this->settingsService->get('site_url', '') ?? ''), '/');
        if ($base === '') {
            $base = rtrim($this->urlGenerator->generate('front_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        }

        return $base . '/' . ltrim($path, '/');
    }

    public function turnstileEnabled(): bool
    {
        return $this->settingsService->isTruthy('turnstile_enabled')
            && $this->settingsService->get('turnstile_site_key') !== '';
    }

    public function turnstileSiteKey(): ?string
    {
        $key = $this->settingsService->get('turnstile_site_key');

        return $key !== '' && $key !== null ? $key : null;
    }

    /** @return list<string> */
    public function profileStackTags(): array
    {
        $tags = $this->siteOwnerService->getStackTags();

        return $tags !== [] ? $tags : ['Linux', 'PHP', 'DevOps'];
    }

    public function getSiteOwner(): ?User
    {
        return $this->siteOwnerService->getOwner();
    }

    /** @return Post[] */
    public function getRelatedPosts(Post $post, int $limit = 4): array
    {
        return $this->postRepository->findRelated($post, $limit);
    }

    public function getPostTypeLabel(string $type): string
    {
        try {
            return PostTypeConfig::label(PostType::from($type));
        } catch (\ValueError) {
            return ucfirst($type);
        }
    }

    public function getPostTypeColor(string $type): string
    {
        return match ($type) {
            PostType::Note->value => 'bg-blue-100 text-blue-800',
            PostType::Article->value => 'bg-green-100 text-green-800',
            PostType::Project->value => 'bg-purple-100 text-purple-800',
            PostType::Software->value => 'bg-orange-100 text-orange-800',
            PostType::Snippet->value => 'bg-gray-100 text-gray-800',
            PostType::Page->value => 'bg-slate-100 text-slate-800',
            PostType::HowTo->value => 'bg-cyan-100 text-cyan-800',
            PostType::Troubleshoot->value => 'bg-red-100 text-red-800',
            PostType::Comparison->value => 'bg-indigo-100 text-indigo-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function howtoDifficultyLabel(?string $value): string
    {
        return PostTypeConfig::difficultyLabel($value);
    }

    public function softwareDistributionLabel(?string $value): string
    {
        return PostTypeConfig::distributionLabel($value);
    }

    public function compareWinnerLabel(?string $value): string
    {
        return PostTypeConfig::compareWinnerLabel($value);
    }

    /**
     * @return list<array{label: string, a: string, b: string, winner: string}>
     */
    public function compareCriteriaList(?string $raw): array
    {
        return PostTypeConfig::parseCompareCriteria($raw);
    }

    /** @return Post[] */
    public function getSidebarPosts(?int $limit = null): array
    {
        $count = $limit ?? $this->settingsService->getInt('sidebar_recent_count', 6);

        return $this->postRepository->findRecentPublished($count);
    }

    /** @return \App\Entity\Category[] */
    public function getSidebarCategories(): array
    {
        return $this->categoryRepository->findActiveOrdered();
    }

    /** @return \App\Entity\Tag[] */
    public function getPopularTags(?int $limit = null): array
    {
        $count = $limit ?? $this->settingsService->getInt('sidebar_tags_count', 12);

        return $this->tagRepository->findForFooter('popular', $count);
    }

    /** @return \App\Entity\Tag[] */
    public function getFooterTags(?string $sort = null, ?int $limit = null): array
    {
        $sort = $sort ?? (string) $this->settingsService->get('footer_tags_sort', 'popular');
        if (!in_array($sort, ['popular', 'recent', 'first'], true)) {
            $sort = 'popular';
        }
        $count = $limit ?? $this->settingsService->getInt('footer_tags_count', 24);

        return $this->tagRepository->findForFooter($sort, $count);
    }

    /** @return \App\Entity\Comment[] */
    public function getRecentComments(?int $limit = null): array
    {
        $count = $limit ?? $this->settingsService->getInt('sidebar_comments_count', 5);

        return $this->commentRepository->findRecentApproved($count);
    }

    /** @return array<string, int> */
    public function getSitePublicStats(): array
    {
        return $this->visitorStatsService->getPublicStats();
    }

    /** @return \App\Entity\SidebarWidget[] */
    public function getSidebarWidgets(): array
    {
        return $this->sidebarWidgetRepository->findActiveOrdered();
    }

    /** @return \App\Entity\MenuItem[] Sadece kök öğeler; alt menüler item.children üzerinden erişilir. */
    public function getMenuItems(string $location): array
    {
        return $this->menuRepository->findActiveTreeByLocation($location);
    }

    public function postMeta(Post $post, string $key, ?string $default = null): ?string
    {
        return $this->postMetaService->get($post, $key, $default);
    }

    /** @return list<string> */
    public function techStackList(?string $value): array
    {
        return $this->postMetaService->splitList($value);
    }

    /** @return Post[] */
    public function getPages(): array
    {
        return $this->postRepository->findPublishedByType(PostType::Page, 50);
    }

    public function postPath(Post $post): string
    {
        return $this->permalinkService->buildPostPath($post);
    }

    public function postUrl(Post $post): string
    {
        return $this->permalinkService->buildPostUrl($post, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function pagePath(Post $page): string
    {
        return $this->permalinkService->buildPagePath($page);
    }

    public function pageUrl(Post $page): string
    {
        return $this->permalinkService->buildPostUrl($page, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function categoryPath(Category $category): string
    {
        return $this->permalinkService->buildCategoryPath($category);
    }

    public function categoryUrl(Category $category): string
    {
        return $this->permalinkService->buildCategoryUrl($category);
    }

    public function tagPath(Tag $tag): string
    {
        return $this->permalinkService->buildTagPath($tag);
    }

    public function tagUrl(Tag $tag): string
    {
        return $this->permalinkService->buildTagUrl($tag);
    }

    public function trackerLogMessage(TrackerLog $log): string
    {
        return $this->trackerLogService->renderMessageWithLink($log);
    }
}
