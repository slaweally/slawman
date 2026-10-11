<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Enum\PostType;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly CacheInterface $cache,
    )
    {
    }

    #[Route('/sitemap.xml', name: 'front_sitemap')]
    public function index(): Response
    {
        $payload = $this->cache->get('front_sitemap_payload', function (ItemInterface $item): array {
            $item->expiresAfter(900);

            return [
                // Google/Bing tek sitemap dosyasında en fazla 50.000 URL kabul ediyor.
                // İçerik bu sınıra yaklaşırsa sitemap index + parçalı sitemap'lere geçilmeli.
                'posts' => $this->postRepository->createPublishedQueryBuilder()->setMaxResults(50000)->getQuery()->getResult(),
                'pages' => $this->postRepository->createPublishedQueryBuilder(PostType::Page)->setMaxResults(5000)->getQuery()->getResult(),
                'categories' => $this->categoryRepository->findActiveOrdered(),
                'tags' => $this->tagRepository->findBy([], ['name' => 'ASC'], 1000),
            ];
        });

        $response = $this->render('front/sitemap/index.xml.twig', [
            'posts' => $payload['posts'],
            'pages' => $payload['pages'],
            'categories' => $payload['categories'],
            'tags' => $payload['tags'],
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
