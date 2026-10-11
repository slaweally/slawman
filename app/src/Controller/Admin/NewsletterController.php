<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Newsletter\NewsletterTemplateId;
use App\Newsletter\NewsletterTemplateRegistry;
use App\Repository\SubscriberRepository;
use App\Service\AuditLogService;
use App\Service\NewsletterService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tools/newsletter', name: 'admin_tools_newsletter_')]
#[IsGranted('ROLE_ADMIN')]
class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
        private readonly NewsletterTemplateRegistry $templateRegistry,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SettingsService $settingsService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $defaultTestEmail = (string) ($this->settingsService->get('notify_admin_email')
            ?: $this->settingsService->get('site_email')
            ?: ($this->getUser() instanceof User ? $this->getUser()->getUserIdentifier() : ''));

        return $this->render('admin/tools/newsletter.html.twig', [
            'templates' => $this->templateRegistry->all(),
            'activeCount' => $this->subscriberRepository->countActive(),
            'smtpConfigured' => $this->newsletterService->isConfigured(),
            'defaultTestEmail' => $defaultTestEmail,
        ]);
    }

    #[Route('/preview', name: 'preview', methods: ['POST'])]
    public function preview(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('newsletter_preview', (string) $request->request->get('_token'))) {
            return new Response('Geçersiz güvenlik tokeni.', Response::HTTP_FORBIDDEN);
        }

        $templateId = $this->resolveTemplateId($request);
        if ($templateId === null) {
            return new Response('Geçersiz şablon.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $html = $this->newsletterService->renderPreview($templateId, $this->extractPayload($request));

            return new Response($html);
        } catch (\Throwable $e) {
            return new Response('Önizleme oluşturulamadı: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/test', name: 'test', methods: ['POST'])]
    public function test(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('newsletter_test', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        if (!$this->newsletterService->isConfigured()) {
            $this->addFlash('error', 'SMTP ayarları yapılandırılmamış. Ayarlar → SMTP bölümünden yapılandırın.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        $templateId = $this->resolveTemplateId($request);
        if ($templateId === null) {
            $this->addFlash('error', 'Geçersiz şablon seçildi.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        $testEmail = trim((string) $request->request->get('test_email', ''));
        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Geçerli bir test e-posta adresi girin.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        try {
            $this->newsletterService->sendTest($templateId, $this->extractPayload($request), $testEmail);
            $this->addFlash('success', sprintf('Test e-postası %s adresine gönderildi.', $testEmail));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Test gönderimi başarısız: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_newsletter_index');
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('newsletter_send', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz güvenlik tokeni.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        if (!$this->newsletterService->isConfigured()) {
            $this->addFlash('error', 'SMTP ayarları yapılandırılmamış.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        if (!$request->request->getBoolean('confirm_send')) {
            $this->addFlash('error', 'Toplu gönderim için onay kutusunu işaretleyin.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        $templateId = $this->resolveTemplateId($request);
        if ($templateId === null) {
            $this->addFlash('error', 'Geçersiz şablon seçildi.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        $subscribers = $this->subscriberRepository->findAllActive();
        if ($subscribers === []) {
            $this->addFlash('error', 'Aktif abone bulunamadı.');

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        $payload = $this->extractPayload($request);
        $validationError = $this->validatePayload($templateId, $payload);
        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return $this->redirectToRoute('admin_tools_newsletter_index');
        }

        try {
            $result = $this->newsletterService->sendBulk($templateId, $payload, $subscribers);

            $user = $this->getUser();
            $this->auditLogService->log(
                $user instanceof User ? $user : null,
                'newsletter_send',
                'Subscriber',
                0,
                [],
                [
                    'template' => $templateId->value,
                    'subject' => $this->newsletterService->resolveSubject($templateId, $payload),
                    'sent' => $result['sent'],
                    'failed' => $result['failed'],
                    'total' => \count($subscribers),
                ]
            );

            if ($result['failed'] > 0) {
                $this->addFlash('warning', sprintf(
                    '%d / %d aboneye gönderildi. %d başarısız.',
                    $result['sent'],
                    \count($subscribers),
                    $result['failed']
                ));
                if ($result['errors'] !== []) {
                    $this->addFlash('error', implode(' · ', $result['errors']));
                }
            } else {
                $this->addFlash('success', sprintf('%d aktif aboneye e-posta gönderildi.', $result['sent']));
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Toplu gönderim başarısız: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tools_newsletter_index');
    }

    private function resolveTemplateId(Request $request): ?NewsletterTemplateId
    {
        return NewsletterTemplateId::tryFromString(trim((string) $request->request->get('template', '')));
    }

    /** @return array<string, string> */
    private function extractPayload(Request $request): array
    {
        $keys = [
            'subject', 'title', 'intro', 'body', 'body_html', 'highlight',
            'cta_text', 'cta_url', 'post_title', 'post_excerpt', 'post_url',
            'post_type', 'digest_items',
        ];

        $payload = [];
        foreach ($keys as $key) {
            $payload[$key] = trim((string) $request->request->get($key, ''));
        }

        return $payload;
    }

    /** @param array<string, string> $payload */
    private function validatePayload(NewsletterTemplateId $templateId, array $payload): ?string
    {
        return match ($templateId) {
            NewsletterTemplateId::Newsletter => (trim($payload['body'] ?? '') === '' && trim($payload['intro'] ?? '') === '')
                ? 'Bülten için en az giriş metni veya içerik girin.'
                : null,
            NewsletterTemplateId::Announcement => trim($payload['body'] ?? '') === ''
                ? 'Duyuru metni boş olamaz.'
                : null,
            NewsletterTemplateId::NewPost => trim($payload['post_title'] ?? '') === ''
                ? 'İçerik başlığı zorunludur.'
                : null,
            NewsletterTemplateId::Digest => trim($payload['digest_items'] ?? '') === ''
                ? 'En az bir özet maddesi girin (her satır bir madde).'
                : null,
            NewsletterTemplateId::Custom => (trim($payload['body_html'] ?? '') === '' && trim($payload['body'] ?? '') === '')
                ? 'Serbest içerik için metin veya HTML girin.'
                : null,
        };
    }
}
