<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Enum\PostType;
use App\Repository\PostRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * WordPress benzeri statik sayfa yönetimi (Hakkımızda, Gizlilik vb.)
 */
#[Route('/pages', name: 'admin_page_')]
#[IsGranted('ROLE_ADMIN')]
class PageController extends AbstractController
{
    public function __construct(private readonly PostRepository $postRepository)
    {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $this->postRepository->createAdminListQueryBuilder()
            ->andWhere('p.type = :type')
            ->setParameter('type', PostType::Page)
            ->orderBy('p.title', 'ASC');

        return $this->render('admin/page/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 30),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(): Response
    {
        return $this->redirectToRoute('admin_post_new', ['type' => 'page']);
    }
}
