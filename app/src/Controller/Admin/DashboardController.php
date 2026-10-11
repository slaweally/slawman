<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Enum\PostStatus;
use App\Enum\PostType;
use App\Repository\AuditLogRepository;
use App\Repository\CommentRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\MediaRepository;
use App\Repository\PostRepository;
use App\Repository\SecurityAlertRepository;
use App\Repository\SubscriberRepository;
use App\Repository\TrackerLogRepository;
use App\Service\AuditLogService;
use App\Service\SecurityAlertService;
use App\Service\SystemMetricsService;
use App\Service\VisitorStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    private const TRACKER_LOG_WARNING_THRESHOLD = 50000;

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly VisitorStatsService $visitorStatsService,
        private readonly SystemMetricsService $systemMetricsService,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly SecurityAlertService $securityAlertService,
        private readonly TrackerLogRepository $trackerLogRepository,
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {
        $visitorStats = $this->visitorStatsService->getDashboardStats();
        $chart = $visitorStats['chart'];
        $maxChartViews = max(1, ...array_column($chart, 'page_views'));

        $postCount = $this->postRepository->countActive();
        $pendingComments = $this->commentRepository->countPending();
        $unreadMessages = $this->contactMessageRepository->countUnread();
        $systemMetrics = $this->systemMetricsService->collect();

        $since24h = new \DateTimeImmutable('-24 hours');
        $trackerLogCount = $this->trackerLogRepository->countAll();

        return $this->render('admin/dashboard/index.html.twig', [
            'recentPosts' => $this->postRepository->createAdminListQueryBuilder()
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults(6)
                ->getQuery()
                ->getResult(),
            'recentComments' => $this->commentRepository->findRecentApproved(5),
            'topViews' => $this->postRepository->findTopByViewCount(5),
            'topComments' => $this->postRepository->findTopByCommentCount(5),
            'postCount' => $postCount,
            'pendingComments' => $pendingComments,
            'mediaCount' => $this->mediaRepository->count([]),
            'subscriberCount' => $this->subscriberRepository->count([]),
            'unreadMessages' => $unreadMessages,
            'visitorStats' => $visitorStats,
            'maxChartViews' => $maxChartViews,
            'auditLogs' => $this->auditLogRepository->findBy([], ['createdAt' => 'DESC'], 8),
            'systemMetrics' => $systemMetrics,
            'systemMetricsHuman' => [
                'db' => $this->systemMetricsService->formatBytes($systemMetrics['db_size_bytes']),
                'project' => $this->systemMetricsService->formatBytes($systemMetrics['project_size_bytes']),
                'cache' => $this->systemMetricsService->formatBytes($systemMetrics['cache_size_bytes']),
                'media' => $this->systemMetricsService->formatBytes($systemMetrics['media_size_bytes']),
                'total_disk' => $this->systemMetricsService->formatBytes($systemMetrics['total_disk_bytes'] + $systemMetrics['db_size_bytes']),
            ],
            'securityBannedIpCount' => $this->securityAlertService->countActiveBans(),
            'securityCritical24h' => $this->securityAlertRepository->countCriticalSince($since24h),
            'securityTotal24h' => $this->securityAlertRepository->countSince($since24h),
            'trackerLogCount' => $trackerLogCount,
            'trackerLogWarning' => $trackerLogCount >= self::TRACKER_LOG_WARNING_THRESHOLD,
            'trackerLogWarningThreshold' => self::TRACKER_LOG_WARNING_THRESHOLD,
        ]);
    }

    #[Route('/quick-note', name: 'admin_quick_note', methods: ['POST'])]
    public function quickNote(Request $request): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('quick_note', (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => 'Geçersiz güvenlik tokeni. Sayfayı yenileyip tekrar deneyin.'], 400);
            }

            $this->addFlash('error', 'Geçersiz güvenlik tokeni. Sayfayı yenileyip tekrar deneyin.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $rawContent = (string) $request->request->get('content', '');
        $content = mb_substr(trim(strip_tags($rawContent)), 0, 250);
        if ($content === '') {
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'error' => 'Not boş olamaz (en fazla 250 karakter).'], 422);
            }

            $this->addFlash('error', 'Not boş olamaz (en fazla 250 karakter).');
            return $this->redirectToRoute('admin_dashboard');
        }

        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $post = new Post();
            $post->setContent($content);
            $post->setType(PostType::Note);
            $post->setStatus(PostStatus::Published);
            $post->setSlug('note-' . bin2hex(random_bytes(6)));
            $post->setAuthor($user);
            $post->setPublishedAt(new \DateTime());

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $this->auditLogService->log($user, 'create', Post::class, (int) $post->getId());

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Not yayınlandı.',
                    'id' => $post->getId(),
                    'preview' => mb_strlen($content) > 35 ? mb_substr($content, 0, 35) . '…' : $content,
                    'editUrl' => $this->generateUrl('admin_post_edit', ['id' => $post->getId()]),
                    'createdAt' => $post->getCreatedAt()?->format('d.m') ?? (new \DateTime())->format('d.m'),
                ]);
            }

            $this->addFlash('success', 'Not yayınlandı.');
            return $this->redirectToRoute('admin_dashboard');
        } catch (\Throwable) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Not kaydedilemedi. Lütfen tekrar deneyin.',
                ], 500);
            }

            $this->addFlash('error', 'Not kaydedilemedi. Lütfen tekrar deneyin.');
            return $this->redirectToRoute('admin_dashboard');
        }
    }
}
