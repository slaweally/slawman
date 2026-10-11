<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Enum\CommentStatus;
use App\Repository\CommentRepository;
use App\Service\AuditLogService;
use App\Service\CommentService;
use App\Service\SettingsService;
use App\Service\SiteOwnerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comments', name: 'admin_comment_')]
#[IsGranted('ROLE_ADMIN')]
class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLog,
        private readonly CommentService $commentService,
        private readonly SettingsService $settingsService,
        private readonly SiteOwnerService $siteOwnerService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $statusFilter = null;
        if ($status = $request->query->get('status')) {
            try {
                $statusFilter = CommentStatus::from($status);
            } catch (\ValueError) {
            }
        }

        $qb = $this->commentRepository->createAdminListQueryBuilder($statusFilter);

        return $this->render('admin/comment/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 30),
            'currentStatus' => $request->query->get('status', ''),
            'pendingCount' => $this->commentRepository->countPending(),
            'trashCount' => $this->commentRepository->countTrash(),
        ]);
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(Request $request, Comment $comment): Response
    {
        if ($this->isCsrfTokenValid('comment' . $comment->getId(), (string) $request->request->get('_token'))) {
            $comment->setStatus(CommentStatus::Approved);
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'update', Comment::class, (int) $comment->getId());
            $this->addFlash('success', 'Yorum onaylandı.');
        }

        return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
    }

    #[Route('/{id}/reply', name: 'reply', methods: ['POST'])]
    public function reply(Request $request, Comment $comment): Response
    {
        if (!$this->isCsrfTokenValid('comment' . $comment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '' || mb_strlen($content) > 5000) {
            $this->addFlash('error', 'Yanıt 1-5000 karakter olmalı.');

            return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
        }

        $post = $comment->getPost();
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        $parent = $comment->getParent() ?? $comment;
        $authorName = $this->siteOwnerService->getDisplayName('Admin');
        $authorEmail = $this->settingsService->get('site_email', 'admin@localhost') ?? 'admin@localhost';

        $this->commentService->createAdminReply(
            $post,
            $authorName,
            $authorEmail,
            $content,
            $parent,
            $request->getClientIp(),
            $request->headers->get('User-Agent')
        );

        if ($comment->getStatus() === CommentStatus::Pending) {
            $comment->setStatus(CommentStatus::Approved);
            $this->entityManager->flush();
        }

        $this->auditLog->log($this->getUser(), 'create', Comment::class, (int) $comment->getId());
        $this->addFlash('success', 'Yanıt yayınlandı.');

        return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
    }

    #[Route('/{id}/status/{status}', name: 'status', methods: ['POST'])]
    public function status(Request $request, Comment $comment, string $status): Response
    {
        if ($this->isCsrfTokenValid('comment' . $comment->getId(), (string) $request->request->get('_token'))) {
            $comment->setStatus(CommentStatus::from($status));
            $this->entityManager->flush();
            $this->auditLog->log($this->getUser(), 'update', Comment::class, (int) $comment->getId());
        }

        return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Comment $comment): Response
    {
        if (!$this->isCsrfTokenValid('comment' . $comment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
        }

        $commentId = (int) $comment->getId();
        $this->commentService->delete($comment);
        $this->auditLog->log($this->getUser(), 'delete', Comment::class, $commentId);
        $this->addFlash('success', 'Yorum kalıcı olarak silindi.');

        return $this->redirectToRoute('admin_comment_index', $this->backQuery($request));
    }

    #[Route('/purge-trash', name: 'purge_trash', methods: ['POST'])]
    public function purgeTrash(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('comment_purge_trash', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_comment_index', ['status' => 'trash']);
        }

        $deleted = $this->commentService->purgeTrash();
        $this->auditLog->log($this->getUser(), 'purge_trash', Comment::class, 0, [], ['deleted' => $deleted]);
        $this->addFlash('success', sprintf('%d çöp kutusu yorumu kalıcı olarak silindi.', $deleted));

        return $this->redirectToRoute('admin_comment_index');
    }

    /** @return array<string, string> */
    private function backQuery(Request $request): array
    {
        $params = [];
        if ($status = $request->request->get('back_status', $request->query->get('status'))) {
            $params['status'] = (string) $status;
        }
        if ($page = $request->request->getInt('back_page', $request->query->getInt('page', 0))) {
            $params['page'] = (string) $page;
        }

        return $params;
    }
}
