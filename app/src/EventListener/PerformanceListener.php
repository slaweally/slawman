<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -20)]
class PerformanceListener
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->settingsService->isTruthy('performance_compress_html')) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }

        $compressed = preg_replace('/\s{2,}/', ' ', preg_replace('/>\s+</', '><', $content));
        if (is_string($compressed)) {
            $response->setContent($compressed);
        }
    }
}
