<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Subscriber;
use App\Newsletter\NewsletterTemplateId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Abonelere SMTP üzerinden şablonlu e-posta gönderimi.
 */
class NewsletterService
{
    private const SEND_DELAY_MICROSECONDS = 100_000;

    public function __construct(
        private readonly MailService $mailService,
        private readonly SettingsService $settingsService,
        private readonly SiteOwnerService $siteOwnerService,
        private readonly ContentSanitizerService $contentSanitizer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->mailService->isConfigured();
    }

    /**
     * @param array<string, string> $payload
     */
    public function renderPreview(NewsletterTemplateId $templateId, array $payload, ?Subscriber $subscriber = null): string
    {
        $context = $this->buildRenderContext($templateId, $payload, $subscriber);

        return $this->twig->render($templateId->twigTemplate(), $context);
    }

    /**
     * @param array<string, string> $payload
     */
    public function resolveSubject(NewsletterTemplateId $templateId, array $payload): string
    {
        $subject = trim($payload['subject'] ?? '');
        if ($subject === '') {
            $siteTitle = (string) $this->settingsService->get('site_title', 'Slawman');
            $subject = $templateId->defaultSubject($siteTitle);
        }

        return mb_substr($subject, 0, 200);
    }

    /**
     * @param array<string, string> $payload
     */
    public function sendTest(NewsletterTemplateId $templateId, array $payload, string $toEmail): void
    {
        $subject = $this->resolveSubject($templateId, $payload);
        $html = $this->renderPreview($templateId, $payload);
        $text = $this->htmlToPlainText($html);

        $this->mailService->send($toEmail, '[TEST] ' . $subject, $text, $html);
    }

    /**
     * @param array<string, string> $payload
     * @param list<Subscriber>      $subscribers
     *
     * @return array{sent: int, failed: int, errors: list<string>}
     */
    public function sendBulk(NewsletterTemplateId $templateId, array $payload, array $subscribers): array
    {
        $subject = $this->resolveSubject($templateId, $payload);
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($subscribers as $subscriber) {
            $email = $subscriber->getEmail();
            if ($email === null || $email === '') {
                continue;
            }

            try {
                $html = $this->renderPreview($templateId, $payload, $subscriber);
                $text = $this->htmlToPlainText($html);
                $this->mailService->send($email, $subject, $text, $html);
                ++$sent;
            } catch (\Throwable $e) {
                ++$failed;
                if (\count($errors) < 10) {
                    $errors[] = sprintf('%s: %s', $email, $e->getMessage());
                }
            }

            usleep(self::SEND_DELAY_MICROSECONDS);
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function buildRenderContext(NewsletterTemplateId $templateId, array $payload, ?Subscriber $subscriber): array
    {
        $siteTitle = (string) $this->settingsService->get('site_title', 'Slawman');
        $siteAuthor = $this->siteOwnerService->getDisplayName('Slaweally');
        $siteUrl = $this->resolveSiteUrl();

        $context = [
            'siteTitle' => $siteTitle,
            'siteAuthor' => $siteAuthor,
            'siteUrl' => $siteUrl,
            'year' => date('Y'),
            'unsubscribeUrl' => $this->buildUnsubscribeUrl($subscriber),
        ];

        return array_merge($context, $this->normalizePayload($templateId, $payload));
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function normalizePayload(NewsletterTemplateId $templateId, array $payload): array
    {
        $body = trim($payload['body'] ?? '');
        $bodyHtml = trim($payload['body_html'] ?? '');

        return match ($templateId) {
            NewsletterTemplateId::Newsletter => [
                'title' => trim($payload['title'] ?? '') ?: $this->settingsService->get('site_title', 'Slawman'),
                'intro' => trim($payload['intro'] ?? ''),
                'body' => $body !== '' ? nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) : '',
                'ctaText' => trim($payload['cta_text'] ?? ''),
                'ctaUrl' => trim($payload['cta_url'] ?? '') ?: $this->resolveSiteUrl(),
            ],
            NewsletterTemplateId::Announcement => [
                'title' => trim($payload['title'] ?? '') ?: 'Önemli Duyuru',
                'body' => $body !== '' ? nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) : '',
                'highlight' => trim($payload['highlight'] ?? ''),
            ],
            NewsletterTemplateId::NewPost => [
                'postTitle' => trim($payload['post_title'] ?? '') ?: 'Yeni içerik',
                'postExcerpt' => trim($payload['post_excerpt'] ?? ''),
                'postUrl' => trim($payload['post_url'] ?? '') ?: $this->resolveSiteUrl(),
                'postType' => trim($payload['post_type'] ?? '') ?: 'İçerik',
            ],
            NewsletterTemplateId::Digest => [
                'title' => trim($payload['title'] ?? '') ?: 'Özet',
                'intro' => trim($payload['intro'] ?? ''),
                'items' => $this->parseDigestItems($payload['digest_items'] ?? ''),
            ],
            NewsletterTemplateId::Custom => [
                'bodyHtml' => $bodyHtml !== '' ? $this->contentSanitizer->sanitize($bodyHtml) : nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            ],
        };
    }

    /** @return list<array{title: string, url: string|null}> */
    private function parseDigestItems(string $raw): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$title, $url] = array_map('trim', explode('|', $line, 2));
                $items[] = ['title' => $title, 'url' => $url !== '' ? $url : null];
            } else {
                $items[] = ['title' => $line, 'url' => null];
            }
        }

        return $items;
    }

    private function buildUnsubscribeUrl(?Subscriber $subscriber): string
    {
        if ($subscriber === null || $subscriber->getToken() === null) {
            return $this->resolveSiteUrl();
        }

        return $this->urlGenerator->generate(
            'front_unsubscribe',
            ['token' => $subscriber->getToken()->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function resolveSiteUrl(): string
    {
        $siteUrl = rtrim((string) $this->settingsService->get('site_url', ''), '/');
        if ($siteUrl !== '') {
            return $siteUrl;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return '';
    }

    private function htmlToPlainText(string $html): string
    {
        $text = strip_tags(str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '</li>', '</h1>', '</h2>', '</h3>'],
            ["\n", "\n", "\n", "\n\n", "\n", "\n\n", "\n\n", "\n\n"],
            $html
        ));

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
