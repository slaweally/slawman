<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%admin_prefix%')]
        private readonly string $adminPrefix = 'admin',
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $path = $event->getRequest()->getPathInfo();
        $isAdmin = str_starts_with($path, '/' . ltrim($this->adminPrefix, '/'));
        $styleSrc = $isAdmin ? "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " : "style-src 'self' 'unsafe-inline' https:; ";
        $imgSrc = $isAdmin ? "img-src 'self' data: https:; " : "img-src 'self' data: https: blob:; ";
        $connectSrc = $isAdmin
            ? "connect-src 'self' https://static.cloudflareinsights.com; "
            : "connect-src 'self' https://challenges.cloudflare.com https://static.cloudflareinsights.com; ";
        $scriptSrc = $isAdmin
            ? "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://static.cloudflareinsights.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
            : "script-src 'self' https://challenges.cloudflare.com https://static.cloudflareinsights.com; ";

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "base-uri 'self'; frame-ancestors 'none'; object-src 'none'; " .
            $scriptSrc .
            $styleSrc .
            $imgSrc .
            "font-src 'self' data:; " .
            $connectSrc .
            "frame-src https://challenges.cloudflare.com; " .
            "form-action 'self'; upgrade-insecure-requests;"
        );

        if ($isAdmin) {
            // Never cache admin/auth pages on reverse proxies (Varnish/Cloudflare/browser).
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0, s-maxage=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Surrogate-Control', 'no-store');
        }

        if ($this->environment === 'prod' && $event->getRequest()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }
}
