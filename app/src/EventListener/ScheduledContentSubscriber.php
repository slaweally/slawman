<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\PostRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * WordPress'in "wp-cron.php sanal cron" mantığının Symfony karşılığı: gerçek bir
 * sistem cron job'u kullanılmaz. Bunun yerine, siteye gelen her frontend isteğinde
 * zamanı gelmiş "scheduled" içerikler tek bir DQL bulk UPDATE ile "published"a çekilir.
 *
 * Maliyet kontrolü:
 * - Sadece ana istekte ve admin paneli dışında çalışır (asset/AJAX/admin isteklerinde hiç tetiklenmez).
 * - Aynı WP_CRON_LOCK_TIMEOUT mantığında kısa ömürlü bir cache kilidi kullanılır:
 *   art arda gelen istekler DB'yi her seferinde yormasın diye kontrol en fazla
 *   LOCK_TTL saniyede bir gerçekten çalışır, aradaki istekler anında geçer.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -32)]
class ScheduledContentSubscriber
{
    private const LOCK_TTL = 30;
    private const LOCK_KEY = 'virtual_cron_scheduled_posts_lock';

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly AdapterInterface $cache,
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
        if ($this->isAdminRequest($request)) {
            return;
        }

        $lock = $this->cache->getItem(self::LOCK_KEY);
        if ($lock->isHit()) {
            return;
        }

        $lock->set(true);
        $lock->expiresAfter(self::LOCK_TTL);
        $this->cache->save($lock);

        try {
            $published = $this->postRepository->publishDueScheduledPosts(new \DateTime());
            if ($published > 0) {
                $this->logger->info('Sanal cron: zamanlanmış içerik yayına alındı', ['count' => $published]);
            }
        } catch (\Throwable $e) {
            // Sanal cron hatası hiçbir koşulda sayfa isteğini etkilememeli.
            $this->logger->error('Sanal cron çalıştırılamadı: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        $prefix = '/' . trim($this->adminPrefix, '/');

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
