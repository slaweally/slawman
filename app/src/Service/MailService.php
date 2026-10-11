<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SiteOwnerService $siteOwnerService,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->settingsService->get('smtp_host') !== ''
            && $this->settingsService->get('smtp_from_email') !== '';
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('SMTP ayarları yapılandırılmamış.');
        }

        $mailer = $this->createMailer();
        $fromEmail = (string) $this->settingsService->get('smtp_from_email');
        $fromName = $this->settingsService->get('smtp_from_name') ?: $this->siteOwnerService->getDisplayName('Slawman');

        $email = (new Email())
            ->from(sprintf('%s <%s>', $fromName, $fromEmail))
            ->to($to)
            ->subject($subject)
            ->text($textBody);

        if ($htmlBody !== null) {
            $email->html($htmlBody);
        }

        $mailer->send($email);
    }

    public function sendContactNotification(
        string $to,
        string $name,
        string $fromEmail,
        string $subject,
        string $message,
        ?string $adminUrl = null,
    ): void {
        $text = sprintf(
            "Yeni iletişim mesajı\n\nAd: %s\nE-posta: %s\nKonu: %s\n\n%s",
            $name,
            $fromEmail,
            $subject,
            $message
        );

        if ($adminUrl !== null) {
            $text .= "\n\nAdmin: " . $adminUrl;
        }

        $html = sprintf(
            '<h2>Yeni iletişim mesajı</h2><p><strong>Ad:</strong> %s<br><strong>E-posta:</strong> %s<br><strong>Konu:</strong> %s</p><pre>%s</pre>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );

        if ($adminUrl !== null) {
            $html .= sprintf('<p><a href="%s">Mesajları görüntüle</a></p>', htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8'));
        }

        $this->send($to, '[İletişim] ' . $subject, $text, $html);
    }

    public function sendCommentNotification(string $to, Comment $comment, string $adminUrl): void
    {
        $post = $comment->getPost();
        $postTitle = $post?->getTitle() ?: ($post?->getContent() ? mb_substr(strip_tags($post->getContent()), 0, 60) : 'İçerik');
        $status = $comment->getStatus()->value;
        $content = strip_tags($comment->getContent() ?? '');

        $text = sprintf(
            "Yeni yorum (%s)\n\nYazar: %s <%s>\nYazı: %s\n\n%s\n\nAdmin: %s",
            $status,
            $comment->getAuthorName() ?? '',
            $comment->getAuthorEmail() ?? '',
            $postTitle,
            $content,
            $adminUrl
        );

        $html = sprintf(
            '<h2>Yeni yorum</h2><p><strong>Durum:</strong> %s<br><strong>Yazar:</strong> %s &lt;%s&gt;<br><strong>Yazı:</strong> %s</p><pre>%s</pre><p><a href="%s">Yorumları görüntüle</a></p>',
            htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $comment->getAuthorName(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $comment->getAuthorEmail(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8')
        );

        $this->send($to, '[Yorum] ' . mb_substr($postTitle, 0, 50), $text, $html);
    }

    /** @param array<string, string> $details */
    public function sendSecurityAlert(string $to, string $title, array $details): void
    {
        $lines = [];
        foreach ($details as $label => $value) {
            $lines[] = sprintf('%s: %s', $label, $value);
        }
        $text = sprintf("GÜVENLİK UYARISI: %s\n\n%s", $title, implode("\n", $lines));

        $rows = '';
        foreach ($details as $label => $value) {
            $rows .= sprintf(
                '<tr><td style="padding:4px 10px;color:#888;">%s</td><td style="padding:4px 10px;"><code>%s</code></td></tr>',
                htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );
        }
        $html = sprintf(
            '<h2 style="color:#f85149;">🚨 Güvenlik Uyarısı: %s</h2><table>%s</table>',
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $rows
        );

        $this->send($to, '[GÜVENLİK] ' . $title, $text, $html);
    }

    /**
     * @param list<array{type: string, cases: int, hits: int}> $byType
     * @param list<array{ipAddress: string, hits: int}> $topOffenders
     */
    public function sendSecurityDigest(
        string $to,
        \DateTimeImmutable $since,
        int $totalHits,
        int $bannedCount,
        array $byType,
        array $topOffenders,
        string $adminUrl,
    ): void {
        $typeLines = [];
        foreach ($byType as $row) {
            $typeLines[] = sprintf('- %s: %d istek (%d vaka)', SecurityAlertService::labelFor($row['type']), $row['hits'], $row['cases']);
        }

        $offenderLines = [];
        foreach ($topOffenders as $row) {
            $offenderLines[] = sprintf('- %s: %d istek', $row['ipAddress'], $row['hits']);
        }

        $text = sprintf(
            "Güvenlik Günlük Özeti (%s'ten bu yana)\n\nToplam şüpheli istek: %d\nBanlanan IP sayısı: %d\n\nTüre göre dağılım:\n%s\n\nEn çok tekrar eden IP'ler:\n%s\n\nAdmin: %s",
            $since->format('Y-m-d H:i'),
            $totalHits,
            $bannedCount,
            $typeLines !== [] ? implode("\n", $typeLines) : '- Kayıt yok',
            $offenderLines !== [] ? implode("\n", $offenderLines) : '- Kayıt yok',
            $adminUrl
        );

        $typeRows = '';
        foreach ($byType as $row) {
            $typeRows .= sprintf(
                '<tr><td style="padding:4px 10px;">%s</td><td style="padding:4px 10px;">%d</td><td style="padding:4px 10px;color:#888;">%d vaka</td></tr>',
                htmlspecialchars(SecurityAlertService::labelFor($row['type']), ENT_QUOTES, 'UTF-8'),
                $row['hits'],
                $row['cases']
            );
        }

        $offenderRows = '';
        foreach ($topOffenders as $row) {
            $offenderRows .= sprintf(
                '<tr><td style="padding:4px 10px;"><code>%s</code></td><td style="padding:4px 10px;">%d istek</td></tr>',
                htmlspecialchars($row['ipAddress'], ENT_QUOTES, 'UTF-8'),
                $row['hits']
            );
        }

        $html = sprintf(
            '<h2>🛡️ Güvenlik Günlük Özeti</h2><p><strong>%s\'ten bu yana</strong></p><p>Toplam şüpheli istek: <strong>%d</strong> &nbsp;|&nbsp; Banlanan IP: <strong>%d</strong></p><h3>Türe göre dağılım</h3><table>%s</table><h3>En çok tekrar eden IP\'ler</h3><table>%s</table><p><a href="%s">Tüm alarmları görüntüle</a></p>',
            $since->format('Y-m-d H:i'),
            $totalHits,
            $bannedCount,
            $typeRows !== '' ? $typeRows : '<tr><td>Kayıt yok</td></tr>',
            $offenderRows !== '' ? $offenderRows : '<tr><td>Kayıt yok</td></tr>',
            htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8')
        );

        $this->send($to, sprintf('[Güvenlik Özeti] %d şüpheli istek, %d IP banlandı', $totalHits, $bannedCount), $text, $html);
    }

    public function sendTestEmail(string $to): void
    {
        $this->send($to, 'Slawman SMTP Test', 'SMTP ayarlarınız başarıyla çalışıyor.');
    }

    private function createMailer(): Mailer
    {
        $host = (string) $this->settingsService->get('smtp_host');
        $port = (int) ($this->settingsService->get('smtp_port') ?: 587);
        $user = $this->settingsService->get('smtp_user') ?? '';
        $pass = $this->settingsService->get('smtp_password') ?? '';
        $encryption = $this->settingsService->get('smtp_encryption') ?: 'tls';

        $dsn = sprintf(
            'smtp://%s:%s@%s:%d?encryption=%s',
            rawurlencode($user),
            rawurlencode($pass),
            $host,
            $port,
            $encryption
        );

        return new Mailer(Transport::fromDsn($dsn));
    }
}
