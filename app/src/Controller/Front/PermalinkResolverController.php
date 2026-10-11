<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Service\PermalinkService;
use App\Service\SeoMetaBuilderService;
use App\Service\SettingsService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Resolves WordPress-style permalinks when structure is not "default".
 */
class PermalinkResolverController extends AbstractController
{
    public function __construct(
        private readonly PermalinkService $permalinkService,
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly SettingsService $settingsService,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
    ) {
    }

    #[Route('/{base}/{slug}', name: 'front_permalink_taxonomy', priority: -40)]
    public function taxonomy(string $base, string $slug, Request $request, PaginatorInterface $paginator): Response
    {
        if ($base === $this->permalinkService->getCategoryBase()) {
            $category = $this->categoryRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
            if ($category === null) {
                throw $this->createNotFoundException();
            }

            $qb = $this->postRepository->createPublishedQueryBuilder()
                ->innerJoin('p.categories', 'c')
                ->andWhere('c.slug = :slug')
                ->setParameter('slug', $slug);

            return $this->render('front/category/show.html.twig', [
                'category' => $category,
                'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 10),
                'seoMeta' => $this->seoMetaBuilderService->forArchive(
                    $category->getName() ?? 'Kategori',
                    $category->getDescription(),
                    $this->permalinkService->buildCategoryPath($category),
                ),
            ]);
        }

        if ($base === $this->permalinkService->getTagBase()) {
            $tag = $this->tagRepository->findOneBy(['slug' => $slug]);
            if ($tag === null) {
                throw $this->createNotFoundException();
            }

            $qb = $this->postRepository->createPublishedQueryBuilder()
                ->innerJoin('p.tags', 't')
                ->andWhere('t.slug = :slug')
                ->setParameter('slug', $slug);

            return $this->render('front/tag/show.html.twig', [
                'tag' => $tag,
                'pagination' => $paginator->paginate(
                    $qb,
                    $request->query->getInt('page', 1),
                    $this->settingsService->getInt('posts_per_page', 10)
                ),
                'seoMeta' => $this->seoMetaBuilderService->forArchive(
                    '#' . $tag->getName(),
                    null,
                    $this->permalinkService->buildTagPath($tag),
                ),
            ]);
        }

        return $this->resolve($base . '/' . $slug);
    }

    #[Route('/{permalinkPath}', name: 'front_permalink', requirements: ['permalinkPath' => '.+'], priority: -255)]
    public function resolve(string $permalinkPath): Response
    {
        $page = $this->permalinkService->resolvePage($permalinkPath);
        if ($page !== null) {
            return $this->forward(PostController::class . '::show', [
                'type' => $page->getType()->value,
                'slug' => $page->getSlug(),
            ]);
        }

        $post = $this->permalinkService->resolvePost($permalinkPath);
        if ($post !== null) {
            return $this->forward(PostController::class . '::show', [
                'type' => $post->getType()->value,
                'slug' => $post->getSlug(),
            ]);
        }

        throw $this->createNotFoundException();
    }
}
