<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FaviconController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settingsService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/favicon.ico', name: 'front_favicon', priority: 20)]
    public function favicon(): Response
    {
        $configured = trim((string) ($this->settingsService->get('site_favicon') ?? ''));
        if ($configured !== '') {
            if (str_starts_with($configured, '/uploads/')) {
                $path = $this->projectDir . '/public' . $configured;
                if (is_file($path)) {
                    return new BinaryFileResponse($path, 200, [], false, 'inline');
                }
            }

            if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
                return $this->redirect($configured);
            }
        }

        $path = $this->projectDir . '/public/favicon.ico';
        if (is_file($path)) {
            return new BinaryFileResponse($path, 200, [], false, 'inline');
        }

        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
<rect width="32" height="32" rx="6" fill="#2D5BE3"/>
<text x="16" y="22" text-anchor="middle" font-family="Arial,sans-serif" font-size="16" font-weight="700" fill="#fff">S</text>
</svg>
SVG;

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
