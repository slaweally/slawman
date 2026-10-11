<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Enum\PostType;
use App\Repository\PostRepository;
use App\Service\SeoMetaBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
    ) {
    }

    #[Route('/sayfa/{slug}', name: 'front_page')]
    public function show(string $slug): Response
    {
        $normalizedSlug = $slug;
        if (str_ends_with($normalizedSlug, '.slw')) {
            $normalizedSlug = substr($normalizedSlug, 0, -4);
        }

        $page = $this->postRepository->findOnePublishedBySlugAndType($normalizedSlug, PostType::Page);
        if ($page === null) {
            throw $this->createNotFoundException();
        }

        if ($slug !== $normalizedSlug) {
            return new RedirectResponse(
                $this->generateUrl('front_page', ['slug' => $normalizedSlug]),
                Response::HTTP_MOVED_PERMANENTLY
            );
        }

        return $this->render('front/page/show.html.twig', [
            'page' => $page,
            'seoMeta' => $this->seoMetaBuilderService->forPost($page),
        ]);
    }
}
