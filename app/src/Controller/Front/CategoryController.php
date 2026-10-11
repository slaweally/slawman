<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Service\PermalinkService;
use App\Service\SeoMetaBuilderService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly PostRepository $postRepository,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
        private readonly PermalinkService $permalinkService,
    ) {
    }

    #[Route('/kategori/{slug}', name: 'front_category')]
    public function show(string $slug, Request $request, PaginatorInterface $paginator): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
        if ($category === null) {
            throw $this->createNotFoundException();
        }

        $qb = $this->postRepository->createPublishedQueryBuilder()
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug);

        $page = $request->query->getInt('page', 1);
        $pagination = $paginator->paginate($qb, $page, 10);
        $this->postRepository->warmCategories($pagination->getItems());

        return $this->render('front/category/show.html.twig', [
            'category' => $category,
            'pagination' => $pagination,
            'seoMeta' => $this->seoMetaBuilderService->forArchive(
                $category->getName() ?? 'Kategori',
                $category->getDescription(),
                $this->permalinkService->buildCategoryPath($category),
                $page,
            ),
        ]);
    }
}
