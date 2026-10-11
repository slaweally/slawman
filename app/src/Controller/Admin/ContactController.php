<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Repository\ContactMessageRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages', name: 'admin_contact_')]
#[IsGranted('ROLE_ADMIN')]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLog,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/contact/index.html.twig', [
            'messages' => $this->contactMessageRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function markRead(Request $request, ContactMessage $message): Response
    {
        if ($this->isCsrfTokenValid('read' . $message->getId(), (string) $request->request->get('_token'))) {
            $message->setIsRead(true);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'update', ContactMessage::class, (int) $message->getId());
        }

        return $this->redirectToRoute('admin_contact_index');
    }
}
