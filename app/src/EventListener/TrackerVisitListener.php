<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\TrackerLogService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anasayfadaki "hacker temalı takip paneli" için ziyaret olaylarını loglar.
 * Ziyaretçiyi tanımlamak için kısa ömürlü, kimliksiz bir çerez (tvid) kullanılır;
 * bu çerez yalnızca ardışık sayfa görüntülemeleri arasında süre hesaplamak içindir.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -64)]
class TrackerVisitListener
{
    private const SKIP_PREFIXES = ['/build/', '/uploads/', '/_wdt/', '/_profiler/', '/tracker-feed'];
    private const COOKIE_NAME = 'tvid';

    public function __construct(
        private readonly TrackerLogService $trackerLogService,
        private readonly string $adminPrefix,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->shouldTrack($request)) {
            return;
        }

        $sessionKey = $request->cookies->get(self::COOKIE_NAME);
        if ($sessionKey === null) {
            $sessionKey = bin2hex(random_bytes(16));
            $event->getResponse()->headers->setCookie(
                Cookie::create(self::COOKIE_NAME, $sessionKey)
                    ->withExpires(time() + 3600 * 24 * 180)
                    ->withHttpOnly(true)
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }

        try {
            $this->trackerLogService->log('visit', $request, null, $sessionKey);
        } catch (\Throwable) {
            // Takip paneli hatası sayfayı etkilememeli.
        }
    }

    private function shouldTrack(Request $request): bool
    {
        if ($request->getMethod() !== Request::METHOD_GET) {
            return false;
        }

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/' . trim($this->adminPrefix, '/') . '/')
            || $path === '/' . trim($this->adminPrefix, '/')) {
            return false;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
