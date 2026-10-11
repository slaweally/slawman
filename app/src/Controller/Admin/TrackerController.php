<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TrackerLog;
use App\Entity\TrackerMessage;
use App\Repository\TrackerLogRepository;
use App\Repository\TrackerMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracker', name: 'admin_tracker_')]
#[IsGranted('ROLE_ADMIN')]
class TrackerController extends AbstractController
{
    /** Düzenlenebilir (admin şablon yazabilir) olay türleri. */
    private const EDITABLE_EVENT_TYPES = [
        'visit' => 'Ziyaret',
        'comment' => 'Yorum',
        'login' => 'Giriş',
    ];

    /** Log listesinde rozet olarak gösterilecek tüm olay türleri (alert dahil, sabit formatlı). */
    private const ALL_EVENT_TYPES = self::EDITABLE_EVENT_TYPES + [
        'alert' => '🚨 Güvenlik Uyarısı',
    ];

    public function __construct(
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly TrackerMessageRepository $trackerMessageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $this->trackerLogRepository->createQueryBuilder('t')->orderBy('t.createdAt', 'DESC');

        return $this->render('admin/tracker/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 30),
            'messagesByType' => $this->trackerMessageRepository->findAllGroupedByType(),
            'editableEventTypes' => self::EDITABLE_EVENT_TYPES,
            'eventTypes' => self::ALL_EVENT_TYPES,
        ]);
    }

    #[Route('/messages/create', name: 'message_create', methods: ['POST'])]
    public function createMessage(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tracker_message', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_tracker_index');
        }

        $eventType = (string) $request->request->get('event_type', 'visit');
        $template = trim((string) $request->request->get('template', ''));

        if (!array_key_exists($eventType, self::EDITABLE_EVENT_TYPES) || $template === '') {
            $this->addFlash('error', 'Olay türü veya şablon metni geçersiz.');

            return $this->redirectToRoute('admin_tracker_index');
        }

        $message = new TrackerMessage();
        $message->setEventType($eventType);
        $message->setTemplate($template);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->addFlash('success', 'Mesaj şablonu eklendi.');

        return $this->redirectToRoute('admin_tracker_index');
    }

    #[Route('/messages/{id}/toggle', name: 'message_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleMessage(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('tracker_message_toggle_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_tracker_index');
        }

        $message = $this->trackerMessageRepository->find($id);
        if ($message instanceof TrackerMessage) {
            $message->setIsActive(!$message->isActive());
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_tracker_index');
    }

    #[Route('/messages/{id}/delete', name: 'message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteMessage(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('tracker_message_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_tracker_index');
        }

        $message = $this->trackerMessageRepository->find($id);
        if ($message instanceof TrackerMessage) {
            $this->entityManager->remove($message);
            $this->entityManager->flush();
            $this->addFlash('success', 'Mesaj şablonu silindi.');
        }

        return $this->redirectToRoute('admin_tracker_index');
    }

    #[Route('/logs/{id}/toggle-read', name: 'log_toggle_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleRead(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('tracker_log_toggle_read_' . $id, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $log = $this->trackerLogRepository->find($id);
        if (!$log instanceof TrackerLog) {
            return $this->json(['ok' => false, 'error' => 'not_found'], 404);
        }

        $log->setIsRead(!$log->isRead());
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'isRead' => $log->isRead()]);
    }

    #[Route('/logs/{id}', name: 'log_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteLog(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('tracker_log_delete_' . $id, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $log = $this->trackerLogRepository->find($id);
        if ($log instanceof TrackerLog) {
            $this->entityManager->remove($log);
            $this->entityManager->flush();
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/logs/clear-all', name: 'log_clear_all', methods: ['POST'])]
    public function clearAll(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('tracker_log_clear_all', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $mode = (string) $request->request->get('mode', 'delete');
        if ($mode === 'read') {
            $this->trackerLogRepository->markAllRead();
        } else {
            $this->trackerLogRepository->deleteAll();
        }

        return $this->json(['ok' => true, 'mode' => $mode]);
    }
}
