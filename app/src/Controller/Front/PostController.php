<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Post;
use App\Enum\PostType;
use App\Form\CommentFormType;
use App\Repository\PostRepository;
use App\Service\CommentService;
use App\Service\SeoMetaBuilderService;
use App\Service\SettingsService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentService $commentService,
        private readonly SettingsService $settingsService,
        private readonly SeoMetaBuilderService $seoMetaBuilderService,
    ) {
    }

    #[Route('/blog', name: 'front_blog')]
    public function blog(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Article, 'front/post/list.html.twig', 'Makaleler');
    }

    #[Route('/notes', name: 'front_notes')]
    public function notes(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Note, 'front/post/notes.html.twig', 'Notlar');
    }

    #[Route('/projects', name: 'front_projects')]
    public function projects(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Project, 'front/post/projects.html.twig', 'Projeler');
    }

    #[Route('/software', name: 'front_software')]
    public function software(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Software, 'front/post/software.html.twig', 'Yazılımlar');
    }

    #[Route('/snippets', name: 'front_snippets')]
    public function snippets(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Snippet, 'front/post/snippets.html.twig', 'Snippetler');
    }

    #[Route('/howto', name: 'front_howto')]
    public function howto(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::HowTo, 'front/post/howto.html.twig', 'Nasıl Yapılır');
    }

    #[Route('/troubleshoot', name: 'front_troubleshoot')]
    public function troubleshoot(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Troubleshoot, 'front/post/troubleshoot.html.twig', 'Hata / Çözüm');
    }

    #[Route('/compare', name: 'front_compare')]
    public function compare(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->listByType($request, $paginator, PostType::Comparison, 'front/post/compare.html.twig', 'Karşılaştırmalar');
    }

    #[Route('/{type}/{slug}', name: 'front_post_show', requirements: ['type' => 'note|article|project|software|snippet|page|howto|troubleshoot|comparison'])]
    public function show(string $type, string $slug): Response
    {
        $postType = PostType::from($type);
        $post = $this->postRepository->findOnePublishedBySlugAndType($slug, $postType);

        if ($post === null) {
            throw $this->createNotFoundException();
        }

        $this->postRepository->incrementViewCount($post);

        return $this->render('front/post/show.html.twig', [
            'post' => $post,
            'comments' => $this->commentService->getApprovedForPost($post),
            'commentsEnabled' => $this->commentService->isEnabled(),
            'seoMeta' => $this->seoMetaBuilderService->forPost($post),
            'commentForm' => $this->commentService->isEnabled()
                ? $this->createForm(CommentFormType::class)->createView()
                : null,
        ]);
    }

    private function listByType(Request $request, PaginatorInterface $paginator, PostType $type, string $template, string $title): Response
    {
        $qb = $this->postRepository->createPublishedQueryBuilder($type);
        $perPage = $type === PostType::Note
            ? $this->settingsService->getInt('notes_per_page', 20)
            : $this->settingsService->getInt('posts_per_page', 10);

        $page = $request->query->getInt('page', 1);
        $pagination = $paginator->paginate($qb, $page, $perPage);
        $this->postRepository->warmCategories($pagination->getItems());

        return $this->render($template, [
            'title' => $title,
            'type' => $type,
            'pagination' => $pagination,
            'seoMeta' => $this->seoMetaBuilderService->forArchive($title, null, $request->getPathInfo(), $page),
        ]);
    }
}
