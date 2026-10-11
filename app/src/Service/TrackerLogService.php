<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TrackerLog;
use App\Entity\TrackerMessage;
use App\Repository\TrackerLogRepository;
use App\Repository\TrackerMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Esprili, siber temalı "canlı takip" panelini besleyen servis.
 * Ziyaretçinin gerçek kimliği YOK SAYILIR: gösterilen isim IP'ye sabit
 * atanmış bir takma addır (UserTrackerService), IP her zaman maskelenir.
 */
class TrackerLogService
{
    private const DEFAULT_TEMPLATES = [
        'visit' => '[{ip}] {name} [{post}] sayfasına uğradı, göz gezdiriyor...',
        'comment' => '[{ip}] {name} [{post}] okudu ve yorum yazdı: incelemem lazım :)',
        'login' => '[{ip}] {name} siteye giriş yaptı, hoşgelmişsiniz efenim.',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly TrackerMessageRepository $trackerMessageRepository,
        private readonly UserTrackerService $userTrackerService,
        private readonly SettingsService $settingsService,
    ) {
    }

    public function log(string $eventType, Request $request, ?string $pageTitle = null, ?string $sessionKey = null): void
    {
        if (!$this->settingsService->isTruthy('tracker_enabled')) {
            return;
        }

        $ip = $request->getClientIp() ?? '0.0.0.0';
        $maskedIp = TrackerLogService::maskIp($ip);
        $name = $this->userTrackerService->resolveDisplayName($ip);
        [$template, $templateId] = $this->pickTemplate($eventType);
        $message = strtr($template, [
            '{ip}' => $maskedIp,
            '{name}' => $name,
            '{post}' => $pageTitle ?? $this->fallbackPageLabel($request),
        ]);

        if ($eventType === 'visit' && $sessionKey !== null) {
            $this->closePreviousVisit($sessionKey);
        }

        $log = new TrackerLog();
        $log->setEventType($eventType);
        $log->setMessage($message);
        $log->setIpAddress($maskedIp);
        $log->setMaskedIp($maskedIp);
        $log->setPageUrl($request->getPathInfo());
        $log->setSessionKey($sessionKey);
        $log->setTemplateId($templateId);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * WAF/güvenlik olayları için sabit formatlı, admin tarafından
     * düzenlenemeyen "KIRMIZI ALERT" kaydı. tracker_enabled ayarından
     * bağımsız olarak her zaman yazılır — güvenlik izlemesi dekoratif
     * panel ayarına bağlı olmamalı.
     */
    public function logAlert(string $reason, Request $request): void
    {
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $maskedIp = TrackerLogService::maskIp($ip);
        $name = $this->userTrackerService->resolveDisplayName($ip);

        $log = new TrackerLog();
        $log->setEventType('alert');
        $log->setMessage(sprintf('🚨 [%s] %s şüpheli bir hareket yaptı: %s', $maskedIp, $name, $reason));
        $log->setIpAddress($maskedIp);
        $log->setMaskedIp($maskedIp);
        $log->setPageUrl($request->getPathInfo());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * Loglanan mesaj metnindeki köşeli parantez segmentini ([sayfa-yolu]) tıklanabilir
     * bir <a> linkine çevirir. Kaynak `pageUrl` alanı zaten ham path olarak saklanıyor
     * (bkz. log()), her zaman aynı `[...]` bloğu sayfa etiketidir. Metin htmlspecialchars
     * ile kaçırılır; yalnızca üretilen <a> etiketi HTML olarak işaretlenir.
     */
    public function renderMessageWithLink(TrackerLog $log): string
    {
        $message = $log->getMessage();
        $pageUrl = $log->getPageUrl();

        if ($pageUrl === null || $pageUrl === '' || !preg_match('/\[([^\[\]]+)\]/', $message, $matches, PREG_OFFSET_CAPTURE, 0)) {
            return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        // WAF alert mesajları ("[maskedIp]") bir sayfa değil, IP bloğunu taşır — atla.
        if ($log->getEventType() === 'alert') {
            return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        // Mesaj `[ip] isim [sayfa] ...` şeklinde; ikinci köşeli parantez sayfa etiketidir.
        if (!preg_match_all('/\[([^\[\]]+)\]/', $message, $all)) {
            return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        $pageLabel = $all[1][1] ?? $all[1][0] ?? null;
        if ($pageLabel === null) {
            return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        $href = $pageUrl === '/' || $pageUrl === '' ? '/' : $pageUrl;
        $link = sprintf(
            '<a href="%s" class="tracker-panel__link">%s</a>',
            htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8')
        );

        $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars('[' . $pageLabel . ']', ENT_QUOTES, 'UTF-8');

        $pos = strrpos($escaped, $escapedLabel);
        if ($pos === false) {
            return $escaped;
        }

        return substr_replace($escaped, $link, $pos, strlen($escapedLabel));
    }

    public static function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $visible = array_slice($parts, 0, max(1, (int) ceil(count($parts) / 2)));

            return implode(':', $visible) . ':xxxx:xxxx';
        }

        $octets = explode('.', $ip);
        if (count($octets) === 4) {
            return sprintf('%s.%s.xx.xx', $octets[0], $octets[1]);
        }

        return 'xx.xx.xx.xx';
    }

    /** @return array{0: string, 1: ?int} [şablon metni, şablon id] */
    private function pickTemplate(string $eventType): array
    {
        $active = $this->trackerMessageRepository->findActiveByType($eventType);
        if ($active === []) {
            return [self::DEFAULT_TEMPLATES[$eventType] ?? '[{ip}] {name} bir hareket yaptı.', null];
        }

        if (count($active) > 1) {
            $lastId = $this->trackerLogRepository->findLastTemplateIdByType($eventType);
            $filtered = array_values(array_filter(
                $active,
                static fn (TrackerMessage $m): bool => $m->getId() !== $lastId
            ));
            if ($filtered !== []) {
                $active = $filtered;
            }
        }

        $chosen = $active[array_rand($active)];

        return [$chosen->getTemplate(), $chosen->getId()];
    }

    private function fallbackPageLabel(Request $request): string
    {
        $path = trim($request->getPathInfo(), '/');

        return $path === '' ? 'anasayfa' : $path;
    }

    /** Bir önceki ziyaretin "sayfada geçirdiği süre"sini best-effort olarak hesaplar. */
    private function closePreviousVisit(string $sessionKey): void
    {
        $previous = $this->trackerLogRepository->findLatestBySessionKey($sessionKey);
        if ($previous === null || $previous->getDurationSeconds() !== null) {
            return;
        }

        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $previous->getCreatedAt()->getTimestamp();
        if ($elapsed > 0 && $elapsed < 1800) {
            $previous->setDurationSeconds($elapsed);
        }
    }
}
