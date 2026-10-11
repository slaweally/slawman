<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\SecurityAlert;
use App\Service\SecurityAlertService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class AdminActionRateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.admin_actions')]
        private readonly RateLimiterFactory $adminActionLimiter,
        #[Autowire('%admin_prefix%')]
        private readonly string $adminPrefix,
        private readonly SecurityAlertService $securityAlertService,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== Request::METHOD_POST) {
            return;
        }

        $pathInfo = $request->getPathInfo();
        if (!str_starts_with($pathInfo, '/' . ltrim($this->adminPrefix, '/'))) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if (in_array($route, ['admin_login', 'admin_logout'], true)) {
            return;
        }

        $key = sprintf('%s|%s', $request->getClientIp() ?? 'unknown', $route !== '' ? $route : $pathInfo);
        $limit = $this->adminActionLimiter->create($key)->consume(1);

        if (!$limit->isAccepted()) {
            try {
                $this->securityAlertService->record(
                    SecurityAlert::TYPE_RATE_LIMIT,
                    SecurityAlert::SEVERITY_MEDIUM,
                    $request,
                    sprintf('admin_actions limiti aşıldı (route: %s)', $route !== '' ? $route : $pathInfo)
                );
            } catch (\Throwable) {
                // Kayıt hatası bloklama davranışını etkilememeli.
            }

            throw new TooManyRequestsHttpException(60, 'Çok fazla admin işlemi gönderildi. Lütfen biraz bekleyin.');
        }
    }
}
