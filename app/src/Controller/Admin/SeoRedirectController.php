<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SeoRedirectRepository;
use App\Service\SeoRedirectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/seo-redirects', name: 'admin_seo_redirect_')]
#[IsGranted('ROLE_ADMIN')]
class SeoRedirectController extends AbstractController
{
    public function __construct(
        private readonly SeoRedirectRepository $seoRedirectRepository,
        private readonly SeoRedirectService $seoRedirectService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/seo_redirect/index.html.twig', [
            'redirects' => $this->seoRedirectRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('seo_redirect', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_seo_redirect_index');
        }

        $this->seoRedirectService->upsert(
            (string) $request->request->get('source_path', ''),
            (string) $request->request->get('target_path', ''),
            (int) $request->request->get('status_code', 301)
        );

        $this->addFlash('success', 'SEO redirect kaydedildi.');

        return $this->redirectToRoute('admin_seo_redirect_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('seo_redirect_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_seo_redirect_index');
        }

        $redirect = $this->seoRedirectRepository->find($id);
        if ($redirect !== null) {
            $this->entityManager->remove($redirect);
            $this->entityManager->flush();
            $this->addFlash('success', 'Redirect silindi.');
        }

        return $this->redirectToRoute('admin_seo_redirect_index');
    }
}
