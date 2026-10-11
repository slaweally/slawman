<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SiteSettingsRepository;
use App\Service\AuditLogService;
use App\Service\CacheManagerService;
use App\Service\MailService;
use App\Service\MediaUploadService;
use App\Service\RedisCacheService;
use App\Service\SettingsRegistry;
use App\Service\SettingsService;
use App\Service\VarnishCacheService;
use App\Settings\SettingsSchema;
use App\Event\SettingsChangedEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/settings', name: 'admin_settings_')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly SettingsService $settingsService,
        private readonly SettingsRegistry $settingsRegistry,
        private readonly AuditLogService $auditLog,
        private readonly CacheManagerService $cacheManager,
        private readonly MailService $mailService,
        private readonly MediaUploadService $mediaUploadService,
        private readonly RedisCacheService $redisCacheService,
        private readonly VarnishCacheService $varnishCacheService,
        private readonly SettingsSchema $settingsSchema,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $settings = $this->settingsService->getAllValues();
        $activeTab = (string) $request->query->get('tab', 'general');
        $activeDefinitions = $this->settingsSchema->definitionsByGroup($activeTab);

        if ($request->isMethod('POST') && $request->request->get('action') === 'save') {
            if (!$this->isCsrfTokenValid('settings', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Geçersiz token.');

                return $this->redirectToRoute('admin_settings_index', ['tab' => $activeTab]);
            }

            $submitted = $request->request->all('settings');
            $groupKeys = array_keys($activeDefinitions);

            foreach ($groupKeys as $key) {
                $meta = $this->settingsRegistry->all()[$key];
                if (in_array($key, ['cache_redis_test_ok', 'cache_varnish_test_ok'], true)) {
                    continue;
                }
                if ($meta['type'] === 'checkbox') {
                    $this->settingsService->set($key, !empty($submitted[$key]) ? 'true' : 'false');
                } elseif ($meta['type'] === 'password' && ($submitted[$key] ?? '') === '') {
                    continue;
                } else {
                    $this->settingsService->set($key, isset($submitted[$key]) ? (string) $submitted[$key] : '');
                }
            }

            if ($activeTab === 'performance') {
                $this->validateCacheBackends($submitted);
            }

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->handleBrandingUpload($request, 'site_logo', 'site_logo_file', $user);
                $this->handleBrandingUpload($request, 'site_favicon', 'site_favicon_file', $user);
            }

            $this->auditLog->log($this->getUser(), 'update', 'SiteSettings', 0);
            $this->eventDispatcher->dispatch(new SettingsChangedEvent($groupKeys));
            $this->addFlash('success', 'Ayarlar kaydedildi.');

            return $this->redirectToRoute('admin_settings_index', ['tab' => $activeTab]);
        }

        return $this->render('admin/settings/index.html.twig', [
            'settings' => $settings,
            'activeTab' => $activeTab,
            'groups' => $this->settingsSchema->groups(),
            'definitions' => $activeDefinitions,
            'cloudPanelVarnish' => $this->varnishCacheService->detectCloudPanelSettings(),
        ]);
    }

    #[Route('/cache/clear', name: 'cache_clear', methods: ['POST'])]
    public function clearCache(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cache_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirect($request->headers->get('referer', $this->generateUrl('admin_dashboard')));
        }

        $result = $this->cacheManager->clearAndReport();
        $request->getSession()->set('maintenance_report', $result);
        $this->auditLog->log($this->getUser(), 'cache_clear', 'Cache', 0);

        return $this->redirect($request->headers->get('referer', $this->generateUrl('admin_dashboard')));
    }

    #[Route('/cache/test-redis', name: 'test_redis', methods: ['POST'])]
    public function testRedis(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('test_redis', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_PERFORMANCE]);
        }

        $dsn = (string) $request->request->get('cache_redis_dsn', $this->settingsService->get('cache_redis_dsn', ''));
        $result = $this->redisCacheService->test($dsn);
        $this->settingsService->set('cache_redis_test_ok', $result['success'] ? 'true' : 'false');
        if ($dsn !== '') {
            $this->settingsService->set('cache_redis_dsn', $dsn);
        }
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_PERFORMANCE]);
    }

    #[Route('/cache/test-varnish', name: 'test_varnish', methods: ['POST'])]
    public function testVarnish(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('test_varnish', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_PERFORMANCE]);
        }

        $host = (string) $request->request->get('cache_varnish_host', '127.0.0.1');
        $port = (int) $request->request->get('cache_varnish_port', 6081);
        $secret = (string) $request->request->get('cache_varnish_secret', '');
        $purgeUrl = (string) $request->request->get('cache_varnish_purge_url', '');
        $mode = (string) $request->request->get('cache_varnish_mode', VarnishCacheService::MODE_CLOUDPANEL);
        $tagPrefix = (string) $request->request->get('cache_varnish_tag_prefix', '');

        $this->settingsService->set('cache_varnish_mode', $mode);
        $this->settingsService->set('cache_varnish_host', $host);
        $this->settingsService->set('cache_varnish_port', (string) $port);
        if ($tagPrefix !== '') {
            $this->settingsService->set('cache_varnish_tag_prefix', $tagPrefix);
        }

        $result = $this->varnishCacheService->test($host, $port, $secret, $purgeUrl, $mode, $tagPrefix);
        $this->settingsService->set('cache_varnish_test_ok', $result['success'] ? 'true' : 'false');
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_PERFORMANCE]);
    }

    /** @param array<string, mixed> $submitted */
    private function validateCacheBackends(array $submitted): void
    {
        if (!empty($submitted['cache_redis_enabled'])) {
            $dsn = (string) ($submitted['cache_redis_dsn'] ?? $this->settingsService->get('cache_redis_dsn', ''));
            $test = $this->redisCacheService->test($dsn);
            if (!$test['success']) {
                $this->settingsService->set('cache_redis_enabled', 'false');
                $this->settingsService->set('cache_redis_test_ok', 'false');
                $this->addFlash('error', 'Redis etkinleştirilemedi: ' . $test['message']);
            } else {
                $this->settingsService->set('cache_redis_test_ok', 'true');
            }
        } else {
            $this->settingsService->set('cache_redis_enabled', 'false');
        }

        if (!empty($submitted['cache_varnish_enabled'])) {
            if (!$this->settingsService->isTruthy('cache_varnish_test_ok')) {
                $test = $this->varnishCacheService->test(
                    (string) ($submitted['cache_varnish_host'] ?? '127.0.0.1'),
                    (int) ($submitted['cache_varnish_port'] ?? 6081),
                    (string) ($submitted['cache_varnish_secret'] ?? ''),
                    (string) ($submitted['cache_varnish_purge_url'] ?? ''),
                    (string) ($submitted['cache_varnish_mode'] ?? VarnishCacheService::MODE_CLOUDPANEL),
                    (string) ($submitted['cache_varnish_tag_prefix'] ?? ''),
                );
                if (!$test['success']) {
                    $this->settingsService->set('cache_varnish_enabled', 'false');
                    $this->settingsService->set('cache_varnish_test_ok', 'false');
                    $this->addFlash('error', 'Varnish etkinleştirilemedi: ' . $test['message']);

                    return;
                }
                $this->settingsService->set('cache_varnish_test_ok', 'true');
            }
        } else {
            $this->settingsService->set('cache_varnish_enabled', 'false');
        }
    }

    #[Route('/smtp/test', name: 'smtp_test', methods: ['POST'])]
    public function testSmtp(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('smtp_test', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_SMTP]);
        }

        $to = (string) $request->request->get('test_email', $this->settingsService->get('site_email', ''));

        try {
            $this->mailService->sendTestEmail($to);
            $this->addFlash('success', 'Test e-postası gönderildi: ' . $to);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'E-posta gönderilemedi: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_settings_index', ['tab' => SettingsRegistry::GROUP_SMTP]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): JsonResponse
    {
        return $this->json([
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'settings' => $this->settingsService->getAllValues(),
        ]);
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('settings_import', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_settings_index');
        }

        $payload = json_decode((string) $request->request->get('payload', '{}'), true);
        if (!is_array($payload) || !isset($payload['settings']) || !is_array($payload['settings'])) {
            $this->addFlash('error', 'Geçersiz import verisi.');

            return $this->redirectToRoute('admin_settings_index');
        }

        $whitelist = array_keys($this->settingsRegistry->all());
        foreach ($payload['settings'] as $key => $value) {
            if (in_array((string) $key, $whitelist, true)) {
                $this->settingsService->set((string) $key, is_scalar($value) ? (string) $value : '');
            }
        }

        $this->addFlash('success', 'Ayarlar içe aktarıldı.');

        return $this->redirectToRoute('admin_settings_index');
    }

    private function handleBrandingUpload(Request $request, string $settingKey, string $fileKey, \App\Entity\User $user): void
    {
        $file = $request->files->get($fileKey);
        if (!$file instanceof UploadedFile) {
            return;
        }

        $media = $this->mediaUploadService->upload($file, $user);
        $this->settingsService->set($settingKey, $this->mediaUploadService->getPublicUrl($media));
    }
}
