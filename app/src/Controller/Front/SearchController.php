<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PostRepository;
use App\Service\SeoMetaBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
    ) {
    }

    #[Route('/ara', name: 'front_search')]
    public function search(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $results = $q !== '' ? $this->postRepository->search($q) : [];

        $title = $q !== '' ? 'Arama: ' . $q : 'Arama';

        return $this->render('front/search/index.html.twig', [
            'q' => $q,
            'results' => $results,
            'seoMeta' => $this->seoMetaBuilderService->forArchive($title),
        ]);
    }
}
