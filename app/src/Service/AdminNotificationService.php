<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\ContactMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminNotificationService
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly SettingsService $settingsService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyNewComment(Comment $comment): void
    {
        if (!$this->settingsService->isTruthy('notify_comment_enabled')) {
            return;
        }

        $to = $this->resolveAdminEmail();
        if ($to === null) {
            return;
        }

        if (!$this->mailService->isConfigured()) {
            $this->logger->warning('Yorum bildirimi atlandı: SMTP yapılandırılmamış.');

            return;
        }

        try {
            $this->mailService->sendCommentNotification(
                $to,
                $comment,
                $this->urlGenerator->generate('admin_comment_index', ['status' => 'pending'], UrlGeneratorInterface::ABSOLUTE_URL)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Yorum bildirimi gönderilemedi: {message}', ['message' => $e->getMessage()]);
        }
    }

    public function notifyNewContact(ContactMessage $message): void
    {
        if (!$this->settingsService->isTruthy('notify_contact_enabled')) {
            return;
        }

        $to = $this->resolveAdminEmail();
        if ($to === null) {
            return;
        }

        if (!$this->mailService->isConfigured()) {
            $this->logger->warning('İletişim bildirimi atlandı: SMTP yapılandırılmamış.');

            return;
        }

        try {
            $this->mailService->sendContactNotification(
                $to,
                $message->getName() ?? '',
                $message->getEmail() ?? '',
                $message->getSubject() ?? '',
                $message->getMessage() ?? '',
                $this->urlGenerator->generate('admin_contact_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            );
        } catch (\Throwable $e) {
            $this->logger->error('İletişim bildirimi gönderilemedi: {message}', ['message' => $e->getMessage()]);
        }
    }

    /** @param array<string, string> $details */
    public function notifySecurityAlert(string $title, array $details): void
    {
        $to = $this->resolveAdminEmail();
        if ($to === null || !$this->mailService->isConfigured()) {
            $this->logger->warning('Güvenlik uyarı maili atlandı: alıcı yok veya SMTP yapılandırılmamış.', ['title' => $title]);

            return;
        }

        try {
            $this->mailService->sendSecurityAlert($to, $title, $details);
        } catch (\Throwable $e) {
            $this->logger->error('Güvenlik uyarı maili gönderilemedi: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param list<array{type: string, cases: int, hits: int}> $byType
     * @param list<array{ipAddress: string, hits: int}> $topOffenders
     */
    public function notifySecurityDigest(
        \DateTimeImmutable $since,
        int $totalHits,
        int $bannedCount,
        array $byType,
        array $topOffenders,
    ): bool {
        $to = $this->resolveAdminEmail();
        if ($to === null || !$this->mailService->isConfigured()) {
            $this->logger->warning('Güvenlik günlük özet maili atlandı: alıcı yok veya SMTP yapılandırılmamış.');

            return false;
        }

        try {
            $this->mailService->sendSecurityDigest(
                $to,
                $since,
                $totalHits,
                $bannedCount,
                $byType,
                $topOffenders,
                $this->urlGenerator->generate('admin_security_alert_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Güvenlik günlük özet maili gönderilemedi: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    private function resolveAdminEmail(): ?string
    {
        $email = trim((string) $this->settingsService->get('notify_admin_email', ''));
        if ($email !== '') {
            return $email;
        }

        $siteEmail = trim((string) $this->settingsService->get('site_email', ''));
        if ($siteEmail !== '') {
            return $siteEmail;
        }

        return null;
    }
}
