<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SecurityAlert;
use App\Repository\SecurityAlertRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * WAF ve auth katmanının tespit ettiği tüm şüpheli olayların tek giriş noktası:
 * `security_alert` tablosuna kalıcı kayıt düşer, admin panelde listelenir ve
 * (yapılandırılmışsa) AdminNotificationService üzerinden mail tetiklenir.
 * TrackerLogService::logAlert() dekoratif "hacker paneli" içindir — bu servis
 * onun yerini almaz, ikisi paralel çalışır.
 *
 * Aggregation: aynı IP + aynı tehdit türü için yeni satır AÇILMAZ — mevcut
 * satırın hit_count'u artırılır ve last_seen_at güncellenir (bkz. record()).
 * Bu sayede tekrarlayan tarama/saldırı trafiği tabloyu şişirmez.
 *
 * Throttle/ban: aynı IP 1 dakikada `waf_max_requests` kadar WAF (sqli/xss/
 * traversal/recon) alarmı üretirse `waf_ban_duration` saatliğine banlanır.
 * Ban aktifken gelen istekler `waf_log_banned_repeats` ayarı false ise
 * veritabanına HİÇ yazılmaz — sadece cache (APCu/Redis/dosya, bkz. cache.app)
 * üzerinden 403 ile kesilir, Write IO tetiklenmez.
 */
class SecurityAlertService
{
    private const WAF_TYPES = [SecurityAlert::TYPE_SQLI, SecurityAlert::TYPE_XSS, SecurityAlert::TYPE_TRAVERSAL, SecurityAlert::TYPE_RECON];
    private const THROTTLE_WINDOW = 60;
    private const BANNED_IPS_SET_KEY = 'waf_banned_ips_set';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly AdminNotificationService $adminNotificationService,
        private readonly AdapterInterface $cache,
        private readonly SettingsService $settingsService,
    ) {
    }

    public function record(string $type, string $severity, Request $request, ?string $reason = null): ?SecurityAlert
    {
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $alreadyBanned = in_array($type, self::WAF_TYPES, true) && $this->isBanned($ip);

        // Banlı IP tekrarı ve loglama kapalıysa: cache zaten 403 verdi, DB'ye hiç dokunma.
        if ($alreadyBanned && !$this->settingsService->isTruthy('waf_log_banned_repeats')) {
            return null;
        }

        $alert = $this->upsert($ip, $type, $severity, $request, $reason);

        $justBanned = false;
        if (in_array($type, self::WAF_TYPES, true) && !$alreadyBanned) {
            $justBanned = $this->registerWafHitAndMaybeBan($ip);
        }

        // Ban zaten aktifse (yeni ban değil) sessizce logla, mail atma.
        $mailSent = $alreadyBanned ? false : $this->notify($type, $severity, $alert, $reason, $justBanned);
        $alert->setMailSent($mailSent);
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * Aynı IP+type için var olan satırı bulup hit_count/last_seen_at günceller;
     * yoksa yeni satır açar. Saldırı trafiği doğası gereği eşzamanlı (concurrent)
     * isteklerle gelebildiğinden, iki isteğin aynı anda "satır yok" görüp ikisinin
     * de INSERT denemesi ihtimaline karşı unique constraint ihlalini yakalayıp
     * bir kez UPDATE'e düşerek yeniden dener.
     */
    private function upsert(string $ip, string $type, string $severity, Request $request, ?string $reason): SecurityAlert
    {
        $existing = $this->securityAlertRepository->findOneByIpAndType($ip, $type);

        if ($existing instanceof SecurityAlert) {
            $existing->incrementHitCount();
            $existing->setLastSeenAt(new \DateTimeImmutable());
            $existing->setSeverity($severity);
            $existing->setRequestPath($request->getPathInfo());
            $existing->setRequestMethod($request->getMethod());
            $existing->setUserAgent($request->headers->get('User-Agent'));
            $existing->setReason($reason);
            $this->entityManager->flush();

            return $existing;
        }

        $alert = new SecurityAlert();
        $alert->setType($type);
        $alert->setSeverity($severity);
        $alert->setIpAddress($ip);
        $alert->setRequestPath($request->getPathInfo());
        $alert->setRequestMethod($request->getMethod());
        $alert->setUserAgent($request->headers->get('User-Agent'));
        $alert->setReason($reason);
        $this->entityManager->persist($alert);

        try {
            $this->entityManager->flush();

            return $alert;
        } catch (UniqueConstraintViolationException) {
            // Başka bir eşzamanlı istek arada satırı açtı — entity manager'ı temizleyip
            // gerçek satırı çekip UPDATE'e düş.
            $this->entityManager->detach($alert);
            $race = $this->securityAlertRepository->findOneByIpAndType($ip, $type);
            if (!$race instanceof SecurityAlert) {
                // Aşırı uç durum: satır zaten silinmiş olabilir, sessizce tek satırlık geri dön.
                return $alert;
            }

            $race->incrementHitCount();
            $race->setLastSeenAt(new \DateTimeImmutable());
            $race->setSeverity($severity);
            $race->setRequestPath($request->getPathInfo());
            $race->setRequestMethod($request->getMethod());
            $race->setUserAgent($request->headers->get('User-Agent'));
            $race->setReason($reason);
            $this->entityManager->flush();

            return $race;
        }
    }

    /** Dinamik (throttle sonucu) ban VEYA admin tarafından girilmiş manuel kara liste. */
    public function isBanned(string $ip): bool
    {
        return $this->cache->getItem($this->banKey($ip))->isHit() || $this->isBlacklisted($ip);
    }

    public function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->parseIpList('waf_whitelist_ips'), true);
    }

    public function isBlacklisted(string $ip): bool
    {
        return in_array($ip, $this->parseIpList('waf_blacklist_ips'), true);
    }

    /** @return list<string> */
    private function parseIpList(string $settingKey): array
    {
        $raw = (string) $this->settingsService->get($settingKey, '');
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $raw) ?: [])));
    }

    /** WAF hit sayacını artırır; eşik aşıldıysa IP'yi banlar ve true döner. Eşik/süre admin ayarlarından dinamik okunur. */
    private function registerWafHitAndMaybeBan(string $ip): bool
    {
        $limit = max(1, $this->settingsService->getInt('waf_max_requests', 3));
        $banHours = max(1, $this->settingsService->getInt('waf_ban_duration', 24));

        $counterItem = $this->cache->getItem($this->counterKey($ip));
        $hits = ((int) $counterItem->get()) + 1;
        $counterItem->set($hits);
        $counterItem->expiresAfter(self::THROTTLE_WINDOW);
        $this->cache->save($counterItem);

        if ($hits < $limit) {
            return false;
        }

        $banItem = $this->cache->getItem($this->banKey($ip));
        $banItem->set(true);
        $banItem->expiresAfter($banHours * 3600);
        $this->cache->save($banItem);
        $this->addToBannedSet($ip);

        $this->cache->deleteItem($this->counterKey($ip));

        return true;
    }

    /** Dashboard'daki "aktif banlı IP" sayısı için — ban atılırken sete eklenir. */
    private function addToBannedSet(string $ip): void
    {
        $setItem = $this->cache->getItem(self::BANNED_IPS_SET_KEY);
        $set = is_array($setItem->get()) ? $setItem->get() : [];
        $set[$ip] = true;
        $setItem->set($set);
        $this->cache->save($setItem);
    }

    /** Aktif (henüz süresi dolmamış) banlı IP sayısı. */
    public function countActiveBans(): int
    {
        $setItem = $this->cache->getItem(self::BANNED_IPS_SET_KEY);
        $set = is_array($setItem->get()) ? $setItem->get() : [];

        $active = 0;
        $stillBanned = [];
        foreach (array_keys($set) as $ip) {
            if ($this->cache->getItem($this->banKey((string) $ip))->isHit()) {
                $active++;
                $stillBanned[$ip] = true;
            }
        }

        // Süresi dolmuş IP'leri setten temizle (lazy sweep).
        if (count($stillBanned) !== count($set)) {
            $setItem->set($stillBanned);
            $this->cache->save($setItem);
        }

        return $active;
    }

    /** Admin panelden tetiklenen toplu temizlik: dinamik banları ve WAF sayaçlarını sıfırlar. */
    public function clearAllBans(): void
    {
        $setItem = $this->cache->getItem(self::BANNED_IPS_SET_KEY);
        $set = is_array($setItem->get()) ? $setItem->get() : [];
        foreach (array_keys($set) as $ip) {
            $this->cache->deleteItem($this->banKey((string) $ip));
        }
        $this->cache->deleteItem(self::BANNED_IPS_SET_KEY);
    }

    private function counterKey(string $ip): string
    {
        return 'waf_hits_' . str_replace([':', '.'], '_', $ip);
    }

    private function banKey(string $ip): string
    {
        return 'waf_ban_' . str_replace([':', '.'], '_', $ip);
    }

    /**
     * Anlık mail varsayılan olarak KAPALIDIR (waf_notify_realtime_enabled): tekil bot
     * taraması/recon isteği başına mail atmak anlamsız gürültü üretir. Sadece IP
     * gerçekten banlandığında (justBanned) — yani eşik aşılıp ciddi/tekrarlayan bir
     * saldırı olduğunda — anlık mail gönderilir. Genel günlük özet için bkz.
     * SecurityDigestCommand + waf_notify_digest_enabled.
     */
    private function notify(string $type, string $severity, SecurityAlert $alert, ?string $reason, bool $justBanned = false): bool
    {
        if ($severity !== SecurityAlert::SEVERITY_CRITICAL && $severity !== SecurityAlert::SEVERITY_HIGH) {
            return false;
        }

        if (!$justBanned && !$this->settingsService->isTruthy('waf_notify_realtime_enabled')) {
            return false;
        }

        try {
            $banHours = max(1, $this->settingsService->getInt('waf_ban_duration', 24));
            $title = $justBanned ? sprintf('%s — IP %d saat banlandı', self::titleFor($type), $banHours) : self::titleFor($type);
            $this->adminNotificationService->notifySecurityAlert($title, [
                'Tür' => self::labelFor($type),
                'Önem' => strtoupper($severity),
                'IP' => $alert->getIpAddress(),
                'Yol' => (string) $alert->getRequestPath(),
                'Metod' => (string) $alert->getRequestMethod(),
                'Sebep' => $reason ?? '-',
                'Zaman' => $alert->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function labelFor(string $type): string
    {
        return match ($type) {
            SecurityAlert::TYPE_SQLI => 'SQL Injection',
            SecurityAlert::TYPE_XSS => 'XSS',
            SecurityAlert::TYPE_TRAVERSAL => 'Directory Traversal',
            SecurityAlert::TYPE_RECON => 'Bot Scan / Reconnaissance',
            SecurityAlert::TYPE_LOGIN_FAILED => 'Başarısız Giriş',
            SecurityAlert::TYPE_RATE_LIMIT => 'Rate Limit Aşımı',
            default => strtoupper($type),
        };
    }

    private static function titleFor(string $type): string
    {
        return match ($type) {
            SecurityAlert::TYPE_SQLI => 'SQL Injection denemesi engellendi',
            SecurityAlert::TYPE_XSS => 'XSS denemesi engellendi',
            SecurityAlert::TYPE_TRAVERSAL => 'Directory traversal denemesi engellendi',
            SecurityAlert::TYPE_RECON => 'Bot tarama / reconnaissance denemesi engellendi',
            SecurityAlert::TYPE_LOGIN_FAILED => 'Başarısız admin girişi',
            SecurityAlert::TYPE_RATE_LIMIT => 'Rate limit aşıldı',
            default => 'Güvenlik uyarısı',
        };
    }
}
