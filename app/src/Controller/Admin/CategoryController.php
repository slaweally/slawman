<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\AuditLogService;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/categories', name: 'admin_category_')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerService $slugger,
        private readonly AuditLogService $auditLog,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $this->categoryRepository->findBy([], ['sortOrder' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new Category(), true);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Category $category): Response
    {
        return $this->handleForm($request, $category, false);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($category);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', Category::class, (int) $category->getId());
        }

        return $this->redirectToRoute('admin_category_index');
    }

    private function handleForm(Request $request, Category $category, bool $isNew): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$category->getSlug()) {
                $category->setSlug($this->slugger->slugify($category->getName() ?? ''));
            }
            $this->entityManager->persist($category);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), $isNew ? 'create' : 'update', Category::class, (int) $category->getId());

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/form.html.twig', ['form' => $form, 'isNew' => $isNew]);
    }
}
