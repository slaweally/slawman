<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SidebarWidget;
use App\Form\SidebarWidgetType;
use App\Repository\SidebarWidgetRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/widgets', name: 'admin_widget_')]
#[IsGranted('ROLE_ADMIN')]
class SidebarWidgetController extends AbstractController
{
    public function __construct(
        private readonly SidebarWidgetRepository $widgetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLog,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/widget/index.html.twig', [
            'widgets' => $this->widgetRepository->findBy([], ['sortOrder' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new SidebarWidget(), true);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, SidebarWidget $widget): Response
    {
        return $this->handleForm($request, $widget, false);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, SidebarWidget $widget): Response
    {
        if ($this->isCsrfTokenValid('delete' . $widget->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($widget);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'delete', SidebarWidget::class, (int) $widget->getId());
            $this->addFlash('success', 'Widget silindi.');
        }

        return $this->redirectToRoute('admin_widget_index');
    }

    private function handleForm(Request $request, SidebarWidget $widget, bool $isNew): Response
    {
        $form = $this->createForm(SidebarWidgetType::class, $widget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($widget);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), $isNew ? 'create' : 'update', SidebarWidget::class, (int) $widget->getId());
            $this->addFlash('success', $isNew ? 'Widget eklendi.' : 'Widget güncellendi.');

            return $this->redirectToRoute('admin_widget_index');
        }

        return $this->render('admin/widget/form.html.twig', ['form' => $form, 'isNew' => $isNew]);
    }
}
