<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Enum\PostType;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\TrackerLogRepository;
use App\Service\SeoMetaBuilderService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly SettingsService $settingsService,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
    ) {
    }

    #[Route('/', name: 'front_home')]
    public function index(): Response
    {
        $trackerEnabled = $this->settingsService->isTruthy('tracker_enabled');
        $trackerPageSize = $this->settingsService->getInt('tracker_page_size', 20);
        $trackerLogs = $trackerEnabled ? $this->trackerLogRepository->findPage($trackerPageSize, 0) : [];

        $featured = $this->postRepository->findFeatured($this->settingsService->getInt('home_featured_count', 5));
        $articles = $this->postRepository->findPublishedByType(PostType::Article, $this->settingsService->getInt('home_articles_count', 5));
        $notes = $this->postRepository->findPublishedByType(PostType::Note, $this->settingsService->getInt('home_notes_count', 10));
        $recent = $this->postRepository->findRecentPublished($this->settingsService->getInt('home_recent_count', 6), PostType::Note);
        $projects = $this->postRepository->findPublishedByType(PostType::Project, $this->settingsService->getInt('home_projects_count', 3));
        $software = $this->postRepository->findPublishedByType(PostType::Software, $this->settingsService->getInt('home_software_count', 3));
        $howto = $this->postRepository->findPublishedByType(PostType::HowTo, $this->settingsService->getInt('home_howto_count', 4));
        $troubleshoot = $this->postRepository->findPublishedByType(PostType::Troubleshoot, $this->settingsService->getInt('home_troubleshoot_count', 4));
        $comparisons = $this->postRepository->findPublishedByType(PostType::Comparison, $this->settingsService->getInt('home_comparison_count', 4));

        $this->postRepository->warmCategories([
            ...$featured, ...$articles, ...$notes, ...$recent,
            ...$projects, ...$software, ...$howto, ...$troubleshoot, ...$comparisons,
        ]);

        return $this->render('front/home/index.html.twig', [
            'trackerEnabled' => $trackerEnabled,
            'trackerLogs' => $trackerLogs,
            'trackerHasMore' => $trackerEnabled && $this->trackerLogRepository->countAll() > count($trackerLogs),
            'featured' => $featured,
            'articles' => $articles,
            'notes' => $notes,
            'recent' => $recent,
            'projects' => $projects,
            'software' => $software,
            'howto' => $howto,
            'troubleshoot' => $troubleshoot,
            'comparisons' => $comparisons,
            'latestPost' => $this->postRepository->findLatestPublished(),
            'stats' => [
                'articles' => $this->postRepository->countPublishedByType(PostType::Article),
                'notes' => $this->postRepository->countPublishedByType(PostType::Note),
                'projects' => $this->postRepository->countPublishedByType(PostType::Project),
                'software' => $this->postRepository->countPublishedByType(PostType::Software),
                'snippets' => $this->postRepository->countPublishedByType(PostType::Snippet),
                'howto' => $this->postRepository->countPublishedByType(PostType::HowTo),
                'troubleshoot' => $this->postRepository->countPublishedByType(PostType::Troubleshoot),
                'comparisons' => $this->postRepository->countPublishedByType(PostType::Comparison),
            ],
            'categories' => $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']),
            'tags' => $this->tagRepository->findBy([], ['name' => 'ASC'], 20),
            'seoMeta' => $this->seoMetaBuilderService->forHome(),
        ]);
    }
}
