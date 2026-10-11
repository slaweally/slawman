<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SeoRedirectService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest', priority: 20)]
class SeoRedirectListener
{
    public function __construct(private readonly SeoRedirectService $seoRedirectService)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $redirect = $this->seoRedirectService->resolve($request->getPathInfo());
        if ($redirect === null) {
            return;
        }

        $event->setResponse(new RedirectResponse($redirect->getTargetPath(), $redirect->getStatusCode()));
    }
}
