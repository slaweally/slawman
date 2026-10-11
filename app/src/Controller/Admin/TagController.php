<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use App\Service\AuditLogService;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tags', name: 'admin_tag_')]
#[IsGranted('ROLE_ADMIN')]
class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerService $slugger,
        private readonly AuditLogService $auditLog,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        return $this->render('admin/tag/index.html.twig', [
            'pagination' => $paginator->paginate(
                $this->tagRepository->createListQueryBuilder(),
                $request->query->getInt('page', 1),
                $request->query->getInt('per_page', 50)
            ),
        ]);
    }

    /**
     * Tom Select'in `load` fonksiyonu için canlı arama — [{ value, text }] formatında döner.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if ($q === '') {
            return new JsonResponse([]);
        }

        $tags = $this->tagRepository->createQueryBuilder('t')
            ->andWhere('t.name LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $results = array_map(static fn (Tag $tag): array => [
            'value' => (string) $tag->getId(),
            'text' => (string) $tag->getName(),
        ], $tags);

        return new JsonResponse($results);
    }

    #[Route('/create-ajax', name: 'create_ajax', methods: ['POST'])]
    public function createAjax(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('tag_create', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Geçersiz token'], 400);
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '' || mb_strlen($name) > 100) {
            return new JsonResponse(['error' => 'Geçersiz etiket adı'], 400);
        }

        $existing = $this->tagRepository->findOneBy(['name' => $name]);
        if ($existing !== null) {
            return new JsonResponse([
                'id' => $existing->getId(),
                'name' => $existing->getName(),
                'slug' => $existing->getSlug(),
                'created' => false,
            ]);
        }

        $tag = new Tag();
        $tag->setName($name);
        $tag->setSlug($this->slugger->slugify($name));

        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->auditLog->log($this->getUser(), 'create', Tag::class, (int) $tag->getId());

        return new JsonResponse([
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'slug' => $tag->getSlug(),
            'created' => true,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new Tag(), true);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Tag $tag): Response
    {
        return $this->handleForm($request, $tag, false);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Tag $tag): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tag->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($tag);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', Tag::class, (int) $tag->getId());
        }

        return $this->redirectToRoute('admin_tag_index');
    }

    private function handleForm(Request $request, Tag $tag, bool $isNew): Response
    {
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$tag->getSlug()) {
                $tag->setSlug($this->slugger->slugify($tag->getName() ?? ''));
            }
            $this->entityManager->persist($tag);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), $isNew ? 'create' : 'update', Tag::class, (int) $tag->getId());

            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/form.html.twig', ['form' => $form, 'isNew' => $isNew]);
    }
}
