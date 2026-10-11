<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\SecurityAlertRepository;
use App\Repository\TrackerLogRepository;
use App\Service\AuditLogService;
use App\Service\BuildAssetDeployService;
use App\Service\RevisionCleanupService;
use App\Service\SecurityAlertService;
use App\Service\SettingsService;
use App\Service\VisitorStatsService;
use App\Service\YandexMetrikaApiService;
use App\Service\YandexMetrikaOauthService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tools', name: 'admin_tools_')]
#[IsGranted('ROLE_ADMIN')]
class ToolsController extends AbstractController
{
    public function __construct(
        private readonly BuildAssetDeployService $buildAssetDeployService,
        private readonly AuditLogService $auditLogService,
        private readonly RevisionCleanupService $revisionCleanupService,
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly SecurityAlertService $securityAlertService,
        private readonly SettingsService $settingsService,
        private readonly YandexMetrikaOauthService $metrikaOauthService,
        private readonly YandexMetrikaApiService $metrikaApiService,
        private readonly VisitorStatsService $visitorStatsService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/tools/index.html.twig');
    }

    #[Route('/phpinfo', name: 'phpinfo')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function phpinfo(): Response
    {
        ob_start();
        phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
        $html = (string) ob_get_clean();

        return new Response($html);
    }

    #[Route('/assets/deploy', name: 'assets_deploy', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deployAssets(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('deploy_assets', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Gecersiz guvenlik tokeni. Lutfen tekrar deneyin.');
            return $this->redirectToRoute('admin_tools_index');
        }

        $archive = $request->files->get('build_archive');
        if (!$archive instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $this->addFlash('error', 'Lutfen bir build zip dosyasi secin.');
            return $this->redirectToRoute('admin_tools_index');
        }

        try {
            $result = $this->buildAssetDeployService->deployFromZip($archive, $this->projectDir);

            $currentUser = $this->getUser();
            $user = $currentUser instanceof User ? $currentUser : null;
            $this->auditLogService->log(
                $user,
                'assets_deploy',
                'BuildAssets',
                0,
                [],
                [
                    'files' => $result['copied_files'],
                    'target' => $result['target_dir'],
                    'archive' => $archive->getClientOriginalName(),
                ]
            );

            $this->addFlash('success', sprintf('Build paketi basariyla uygulandi (%d dosya).', $result['copied_files']));
            $this->addFlash('warning', 'Simdi cache temizleyin ve sayfayi hard refresh yapin (Ctrl+F5).');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Build deploy basarisiz: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_index');
    }

    #[Route('/maintenance', name: 'maintenance')]
    public function maintenance(): Response
    {
        return $this->render('admin/tools/maintenance.html.twig', [
            'revisionStats' => $this->revisionCleanupService->getStats(),
        ]);
    }

    #[Route('/maintenance/revisions/prune', name: 'maintenance_revisions_prune', methods: ['POST'])]
    public function pruneRevisions(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_revisions_prune', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        $keep = $request->request->getInt('keep_per_post', 15);
        if ($keep < 0 || $keep > 500) {
            $this->addFlash('error', 'Her içerik için saklanacak revizyon sayısı 0–500 arasında olmalıdır.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        try {
            $deleted = $this->revisionCleanupService->pruneKeepLatestPerPost($keep);
            $this->logMaintenance('revision_prune', ['keep_per_post' => $keep, 'deleted' => $deleted]);
            $this->addFlash('success', sprintf(
                'Temizlik tamamlandı: her içerikte en yeni %d revizyon bırakıldı, %d kayıt silindi.',
                $keep,
                $deleted
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Revizyon temizliği başarısız: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_maintenance');
    }

    #[Route('/maintenance/revisions/delete-older', name: 'maintenance_revisions_delete_older', methods: ['POST'])]
    public function deleteOlderRevisions(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_revisions_delete_older', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        $days = $request->request->getInt('older_than_days', 90);
        if ($days < 1 || $days > 3650) {
            $this->addFlash('error', 'Gün sayısı 1–3650 arasında olmalıdır.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        try {
            $deleted = $this->revisionCleanupService->deleteOlderThanDays($days);
            $this->logMaintenance('revision_delete_older', ['older_than_days' => $days, 'deleted' => $deleted]);
            $this->addFlash('success', sprintf('%d günden eski %d revizyon silindi.', $days, $deleted));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Eski revizyon silme başarısız: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_maintenance');
    }

    #[Route('/maintenance/revisions/delete-all', name: 'maintenance_revisions_delete_all', methods: ['POST'])]
    public function deleteAllRevisions(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_revisions_delete_all', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        if (!$request->request->getBoolean('confirm_delete_all')) {
            $this->addFlash('error', 'Tüm revizyonları silmek için onay kutusunu işaretleyin.');

            return $this->redirectToRoute('admin_tools_maintenance');
        }

        try {
            $deleted = $this->revisionCleanupService->deleteAll();
            $this->logMaintenance('revision_delete_all', ['deleted' => $deleted]);
            $this->addFlash('success', sprintf('Tüm revizyon geçmişi silindi (%d kayıt).', $deleted));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Revizyon silme başarısız: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_maintenance');
    }

    #[Route('/maintenance/sweep-all-logs', name: 'maintenance_sweep_all_logs', methods: ['POST'])]
    public function sweepAllLogs(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('maintenance_sweep_all_logs', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Geçersiz güvenlik tokeni.'], 400);
        }

        try {
            $trackerDeleted = $this->trackerLogRepository->deleteAll();
            $securityDeleted = $this->securityAlertRepository->deleteAll();
            $auditDeleted = $this->auditLogRepository->deleteAll();
            $this->securityAlertService->clearAllBans();

            $total = $trackerDeleted + $securityDeleted + $auditDeleted;

            $this->logMaintenance('sweep_all_logs', [
                'tracker_log' => $trackerDeleted,
                'security_alert' => $securityDeleted,
                'audit_log' => $auditDeleted,
                'total' => $total,
            ]);

            return $this->json([
                'ok' => true,
                'total' => $total,
                'breakdown' => [
                    'Takip logları' => $trackerDeleted,
                    'Güvenlik alarmları' => $securityDeleted,
                    'Audit kayıtları' => $auditDeleted,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'Temizlik başarısız: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/analytics', name: 'analytics')]
    public function analytics(): Response
    {
        $isAuthorized = $this->metrikaOauthService->isAuthorized();

        return $this->render('admin/tools/analytics.html.twig', [
            'settings' => $this->settingsService->getAllValues(),
            'isAuthorized' => $isAuthorized,
            'oauthAuthorizeUrl' => 'https://oauth.yandex.com/authorize?response_type=code&client_id=' . YandexMetrikaOauthService::YANDEX_APP_ID,
            'dashboardStats' => $this->visitorStatsService->getDashboardStats(),
            'counters' => $isAuthorized ? $this->metrikaApiService->getCounters() : [],
        ]);
    }

    #[Route('/analytics/save', name: 'analytics_save', methods: ['POST'])]
    public function saveAnalytics(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tools_analytics', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_analytics');
        }

        $checkboxKeys = ['metrika_enabled', 'metrika_webvisor', 'metrika_clickmap', 'metrika_tracklinks', 'metrika_accurate_track', 'metrika_track_hash'];
        foreach ($checkboxKeys as $key) {
            $this->settingsService->set($key, !empty($request->request->get($key)) ? 'true' : 'false');
        }

        $this->settingsService->set('metrika_counter_id', (string) $request->request->get('metrika_counter_id', ''));

        $this->addFlash('success', 'Yandex Metrika ayarları kaydedildi.');

        return $this->redirectToRoute('admin_tools_analytics');
    }

    #[Route('/analytics/authorize', name: 'analytics_authorize', methods: ['POST'])]
    public function authorizeAnalytics(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tools_analytics_authorize', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_analytics');
        }

        $confirmationCode = (string) $request->request->get('confirmation_code', '');
        if ($confirmationCode === '') {
            $this->addFlash('error', 'Lütfen yetkilendirme kodunu girin.');

            return $this->redirectToRoute('admin_tools_analytics');
        }

        if ($this->metrikaOauthService->connect($confirmationCode)) {
            $this->addFlash('success', 'Yandex Metrika başarıyla bağlandı.');
        } else {
            $this->addFlash('error', 'Yandex Metrika bağlantısı başarısız oldu. Yetkilendirme kodunu kontrol edin.');
        }

        return $this->redirectToRoute('admin_tools_analytics');
    }

    /** @param array<string, mixed> $context */
    private function logMaintenance(string $action, array $context): void
    {
        $user = $this->getUser();
        $this->auditLogService->log(
            $user instanceof User ? $user : null,
            $action,
            'PostRevision',
            0,
            [],
            $context
        );
    }
}
