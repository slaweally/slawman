<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use App\Form\MenuItemType;
use App\Repository\MenuItemRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/menus', name: 'admin_menu_')]
#[IsGranted('ROLE_ADMIN')]
class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuItemRepository $menuRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLog,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/menu/index.html.twig', [
            'headerItems' => $this->menuRepository->findRootItemsByLocation('header'),
            'footerItems' => $this->menuRepository->findRootItemsByLocation('footer'),
        ]);
    }

    /**
     * Nestable/SortableJS sürükle-bırak sonucu oluşan hiyerarşiyi kaydeder.
     * Beklenen payload: { location: "header", tree: [{id, children: [{id, children: [...]}]}] }
     * Her seviyede sortOrder, dizideki sıraya göre 0'dan başlayarak atanır.
     */
    #[Route('/save-order', name: 'save_order', methods: ['POST'])]
    public function saveMenuOrder(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('menu_save_order', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload) || !isset($payload['tree']) || !is_array($payload['tree'])) {
            return $this->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        $updated = $this->applyTree($payload['tree'], null);
        $this->entityManager->flush();
        $this->auditLog->log($this->getUser(), 'reorder', MenuItem::class, 0, [], ['updated' => $updated]);

        return $this->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * @param array<int, array{id: int, children?: array}> $nodes
     */
    private function applyTree(array $nodes, ?MenuItem $parent): int
    {
        $count = 0;
        foreach (array_values($nodes) as $index => $node) {
            $id = (int) ($node['id'] ?? 0);
            $item = $id > 0 ? $this->menuRepository->find($id) : null;
            if (!$item instanceof MenuItem) {
                continue;
            }

            $item->setParent($parent);
            $item->setSortOrder($index);
            $count++;

            if (!empty($node['children']) && is_array($node['children'])) {
                $count += $this->applyTree($node['children'], $item);
            }
        }

        return $count;
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new MenuItem(), true);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, MenuItem $menuItem): Response
    {
        return $this->handleForm($request, $menuItem, false);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, MenuItem $menuItem): Response
    {
        if ($this->isCsrfTokenValid('delete' . $menuItem->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($menuItem);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', MenuItem::class, (int) $menuItem->getId());
            $this->addFlash('success', 'Menü öğesi silindi.');
        }

        return $this->redirectToRoute('admin_menu_index');
    }

    private function handleForm(Request $request, MenuItem $item, bool $isNew): Response
    {
        $form = $this->createForm(MenuItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), $isNew ? 'create' : 'update', MenuItem::class, (int) $item->getId());
            $this->addFlash('success', $isNew ? 'Menü öğesi eklendi.' : 'Menü öğesi güncellendi.');

            return $this->redirectToRoute('admin_menu_index');
        }

        return $this->render('admin/menu/form.html.twig', ['form' => $form, 'isNew' => $isNew]);
    }
}
