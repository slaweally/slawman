<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\VisitorStatsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -64)]
class VisitorTrackingListener
{
    private const SKIP_PREFIXES = ['/build/', '/uploads/', '/_wdt/', '/_profiler/'];

    public function __construct(
        private readonly VisitorStatsService $visitorStatsService,
        private readonly string $adminPrefix,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/' . trim($this->adminPrefix, '/') . '/')
            || $path === '/' . trim($this->adminPrefix, '/')) {
            return;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        try {
            $isNewVisitor = $this->visitorStatsService->track($request);
            if ($isNewVisitor) {
                $tomorrow = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0);
                $event->getResponse()->headers->setCookie(
                    Cookie::create(VisitorStatsService::VISITOR_COOKIE_NAME)
                        ->withValue('1')
                        ->withExpires($tomorrow)
                        ->withHttpOnly(true)
                        ->withSameSite(Cookie::SAMESITE_LAX)
                );
            }
        } catch (\Throwable) {
            // İstatistik hatası sayfayı etkilememeli.
        }
    }
}
