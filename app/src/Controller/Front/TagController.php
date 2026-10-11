<?php

declare(strict_types=1);

namespace App\Controller\Front;

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

class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly PostRepository $postRepository,
        private readonly SettingsService $settingsService,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
        private readonly PermalinkService $permalinkService,
    ) {
    }

    #[Route('/etiketler', name: 'front_tags')]
    public function list(Request $request, PaginatorInterface $paginator): Response
    {
        $sort = (string) $this->settingsService->get('footer_tags_sort', 'popular');
        if (!in_array($sort, ['popular', 'recent', 'first'], true)) {
            $sort = 'popular';
        }

        $qb = match ($sort) {
            'recent' => $this->tagRepository->createListQueryBuilder()->orderBy('t.createdAt', 'DESC'),
            'first' => $this->tagRepository->createListQueryBuilder()->orderBy('t.name', 'ASC'),
            default => $this->tagRepository->createQueryBuilder('t')
                ->leftJoin('t.posts', 'p')
                ->addSelect('COUNT(p.id) AS HIDDEN postCount')
                ->groupBy('t.id')
                ->orderBy('postCount', 'DESC')
                ->addOrderBy('t.name', 'ASC'),
        };

        $page = $request->query->getInt('page', 1);

        return $this->render('front/tag/list.html.twig', [
            'pagination' => $paginator->paginate($qb, $page, 48),
            'seoMeta' => $this->seoMetaBuilderService->forArchive('Etiketler', null, $request->getPathInfo(), $page),
        ]);
    }

    #[Route('/etiket/{slug}', name: 'front_tag')]
    public function show(string $slug, Request $request, PaginatorInterface $paginator): Response
    {
        $tag = $this->tagRepository->findOneBy(['slug' => $slug]);
        if ($tag === null) {
            throw $this->createNotFoundException();
        }

        $qb = $this->postRepository->createPublishedQueryBuilder()
            ->innerJoin('p.tags', 't')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $slug);

        $page = $request->query->getInt('page', 1);
        $pagination = $paginator->paginate($qb, $page, 10);
        $this->postRepository->warmCategories($pagination->getItems());

        return $this->render('front/tag/show.html.twig', [
            'tag' => $tag,
            'pagination' => $pagination,
            'seoMeta' => $this->seoMetaBuilderService->forArchive(
                '#' . $tag->getName(),
                null,
                $this->permalinkService->buildTagPath($tag),
                $page,
            ),
        ]);
    }
}
