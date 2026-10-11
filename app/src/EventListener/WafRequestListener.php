<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\SecurityAlert;
use App\Service\SecurityAlertService;
use App\Service\SettingsService;
use App\Service\TrackerLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * İmza tabanlı WAF: bot taraması / directory traversal / SQLi / XSS
 * denemelerini request'e ulaşmadan önce yakalar, 403 ile keser, admine mail
 * atar ve hacker paneline "KIRMIZI ALERT" olarak düşürür.
 *
 * Sınıflandırma önceliği (ilk eşleşen kazanır — daha spesifik olan önce):
 *   1) Dosya/yol taraması (recon)   — sadece PATH üzerinde (.env, wp-config.php, .aws/config, .yml/.json vb.)
 *   2) Directory traversal          — sadece PATH üzerinde (../, /etc/passwd)
 *   3) SQL Injection                — sadece QUERY/BODY parametrelerinde ('OR', UNION SELECT vb.)
 *   4) XSS                          — PATH + QUERY/BODY birlikte
 * Path taraması ile query/body taraması kasıtlı olarak ayrı tutulur: bir
 * dosya tarama isteği asla "SQL Injection" olarak yanlış etiketlenmez.
 *
 * Admin (backstage) altındaki istekler taranmaz — CMS içeriği içinde
 * meşru olarak bu desenlere benzer metin geçebilir (ör. kod bloğu).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 48)]
class WafRequestListener
{
    private const RECON_PATTERN = '/(\.env(?!\.example)|\.git\/config|\bwp-config\.php\b|\.aws\/(config|credentials)|\.ya?ml$|\.json$|\bconfig\.php\b|\bdocker-compose\.ya?ml\b|\.htpasswd|\.ssh\/|id_rsa|\bphpinfo\.php\b|\/\.well-known\/(?!acme-challenge))/i';
    private const TRAVERSAL_PATTERN = '/(\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e\/|\/etc\/passwd|\/etc\/shadow|\bwindows\/win\.ini\b)/i';
    private const SQLI_PATTERN = '/(\bunion\b\s+\bselect\b|\bor\b\s+[\'"]?1[\'"]?\s*=\s*[\'"]?1|\band\b\s+[\'"]?1[\'"]?\s*=\s*[\'"]?1|\bdrop\b\s+\btable\b|\binformation_schema\b|\bsleep\s*\(|\bbenchmark\s*\(|;\s*--|\/\*.*\*\/)/i';
    private const XSS_PATTERN = '/(<script[\s>\/]|javascript:|on(error|load|mouseover|focus)\s*=|<iframe[\s>]|document\.cookie)/i';

    private const REASON_TO_TYPE = [
        'Bot taraması / hassas dosya keşfi' => SecurityAlert::TYPE_RECON,
        'Directory traversal / hassas dosya erişimi' => SecurityAlert::TYPE_TRAVERSAL,
        'SQL Injection deneme' => SecurityAlert::TYPE_SQLI,
        'XSS deneme' => SecurityAlert::TYPE_XSS,
    ];

    public function __construct(
        private readonly TrackerLogService $trackerLogService,
        private readonly SecurityAlertService $securityAlertService,
        private readonly SettingsService $settingsService,
        private readonly LoggerInterface $logger,
        private readonly string $adminPrefix,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/' . trim($this->adminPrefix, '/'))) {
            return;
        }

        $ip = $request->getClientIp() ?? '0.0.0.0';

        // Beyaz liste her kontrolden önce gelir — hiçbir taramaya/bana takılmaz.
        if ($this->securityAlertService->isWhitelisted($ip)) {
            return;
        }

        if ($this->securityAlertService->isBanned($ip)) {
            $reason = $this->securityAlertService->isBlacklisted($ip)
                ? 'Manuel kara listedeki IP istek attı'
                : 'Banlı IP tekrar istek attı';

            try {
                // Tip burada önemli değil: DB yazımı SecurityAlertService içinde
                // waf_log_banned_repeats ayarına göre baştan engellenir/aggregate edilir.
                $this->securityAlertService->record(SecurityAlert::TYPE_SQLI, SecurityAlert::SEVERITY_CRITICAL, $request, $reason);
            } catch (\Throwable) {
                // Kayıt hatası bloklama işlemini etkilememeli.
            }

            $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']));

            return;
        }

        $reason = $this->detect($request);
        if ($reason === null) {
            return;
        }

        $this->logger->warning('WAF: şüpheli istek engellendi', [
            'ip' => $request->getClientIp(),
            'path' => $path,
            'reason' => $reason,
        ]);

        try {
            $this->trackerLogService->logAlert($reason, $request);
        } catch (\Throwable) {
            // Alert loglaması bloklama işlemini etkilememeli.
        }

        try {
            $this->securityAlertService->record(
                self::REASON_TO_TYPE[$reason] ?? SecurityAlert::TYPE_SQLI,
                SecurityAlert::SEVERITY_CRITICAL,
                $request,
                $reason
            );
        } catch (\Throwable) {
            // Kayıt/mail hatası bloklama işlemini etkilememeli.
        }

        $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']));
    }

    private function detect(Request $request): ?string
    {
        $path = rawurldecode($request->getPathInfo());
        $params = rawurldecode(implode(' ', array_filter([
            (string) $request->getQueryString(),
            $this->flatten($request->request->all()),
        ])));

        // 1) Bot taraması / hassas dosya keşfi — sadece yol üzerinde, en spesifik olan önce.
        if ($this->settingsService->isTruthy('waf_detect_recon') && preg_match(self::RECON_PATTERN, $path) === 1) {
            return 'Bot taraması / hassas dosya keşfi';
        }

        // 2) Directory traversal — sadece yol üzerinde.
        if ($this->settingsService->isTruthy('waf_detect_traversal') && preg_match(self::TRAVERSAL_PATTERN, $path) === 1) {
            return 'Directory traversal / hassas dosya erişimi';
        }

        // 3) SQL Injection — yalnızca query/body parametrelerinde aranır; dosya
        // taraması istekleri (yukarıda elendi) asla buraya düşüp yanlış etiketlenmez.
        if ($params !== '' && $this->settingsService->isTruthy('waf_detect_sqli') && preg_match(self::SQLI_PATTERN, $params) === 1) {
            return 'SQL Injection deneme';
        }

        // 4) XSS — hem yol hem parametrelerde aranır.
        $haystack = trim($path . ' ' . $params);
        if ($haystack !== '' && $this->settingsService->isTruthy('waf_detect_xss') && preg_match(self::XSS_PATTERN, $haystack) === 1) {
            return 'XSS deneme';
        }

        return null;
    }

    /** @param array<int|string, mixed> $data */
    private function flatten(array $data): string
    {
        $parts = [];
        array_walk_recursive($data, function ($value) use (&$parts): void {
            $parts[] = (string) $value;
        });

        return implode(' ', $parts);
    }
}
