<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SeoRedirectService;
use App\Service\SettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bulunamayan public URL'leri (404) ayarlardaki hedefe yönlendirir.
 * Bilinen eski URL'ler önce SeoRedirectListener ile eşleştirilir.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class NotFoundRedirectListener
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SeoRedirectService $seoRedirectService,
        #[Autowire('%admin_prefix%')]
        private readonly string $adminPrefix,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof NotFoundHttpException) {
            return;
        }

        if (!$this->settingsService->isTruthy('seo_not_found_redirect_enabled')) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        if ($this->wantsJson($request)) {
            return;
        }

        $path = $this->seoRedirectService->normalizePath($request->getPathInfo());
        if ($this->shouldSkip($path)) {
            return;
        }

        $target = trim((string) $this->settingsService->get('seo_not_found_redirect_target', '/'));
        if ($target === '' || str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return;
        }

        $targetPath = $this->seoRedirectService->normalizePath($target);
        if ($targetPath === $path) {
            return;
        }

        $statusCode = (int) $this->settingsService->get('seo_not_found_redirect_status', '301');
        if (!in_array($statusCode, [301, 302, 307, 308], true)) {
            $statusCode = 301;
        }

        $event->setResponse(new RedirectResponse($targetPath, $statusCode));
    }

    private function shouldSkip(string $path): bool
    {
        $adminBase = $this->seoRedirectService->normalizePath('/' . ltrim($this->adminPrefix, '/'));
        if ($path === $adminBase || str_starts_with($path, $adminBase . '/')) {
            return true;
        }

        foreach (['/build', '/bundles', '/_wdt', '/_profiler'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');
        if ($accept === '') {
            return false;
        }

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }
}
